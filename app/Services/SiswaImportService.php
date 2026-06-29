<?php

namespace App\Services;

use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use RuntimeException;
use SimpleXMLElement;
use ZipArchive;

class SiswaImportService
{
    /**
     * @return array{created: int, updated: int, skipped: int, errors: array<int, string>}
     */
    public function import(UploadedFile $file): array
    {
        $rows = $this->readRows($file);

        if (count($rows) < 2) {
            return [
                'created' => 0,
                'updated' => 0,
                'skipped' => 0,
                'errors' => ['File import tidak memiliki baris data siswa.'],
            ];
        }

        $headers = $this->normalizeHeaders(array_shift($rows));
        $kelasByName = Kelas::query()->get()->keyBy(fn(Kelas $kelas) => $this->normalizeKey($kelas->nama_kelas));
        $summary = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        DB::transaction(function () use ($rows, $headers, $kelasByName, &$summary): void {
            foreach ($rows as $index => $row) {
                $line = $index + 2;
                $data = $this->mapRow($headers, $row);

                if ($this->isEmptyRow($data)) {
                    $summary['skipped']++;
                    continue;
                }

                $kelasName = $this->normalizeKey($data['kelas'] ?? '');
                $kelas = $kelasByName->get($kelasName);

                if (! $kelas) {
                    $summary['errors'][] = "Baris {$line}: kelas '{$data['kelas']}' tidak ditemukan.";
                    continue;
                }

                $data['jenis_kelamin'] = $this->normalizeGender($data['jenis_kelamin'] ?? '');
                $data['kelas_id'] = $kelas->id;
                $data['periode_id'] = $kelas->periode_id;
                $data['status'] = $data['status'] ?: 'aktif';

                unset($data['kelas']);

                $validator = Validator::make($data, [
                    'nis' => ['nullable', 'string', 'max:50'],
                    'nisn' => ['nullable', 'string', 'max:50'],
                    'nama_siswa' => ['required', 'string', 'max:255'],
                    'jenis_kelamin' => ['required', Rule::in(['laki-laki', 'perempuan'])],
                    'nama_ayah' => ['required', 'string', 'max:255'],
                    'no_whatsapp_ayah' => ['required', 'string', 'max:20'],
                    'nama_ibu' => ['required', 'string', 'max:255'],
                    'no_whatsapp_ibu' => ['required', 'string', 'max:20'],
                    'nama_wali' => ['nullable', 'string', 'max:255'],
                    'no_whatsapp_wali' => ['nullable', 'string', 'max:20'],
                    'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
                    'periode_id' => ['required', 'integer', 'exists:periodes,id'],
                    'status' => ['required', 'string', 'max:50'],
                ]);

                if ($validator->fails()) {
                    $summary['errors'][] = "Baris {$line}: " . $validator->errors()->first();
                    continue;
                }

                $existing = $this->findExistingSiswa($data);

                if ($conflict = $this->findUniqueConflict($data, $existing?->id)) {
                    $summary['errors'][] = "Baris {$line}: {$conflict}";
                    continue;
                }

                if ($existing) {
                    $existing->update($data);
                    $summary['updated']++;
                } else {
                    Siswa::create($data);
                    $summary['created']++;
                }
            }
        });

        return $summary;
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function readRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        return match ($extension) {
            'csv' => $this->readCsvRows($file),
            'xlsx' => $this->readXlsxRows($file),
            default => throw new RuntimeException('Format file tidak didukung. Gunakan .xlsx atau .csv.'),
        };
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function readCsvRows(UploadedFile $file): array
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'r');

        if (! $handle) {
            throw new RuntimeException('File CSV tidak dapat dibaca.');
        }

        while (($row = fgetcsv($handle, 0, ',')) !== false) {
            $rows[] = array_map(fn($value) => $this->cleanValue($value), $row);
        }

        fclose($handle);

