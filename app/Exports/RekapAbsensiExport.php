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
        $totalHadir = 0;
        $totalSakit = 0;
        $totalIzin = 0;
        $totalAlpa = 0;
        $totalHariMasuk = 0;

        foreach ($this->rekapSiswa as $rekap) {
            $totalHadir += $rekap['hadir'];
            $totalSakit += $rekap['sakit'];
            $totalIzin += $rekap['izin'];
            $totalAlpa += $rekap['alpa'];
            $totalHariMasuk += $rekap['total_hari_masuk'];
        }

        $persentaseHadir = $totalHariMasuk > 0 ? round(($totalHadir / $totalHariMasuk) * 100, 1) : 0;
        $persentaseSakit = $totalHariMasuk > 0 ? round(($totalSakit / $totalHariMasuk) * 100, 1) : 0;
        $persentaseIzin = $totalHariMasuk > 0 ? round(($totalIzin / $totalHariMasuk) * 100, 1) : 0;
        $persentaseAlpa = $totalHariMasuk > 0 ? round(($totalAlpa / $totalHariMasuk) * 100, 1) : 0;

        $rows = [
            ['REKAP ABSENSI SISWA'],
            ['Kelas', $this->namaKelas],
            ['Periode', $this->periodeLabel()],
            [],
            ['No', 'Nama Siswa', 'Kelas', 'Hadir', 'Sakit', 'Izin', 'Alpa', 'Total', 'Persentase'],
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

        // Baris Total
        $rows[] = [
            '',
            'TOTAL',
            '',
            $totalHadir,
            $totalSakit,
            $totalIzin,
            $totalAlpa,
            $totalHariMasuk,
            '',
        ];

        // Baris Persentase Total
        $rows[] = [
            '',
            'PERSENTASE (%)',
            '',
            $persentaseHadir.'%',
            $persentaseSakit.'%',
            $persentaseIzin.'%',
            $persentaseAlpa.'%',
            '100%',
            '',
        ];

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
            'H' => 12,
            'I' => 16,
        ];
    }

    /**
     * @return array<int, array<string, array<string, string|int|bool>>>
     */
    public function styles(Worksheet $sheet): array
    {
        $lastDataRow = count($this->rekapSiswa) + 5;
        $totalRow = $lastDataRow + 1;
        $persentaseRow = $lastDataRow + 2;

        $sheet->mergeCells('A1:I1');
        $sheet->getStyle("A5:I{$persentaseRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A5:I{$persentaseRow}")
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A5:A{$persentaseRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D5:I{$persentaseRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

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
            $totalRow => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'EFF6FF']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
            $persentaseRow => [
                'font' => ['bold' => true, 'italic' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'color' => ['rgb' => 'F3F4F6']],
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
