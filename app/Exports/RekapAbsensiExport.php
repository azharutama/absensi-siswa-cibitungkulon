<?php

namespace App\Exports;

use Carbon\Carbon;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RekapAbsensiExport implements FromArray, WithColumnWidths, WithStyles, WithTitle
{
    /**
     * @param  array<int, array{nama_siswa: string, nama_kelas: string, hadir: int, sakit: int, izin: int, alpa: int, total_hari_masuk: int, persentase: float|int}>  $rekapSiswa
     */
    public function __construct(
        private array $rekapSiswa,
        private string $namaKelas,
        private string $tanggalMulai,
        private string $tanggalBerakhir,
        private int $totalHariAktif = 0,
    ) {}

    /**
     * @return array<int, array<int, string|int|float>>
     */
    public function array(): array
    {
        $rows = [
            ['REKAP ABSENSI SISWA'],
            ['Kelas', $this->namaKelas],
            ['Periode', $this->periodeLabel()],
            [],
            ['No', 'Nama Siswa', 'Kelas', 'Hadir', 'Sakit', 'Izin', 'Alpa', 'Persentase'],
        ];

        foreach ($this->rekapSiswa as $index => $rekap) {
            $rows[] = [
                $index + 1,
                $rekap['nama_siswa'],
                $rekap['nama_kelas'],
                $rekap['hadir'],
                $rekap['sakit'],
                $rekap['izin'],
                $rekap['alpa'],
                $rekap['total_hari_masuk'],
                $rekap['persentase'].'%',
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, float>
     */
    public function columnWidths(): array
    {
        return [
            'A' => 8,
            'B' => 32,
            'C' => 16,
            'D' => 12,
            'E' => 12,
            'F' => 12,
            'G' => 12,
            'H' => 16,
        ];
    }

    /**
     * @return array<int, array<string, array<string, string|int|bool>>>
     */
    public function styles(Worksheet $sheet): array
    {
        $lastRow = max(5, count($this->rekapSiswa) + 5);

        $sheet->mergeCells('A1:H1');
        $sheet->getStyle("A5:H{$lastRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A5:H{$lastRow}")
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A5:A{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D5:H{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        return [
            1 => [
                'font' => ['bold' => true, 'size' => 14],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            5 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => '2563EB']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }

    public function title(): string
    {
        return 'Rekap Absensi';
    }

    private function periodeLabel(): string
    {
        return Carbon::parse($this->tanggalMulai)->translatedFormat('d F Y')
            .' s.d. '.Carbon::parse($this->tanggalBerakhir)->translatedFormat('d F Y');
    }
}
