<?php

namespace App\Imports;

use App\Services\SiswaImportService;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\RemembersChunkOffset;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class SiswaSpreadsheetImport implements ToCollection, WithChunkReading, WithHeadingRow, WithMultipleSheets
{
    use RemembersChunkOffset;

    public function __construct(private SiswaImportService $service) {}

    /** @param Collection<int, Collection<string, mixed>> $rows */
    public function collection(Collection $rows): void
    {
        $this->service->processRows($rows, $this->getChunkOffset() ?? 2);
    }

    public function chunkSize(): int
    {
        return SiswaImportService::CHUNK_SIZE;
    }

    /** @return array<int, self> */
    public function sheets(): array
    {
        return [0 => $this];
    }
}