        return $rows;
    }

    /**
     * @return array<int, array<int, string|null>>
     */
    private function readXlsxRows(UploadedFile $file): array
    {
        $zip = new ZipArchive();

        if ($zip->open($file->getRealPath()) !== true) {
            throw new RuntimeException('File XLSX tidak dapat dibuka.');
        }

        $sharedStrings = $this->readSharedStrings($zip);
        $sheetPath = $this->firstWorksheetPath($zip);
        $sheetXml = $zip->getFromName($sheetPath);
        $zip->close();

        if ($sheetXml === false) {
            throw new RuntimeException('Sheet pertama pada file XLSX tidak dapat dibaca.');
        }

        $sheet = simplexml_load_string($sheetXml);

        if (! $sheet instanceof SimpleXMLElement) {
            throw new RuntimeException('Format XML sheet pada file XLSX tidak valid.');
        }

        $rows = [];

        foreach ($sheet->xpath('//*[local-name()="sheetData"]/*[local-name()="row"]') ?: [] as $row) {
            $values = [];

            foreach ($row->xpath('./*[local-name()="c"]') ?: [] as $cell) {
                $reference = (string) $cell['r'];
                $columnIndex = $this->columnIndex($reference);
                $values[$columnIndex] = $this->cellValue($cell, $sharedStrings);
            }

            if ($values !== []) {
                ksort($values);
                $rows[] = $this->fillMissingColumns($values);
            }
        }

        return $rows;
    }

    /**
     * @return array<int, string>
     */
    private function readSharedStrings(ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $sharedStringsXml = simplexml_load_string($xml);

        if (! $sharedStringsXml instanceof SimpleXMLElement) {
            return [];
        }

        $strings = [];

        foreach ($sharedStringsXml->xpath('//*[local-name()="si"]') ?: [] as $item) {
            $textNodes = $item->xpath('.//*[local-name()="t"]') ?: [];

            if ($textNodes) {
                $strings[] = implode('', array_map(fn($node) => (string) $node, $textNodes));
                continue;
            }

            $strings[] = '';
        }

        return $strings;
    }

    private function firstWorksheetPath(ZipArchive $zip): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relationsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $relationsXml === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbook = simplexml_load_string($workbookXml);
        $relations = simplexml_load_string($relationsXml);

        if (! $workbook instanceof SimpleXMLElement || ! $relations instanceof SimpleXMLElement) {
            return 'xl/worksheets/sheet1.xml';
        }

        $sheets = $workbook->xpath('//*[local-name()="sheet"]');

        if (! $sheets || ! isset($sheets[0])) {
            return 'xl/worksheets/sheet1.xml';
        }

        $relationId = (string) $sheets[0]->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;

        foreach ($relations->xpath('//*[local-name()="Relationship"]') ?: [] as $relationship) {
            if ((string) $relationship['Id'] === $relationId) {
                $target = (string) $relationship['Target'];

                return str_starts_with($target, 'xl/') ? $target : 'xl/' . ltrim($target, '/');
            }
        }

        return 'xl/worksheets/sheet1.xml';
    }

    private function cellValue(SimpleXMLElement $cell, array $sharedStrings): ?string
    {
        $type = (string) $cell['t'];
        $valueNodes = $cell->xpath('./*[local-name()="v"]') ?: [];
        $value = isset($valueNodes[0]) ? (string) $valueNodes[0] : null;

        if ($type === 'inlineStr') {
            $textNodes = $cell->xpath('.//*[local-name()="t"]') ?: [];

            return $this->cleanValue(implode('', array_map(fn($node) => (string) $node, $textNodes)));
        }

        if ($value === null) {
            return null;
        }

        if ($type === 's') {
            return $this->cleanValue($sharedStrings[(int) $value] ?? '');
        }

        return $this->cleanValue($value);
    }

    private function columnIndex(string $reference): int
    {
        $letters = preg_replace('/[^A-Z]/', '', strtoupper($reference));
        $index = 0;

        foreach (str_split($letters) as $letter) {
            $index = ($index * 26) + (ord($letter) - 64);
        }

        return $index - 1;
    }

    /**
     * @param array<int, string|null> $values
     * @return array<int, string|null>
     */
    private function fillMissingColumns(array $values): array
    {
        $max = max(array_keys($values));
        $row = [];

        for ($i = 0; $i <= $max; $i++) {
            $row[] = $values[$i] ?? null;
        }

        return $row;
    }

    /**
     * @param array<int, string|null> $headers
     * @return array<int, string>
     */
    private function normalizeHeaders(array $headers): array
    {
        return array_map(fn($header) => $this->normalizeKey((string) $header), $headers);
    }

    /**
     * @param array<int, string> $headers
     * @param array<int, string|null> $row
     * @return array<string, string|null>
     */
    private function mapRow(array $headers, array $row): array
    {
        $aliases = [
            'nis' => 'nis',
            'nisn' => 'nisn',
            'nama siswa' => 'nama_siswa',
            'nama lengkap siswa' => 'nama_siswa',
            'nama' => 'nama_siswa',
            'jenis kelamin' => 'jenis_kelamin',
            'jenis kelamin' => 'jenis_kelamin',
            'jk' => 'jenis_kelamin',
            'kelas' => 'kelas',
            'nama ayah' => 'nama_ayah',
            'nama ayah' => 'nama_ayah',
            'no whatsapp ayah' => 'no_whatsapp_ayah',
            'no whatsapp ayah' => 'no_whatsapp_ayah',
            'wa ayah' => 'no_whatsapp_ayah',
            'nama ibu' => 'nama_ibu',
            'nama ibu' => 'nama_ibu',
            'no whatsapp ibu' => 'no_whatsapp_ibu',
            'no whatsapp ibu' => 'no_whatsapp_ibu',
            'wa ibu' => 'no_whatsapp_ibu',
            'nama wali' => 'nama_wali',
            'nama wali' => 'nama_wali',
            'no whatsapp wali' => 'no_whatsapp_wali',
            'no whatsapp wali' => 'no_whatsapp_wali',
            'wa wali' => 'no_whatsapp_wali',
            'status' => 'status',
        ];

        $data = [
            'nis' => null,
            'nisn' => null,
            'nama_siswa' => null,
            'jenis_kelamin' => null,
            'kelas' => null,
            'nama_ayah' => null,
            'no_whatsapp_ayah' => null,
            'nama_ibu' => null,
            'no_whatsapp_ibu' => null,
            'nama_wali' => null,
            'no_whatsapp_wali' => null,
            'status' => 'aktif',
        ];

        foreach ($headers as $index => $header) {
            $field = $aliases[$header] ?? null;

            if ($field) {
                $data[$field] = $this->cleanValue($row[$index] ?? null);
            }
        }

        return $data;
    }

    /**
     * @param array<string, string|null> $data
     */
    private function isEmptyRow(array $data): bool
    {
        foreach ($data as $value) {
            if (filled($value) && $value !== 'aktif') {
                return false;
            }
        }

        return true;
    }

    private function cleanValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeKey(string $value): string
    {
        return strtolower(trim(str_replace(['-', '_'], ' ', $value)));
    }

    private function normalizeGender(string $value): ?string
    {
        $value = $this->normalizeKey($value);

        return match ($value) {
            'l', 'lk', 'laki laki', 'laki', 'laki-laki' => 'laki-laki',
            'p', 'pr', 'perempuan' => 'perempuan',
            default => $value ?: null,
        };
    }

    /**
     * @param array<string, mixed> $data
     */
    private function findExistingSiswa(array $data): ?Siswa
    {
        if (blank($data['nisn'] ?? null) && blank($data['nis'] ?? null)) {
            return null;
        }

        return Siswa::query()
            ->where(function ($query) use ($data): void {
                $query->when($data['nisn'] ?? null, fn($query, $nisn) => $query->orWhere('nisn', $nisn))
                    ->when($data['nis'] ?? null, fn($query, $nis) => $query->orWhere('nis', $nis));
            })
            ->first();
    }

    /**
     * @param array<string, mixed> $data
     */
    private function findUniqueConflict(array $data, ?int $ignoreId): ?string
    {
        foreach (['nis' => 'NIS', 'nisn' => 'NISN'] as $field => $label) {
            if (blank($data[$field] ?? null)) {
                continue;
            }

            $exists = Siswa::where($field, $data[$field])
                ->when($ignoreId, fn($query) => $query->whereKeyNot($ignoreId))
                ->exists();

            if ($exists) {
                return "{$label} {$data[$field]} sudah digunakan siswa lain.";
            }
        }

        return null;
    }
}
