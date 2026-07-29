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

    private const REQUIRED_HEADERS = [
        'NIS atau NISN' => ['nis', 'nisn'],
        'nama siswa' => ['nama siswa', 'nama lengkap siswa', 'nama'],
        'jenis kelamin' => ['jenis kelamin', 'jk'],
        'nama ayah' => ['nama ayah'],
        'nomor WhatsApp ayah' => ['no whatsapp ayah', 'wa ayah'],
        'nama ibu' => ['nama ibu'],
        'nomor WhatsApp ibu' => ['no whatsapp ibu', 'wa ibu'],
    ];

    private const RULES = [
        'nis' => ['nullable', 'string', 'max:50', 'required_without:nisn'],
        'nisn' => ['nullable', 'string', 'max:50', 'required_without:nis'],
        'nama_siswa' => ['required', 'string', 'max:255'],
        'jenis_kelamin' => ['required', 'in:laki-laki,perempuan'],
        'nama_ayah' => ['required', 'string', 'max:255'],
        'no_whatsapp_ayah' => ['required', 'string', 'max:20'],
        'nama_ibu' => ['required', 'string', 'max:255'],
        'no_whatsapp_ibu' => ['required', 'string', 'max:20'],
        'nama_wali' => ['nullable', 'string', 'max:255'],
        'no_whatsapp_wali' => ['nullable', 'string', 'max:20'],
        'kelas_id' => ['required', 'integer'],
        'status' => ['required', 'in:aktif,nonaktif'],
    ];

    private Kelas $kelas;

    /** @var array{created: int, updated: int, skipped: int, error_count: int, errors: array<int, string>} */
    private array $summary;

    private int $processedRows = 0;

    private bool $headersValidated = false;

    /**
     * @return array{created: int, updated: int, skipped: int, error_count: int, errors: array<int, string>}
     */
    public function import(UploadedFile $file, Kelas $kelas): array
    {
        $this->kelas = $kelas;
        $this->summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'error_count' => 0, 'errors' => []];
        $this->processedRows = 0;
        $this->headersValidated = false;

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

        $headers = array_map(fn ($header) => $this->normalizeKey((string) $header), array_keys($rows->first()->all()));

        if (! $this->headersValidated) {
            foreach (self::REQUIRED_HEADERS as $label => $aliases) {
                if (array_intersect($aliases, $headers) === []) {
                    throw new RuntimeException("Kolom wajib '{$label}' tidak ditemukan pada header file.");
                }
            }

            $this->headersValidated = true;
        }

        $preparedRows = [];
        foreach ($rows as $index => $row) {
            $preparedRow = $this->prepareRow($row->all(), $firstLine + $index);

            if ($preparedRow) {
                $preparedRows[] = $preparedRow;
            }
        }

        if ($preparedRows !== []) {
            DB::transaction(fn () => $this->persistRows(
                $preparedRows,
                in_array('nis', $headers, true),
                in_array('nisn', $headers, true),
            ));
        }
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array{line: int, data: array<string, string|int|null>}|null
     */
    private function prepareRow(array $row, int $line): ?array
    {
        if (++$this->processedRows > self::MAX_IMPORT_ROWS) {
            throw new RuntimeException('File import melebihi batas 10.000 baris data.');
        }

        $data = array_fill_keys([
            'nis', 'nisn', 'nama_siswa', 'jenis_kelamin', 'nama_ayah',
            'no_whatsapp_ayah', 'nama_ibu', 'no_whatsapp_ibu', 'nama_wali', 'no_whatsapp_wali',
        ], null) + ['status' => 'aktif'];

        foreach ($row as $header => $value) {
            $field = self::HEADER_ALIASES[$this->normalizeKey((string) $header)] ?? null;

            if ($field) {
                $value = trim((string) $value);
                $data[$field] = $value === '' ? null : $value;
            }
        }

        if (! collect($data)->contains(fn (?string $value) => filled($value) && $value !== 'aktif')) {
            $this->summary['skipped']++;

            return null;
        }

        if (preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)[eE][+-]?\d+$/', $data['nis'] ?? '') === 1
            || preg_match('/^[+-]?(?:\d+(?:\.\d*)?|\.\d+)[eE][+-]?\d+$/', $data['nisn'] ?? '') === 1) {
            $this->addError("Baris {$line}: NIS/NISN terdeteksi sebagai notasi ilmiah. Format kolom sebagai Text di Excel.");

            return null;
        }

        $gender = $this->normalizeKey((string) $data['jenis_kelamin']);
        $data['jenis_kelamin'] = match ($gender) {
            'l', 'lk', 'laki laki', 'laki' => 'laki-laki',
            'p', 'pr', 'perempuan' => 'perempuan',
            default => $gender ?: null,
        };
        $data['kelas_id'] = $this->kelas->id;
        $data['status'] = $this->normalizeKey($data['status'] ?: 'aktif');

        $validator = Validator::make($data, self::RULES);

        if ($validator->fails()) {
            $this->addError("Baris {$line}: ".$validator->errors()->first());

            return null;
        }

        return compact('line', 'data');
    }

    /**
     * @param  array<int, array{line: int, data: array<string, string|int|null>}>  $rows
     */
    private function persistRows(array $rows, bool $hasNisColumn, bool $hasNisnColumn): void
    {
        $identifiers = static fn (array $values): array => array_values(array_unique(array_filter($values, fn ($value) => filled($value))));
        $key = static fn (?string $value): ?string => $value === null ? null : mb_strtolower($value);
        $nisValues = $identifiers(array_column(array_column($rows, 'data'), 'nis'));
        $nisnValues = $identifiers(array_column(array_column($rows, 'data'), 'nisn'));
        $existing = Siswa::query()
            ->where(function ($query) use ($nisValues, $nisnValues): void {
                $query->when($nisValues !== [], fn ($query) => $query->whereIn('nis', $nisValues))
                    ->when($nisnValues !== [], fn ($query) => $query->orWhereIn('nisn', $nisnValues));
            })
            ->get();
        $studentsByNis = $existing->filter(fn (Siswa $siswa) => filled($siswa->nis))
            ->keyBy(fn (Siswa $siswa) => $key($siswa->nis));
        $studentsByNisn = $existing->filter(fn (Siswa $siswa) => filled($siswa->nisn))
            ->keyBy(fn (Siswa $siswa) => $key($siswa->nisn));

        foreach ($rows as ['line' => $line, 'data' => $data]) {
            $nisKey = $key($data['nis']);
            $nisnKey = $key($data['nisn']);
            $studentByNis = $nisKey ? $studentsByNis->get($nisKey) : null;
            $studentByNisn = $nisnKey ? $studentsByNisn->get($nisnKey) : null;

            if ($studentByNis && $studentByNisn && ! $studentByNis->is($studentByNisn)) {
                $this->addError("Baris {$line}: NIS dan NISN mengarah ke siswa yang berbeda.");

                continue;
            }

            $student = $studentByNis ?? $studentByNisn;
            $oldNisKey = $student ? $key($student->nis) : null;
            $oldNisnKey = $student ? $key($student->nisn) : null;
            $data = $student && ! $hasNisColumn ? array_diff_key($data, ['nis' => true]) : $data;
            $data = $student && ! $hasNisnColumn ? array_diff_key($data, ['nisn' => true]) : $data;

            try {
                $student ? $student->update($data) : $student = Siswa::create($data);
            } catch (QueryException $exception) {
                if (! in_array($exception->errorInfo[0] ?? null, ['23000', '23505'], true)) {
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
                $studentsByNis->put($key($student->nis), $student);
            }

            if (filled($student->nisn)) {
                $studentsByNisn->put($key($student->nisn), $student);
            }
        }
    }

    private function normalizeKey(string $value): string
    {
        return mb_strtolower((string) preg_replace('/\s+/u', ' ', trim(str_replace(['-', '_'], ' ', $value))));
    }

    private function addError(string $message): void
    {
        $this->summary['error_count']++;

        if (count($this->summary['errors']) < self::MAX_ERROR_DETAILS) {
            $this->summary['errors'][] = $message;
        }
    }
}
