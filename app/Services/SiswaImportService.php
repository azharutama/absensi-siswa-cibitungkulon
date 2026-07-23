<?php

namespace App\Services;

use App\Imports\SiswaSpreadsheetImport;
use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;
use RuntimeException;
use Throwable;

class SiswaImportService
{
    public const CHUNK_SIZE = 250;

    private const MAX_IMPORT_ROWS = 10000;

    private const MAX_ERROR_DETAILS = 100;

    private const HEADER_ALIASES = [
        'nis' => 'nis',
        'nisn' => 'nisn',
        'nama siswa' => 'nama_siswa',
        'nama lengkap siswa' => 'nama_siswa',
        'nama' => 'nama_siswa',
        'jenis kelamin' => 'jenis_kelamin',
        'jk' => 'jenis_kelamin',
        'kelas' => 'kelas',
        'nama ayah' => 'nama_ayah',
        'no whatsapp ayah' => 'no_whatsapp_ayah',
        'wa ayah' => 'no_whatsapp_ayah',
        'nama ibu' => 'nama_ibu',
        'no whatsapp ibu' => 'no_whatsapp_ibu',
        'wa ibu' => 'no_whatsapp_ibu',
        'nama wali' => 'nama_wali',
        'no whatsapp wali' => 'no_whatsapp_wali',
        'wa wali' => 'no_whatsapp_wali',
        'status' => 'status',
    ];

    /** @var Collection<string, Collection<int, Kelas>> */
    private Collection $kelasByName;

    /** @var array{created: int, updated: int, skipped: int, error_count: int, errors: array<int, string>} */
    private array $summary;

    private int $processedRows = 0;

    private bool $headersValidated = false;

    /**
     * @return array{created: int, updated: int, skipped: int, error_count: int, errors: array<int, string>}
     */
    public function import(UploadedFile $file): array
    {
        $this->reset();

        try {
            Excel::import(new SiswaSpreadsheetImport($this), $file);
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw new RuntimeException('File import tidak dapat dibaca.', previous: $exception);
        }

        if ($this->processedRows === 0) {
            $this->addError('File import tidak memiliki baris data siswa.');
        }

        if ($this->summary['error_count'] > self::MAX_ERROR_DETAILS) {
            $omitted = $this->summary['error_count'] - self::MAX_ERROR_DETAILS;
            $this->summary['errors'][] = "{$omitted} kesalahan lain tidak ditampilkan.";
        }

        return $this->summary;
    }

    /**
     * @param  Collection<int, Collection<string, mixed>>  $rows
     */
    public function processRows(Collection $rows, int $firstLine): void
    {
        if ($rows->isEmpty()) {
            return;
        }

        $headers = array_map($this->normalizeKey(...), array_keys($rows->first()->all()));

        if (! $this->headersValidated) {
            $this->ensureRequiredHeaders($headers);
            $this->headersValidated = true;
        }

        $hasNisColumn = in_array('nis', $headers, true);
        $hasNisnColumn = in_array('nisn', $headers, true);
        $preparedRows = [];

        foreach ($rows as $index => $row) {
            $this->processedRows++;

            if ($this->processedRows > self::MAX_IMPORT_ROWS) {
                throw new RuntimeException('File import melebihi batas 10.000 baris data.');
            }

            $line = $firstLine + $index;
            $data = $this->mapRow($row->all());

            if ($this->isEmptyRow($data)) {
                $this->summary['skipped']++;

                continue;
            }

            $kelas = $this->kelasByName->get($this->normalizeKey($data['kelas'] ?? ''));

            if (! $kelas || $kelas->isEmpty()) {
                $this->addError("Baris {$line}: kelas '{$data['kelas']}' tidak ditemukan.");

                continue;
            }

            if ($kelas->count() > 1) {
                $this->addError("Baris {$line}: kelas '{$data['kelas']}' ambigu pada periode aktif.");

                continue;
            }

            if ($this->usesScientificNotation($data['nis']) || $this->usesScientificNotation($data['nisn'])) {
                $this->addError("Baris {$line}: NIS/NISN terdeteksi sebagai notasi ilmiah. Format kolom sebagai Text di Excel.");

                continue;
            }

            $data['jenis_kelamin'] = $this->normalizeGender($data['jenis_kelamin'] ?? '');
            $data['kelas_id'] = $kelas->first()->id;
            $data['periode_id'] = $kelas->first()->periode_id;
            $data['status'] = $this->normalizeKey($data['status'] ?: 'aktif');
            unset($data['kelas']);

            $validator = Validator::make($data, $this->rules());

            if ($validator->fails()) {
                $this->addError("Baris {$line}: ".$validator->errors()->first());

                continue;
            }

            $preparedRows[] = compact('line', 'data');
        }

        if ($preparedRows !== []) {
            DB::transaction(fn () => $this->persist($preparedRows, $hasNisColumn, $hasNisnColumn));
        }
    }

