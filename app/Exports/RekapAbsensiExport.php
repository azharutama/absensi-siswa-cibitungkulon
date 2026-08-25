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
     * @param  array<int, array{nama_siswa: string, nama_kelas: string, hadir: int, sakit: int, izin: int, alpa: int, total_tidak_masuk: int, persentase: float|int}>  $rekapSiswa
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
        $totalTidakMasuk = 0;

        foreach ($this->rekapSiswa as $rekap) {
            $totalHadir += $rekap['hadir'];
            $totalSakit += $rekap['sakit'];
            $totalIzin += $rekap['izin'];
            $totalAlpa += $rekap['alpa'];
            $totalTidakMasuk += $rekap['total_tidak_masuk'];
        }

        $jumlahSiswa = count($this->rekapSiswa);
        $totalHariKerjaKelas = $this->totalHariAktif * $jumlahSiswa;

        $persentaseHadir = $totalHariKerjaKelas > 0 ? min(round(($totalHadir / $totalHariKerjaKelas) * 100, 1), 100) : 0;
        $persentaseSakit = $totalHariKerjaKelas > 0 ? min(round(($totalSakit / $totalHariKerjaKelas) * 100, 1), 100) : 0;
        $persentaseIzin = $totalHariKerjaKelas > 0 ? min(round(($totalIzin / $totalHariKerjaKelas) * 100, 1), 100) : 0;
        $persentaseAlpa = $totalHariKerjaKelas > 0 ? min(round(($totalAlpa / $totalHariKerjaKelas) * 100, 1), 100) : 0;

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
            'H' => 16,
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

        $sheet->mergeCells('A1:H1');
        $sheet->getStyle("A5:H{$persentaseRow}")
            ->getBorders()
            ->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN);
        $sheet->getStyle("A5:H{$persentaseRow}")
            ->getAlignment()
            ->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("A5:A{$persentaseRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("D5:H{$persentaseRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Style total row: apply blue fill to all columns except G (Alpa)
        $sheet->getStyle("A{$totalRow}:F{$totalRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EFF6FF');
        $sheet->getStyle("H{$totalRow}:H{$totalRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('EFF6FF');
        $sheet->getStyle("A{$totalRow}:H{$totalRow}")->getFont()->setBold(true);
        $sheet->getStyle("A{$totalRow}:H{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Style percentage row
        $sheet->getStyle("A{$persentaseRow}:H{$persentaseRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F3F4F6');
        $sheet->getStyle("A{$persentaseRow}:H{$persentaseRow}")->getFont()->setBold(true)->setItalic(true);
        $sheet->getStyle("A{$persentaseRow}:H{$persentaseRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

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