    private function reset(): void
    {
        $this->kelasByName = Kelas::query()
            ->whereHas('periode', fn ($query) => $query->where('status_aktif', true))
            ->get()
            ->groupBy(fn (Kelas $kelas) => $this->normalizeKey($kelas->nama_kelas));
        $this->summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'error_count' => 0, 'errors' => []];
        $this->processedRows = 0;
        $this->headersValidated = false;
    }

    /**
     * @param  array<int, array{line: int, data: array<string, string|int|null>}>  $rows
     */
    private function persist(array $rows, bool $hasNisColumn, bool $hasNisnColumn): void
    {
        $nisValues = array_values(array_unique(array_filter(array_column(array_column($rows, 'data'), 'nis'), fn ($value) => filled($value))));
        $nisnValues = array_values(array_unique(array_filter(array_column(array_column($rows, 'data'), 'nisn'), fn ($value) => filled($value))));
        $existing = Siswa::query()
            ->where(function ($query) use ($nisValues, $nisnValues): void {
                $query->when($nisValues !== [], fn ($query) => $query->whereIn('nis', $nisValues))
                    ->when($nisnValues !== [], fn ($query) => $query->orWhereIn('nisn', $nisnValues));
            })
            ->get();
        $studentsByNis = $existing->filter(fn (Siswa $siswa) => filled($siswa->nis))
            ->keyBy(fn (Siswa $siswa) => $this->identifierKey($siswa->nis));
        $studentsByNisn = $existing->filter(fn (Siswa $siswa) => filled($siswa->nisn))
            ->keyBy(fn (Siswa $siswa) => $this->identifierKey($siswa->nisn));

        foreach ($rows as ['line' => $line, 'data' => $data]) {
            $nisKey = $this->identifierKey($data['nis']);
            $nisnKey = $this->identifierKey($data['nisn']);
            $studentByNis = $nisKey ? $studentsByNis->get($nisKey) : null;
            $studentByNisn = $nisnKey ? $studentsByNisn->get($nisnKey) : null;

            if ($studentByNis && $studentByNisn && ! $studentByNis->is($studentByNisn)) {
                $this->addError("Baris {$line}: NIS dan NISN mengarah ke siswa yang berbeda.");

                continue;
            }

            $student = $studentByNis ?? $studentByNisn;
            $oldNisKey = $student ? $this->identifierKey($student->nis) : null;
            $oldNisnKey = $student ? $this->identifierKey($student->nisn) : null;
            $persistedData = $data;

            if ($student && ! $hasNisColumn) {
                unset($persistedData['nis']);
            }

            if ($student && ! $hasNisnColumn) {
                unset($persistedData['nisn']);
            }

            try {
                $student ? $student->update($persistedData) : $student = Siswa::create($persistedData);
            } catch (QueryException $exception) {
                if (! $this->isDuplicateKey($exception)) {
                    throw $exception;
                }

                $this->addError("Baris {$line}: NIS atau NISN baru saja digunakan oleh data lain.");

                continue;
            }

            $this->summary[$student->wasRecentlyCreated ? 'created' : 'updated']++;

            if ($oldNisKey && $studentsByNis->get($oldNisKey)?->is($student)) {
                $studentsByNis->forget($oldNisKey);
            }

            if ($oldNisnKey && $studentsByNisn->get($oldNisnKey)?->is($student)) {
                $studentsByNisn->forget($oldNisnKey);
            }

            if (filled($student->nis)) {
                $studentsByNis->put($this->identifierKey($student->nis), $student);
            }

            if (filled($student->nisn)) {
                $studentsByNisn->put($this->identifierKey($student->nisn), $student);
            }
        }
    }

    /** @return array<string, array<int, mixed>> */
    private function rules(): array
    {
        return [
            'nis' => ['nullable', 'string', 'max:50', 'required_without:nisn'],
            'nisn' => ['nullable', 'string', 'max:50', 'required_without:nis'],
            'nama_siswa' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', Rule::in(['laki-laki', 'perempuan'])],
            'nama_ayah' => ['required', 'string', 'max:255'],
            'no_whatsapp_ayah' => ['required', 'string', 'max:20'],
            'nama_ibu' => ['required', 'string', 'max:255'],
            'no_whatsapp_ibu' => ['required', 'string', 'max:20'],
            'nama_wali' => ['nullable', 'string', 'max:255'],
            'no_whatsapp_wali' => ['nullable', 'string', 'max:20'],
            'kelas_id' => ['required', 'integer'],
            'periode_id' => ['required', 'integer'],
            'status' => ['required', Rule::in(['aktif', 'nonaktif'])],
        ];
    }

    /** @param array<int, string> $headers */
    private function ensureRequiredHeaders(array $headers): void
    {
        foreach ([
            'NIS atau NISN' => ['nis', 'nisn'],
            'nama siswa' => ['nama siswa', 'nama lengkap siswa', 'nama'],
            'jenis kelamin' => ['jenis kelamin', 'jk'],
            'kelas' => ['kelas'],
            'nama ayah' => ['nama ayah'],
            'nomor WhatsApp ayah' => ['no whatsapp ayah', 'wa ayah'],
            'nama ibu' => ['nama ibu'],
            'nomor WhatsApp ibu' => ['no whatsapp ibu', 'wa ibu'],
        ] as $label => $aliases) {
            if (array_intersect($aliases, $headers) === []) {
                throw new RuntimeException("Kolom wajib '{$label}' tidak ditemukan pada header file.");
            }
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, string|null>
     */
    private function mapRow(array $row): array
    {
        $data = array_fill_keys([
            'nis', 'nisn', 'nama_siswa', 'jenis_kelamin', 'kelas', 'nama_ayah',
            'no_whatsapp_ayah', 'nama_ibu', 'no_whatsapp_ibu', 'nama_wali', 'no_whatsapp_wali',
        ], null) + ['status' => 'aktif'];

        foreach ($row as $header => $value) {
            $field = self::HEADER_ALIASES[$this->normalizeKey((string) $header)] ?? null;

            if ($field) {
                $data[$field] = $this->cleanValue($value);
            }
        }

        return $data;
    }

    /** @param array<string, string|null> $data */
    private function isEmptyRow(array $data): bool
    {
        return ! collect($data)->contains(fn (?string $value) => filled($value) && $value !== 'aktif');
    }

    private function cleanValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeKey(string $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim(str_replace(['-', '_'], ' ', $value))));
    }

    private function normalizeGender(string $value): ?string
    {
        return match ($value = $this->normalizeKey($value)) {
            'l', 'lk', 'laki laki', 'laki' => 'laki-laki',
            'p', 'pr', 'perempuan' => 'perempuan',
            default => $value ?: null,
        };
    }

    private function usesScientificNotation(?string $value): bool
    {
        return $value !== null && preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)[eE][+-]?\d+$/', $value) === 1;
    }

    private function identifierKey(?string $value): ?string
    {
        return $value === null ? null : mb_strtolower($value);
    }

    private function addError(string $message): void
    {
        $this->summary['error_count']++;

        if (count($this->summary['errors']) < self::MAX_ERROR_DETAILS) {
            $this->summary['errors'][] = $message;
        }
    }

    private function isDuplicateKey(QueryException $exception): bool
    {
        return in_array($exception->errorInfo[0] ?? null, ['23000', '23505'], true);
    }
}
