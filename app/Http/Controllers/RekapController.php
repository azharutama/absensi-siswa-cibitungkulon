<?php

namespace App\Http\Controllers;

use App\Exports\RekapAbsensiExport;
use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RekapController extends Controller
{
    public function index(Request $request): View
    {
        return view('rekap.index', $this->rekapData($request));
    }

    /**
     * Download the filtered attendance recap as an Excel workbook.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $data = $this->rekapData($request);

        abort_unless($data['kelasId'], 422, 'Pilih kelas terlebih dahulu sebelum mengunduh rekap.');

        $filename = sprintf(
            'rekap-absensi-%s-%s-sampai-%s.xlsx',
            Str::slug($data['namaKelas']),
            $data['tanggalMulai'],
            $data['tanggalBerakhir'],
        );

        return Excel::download(
            new RekapAbsensiExport(
                $data['rekapSiswa'],
                $data['namaKelas'],
                $data['tanggalMulai'],
                $data['tanggalBerakhir'],
            ),
            $filename,
        );
    }

    /**
     * @return array{kelas: Collection<int, Kelas>, rekapSiswa: array<int, array{nama_siswa: string, nama_kelas: string, hadir: int, sakit: int, izin: int, alpa: int, persentase: float|int}>, kelasId: int|string|null, tanggalMulai: string, tanggalBerakhir: string, stats: array{rata_hadir: float|int, total_sakit: int, total_izin: int, total_alpa: int}, namaKelas: string|null}
     */
    private function rekapData(Request $request): array
    {
        $filters = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'tanggal_mulai' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_berakhir' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
        ]);

        $kelas = Kelas::query()
            ->accessibleBy($request->user())
            ->with('gurus', function ($query): void {
                $query->select(['users.id', 'users.nama']);
            })
            ->select(['id', 'nama_kelas'])
            ->orderBy('nama_kelas')
            ->get();

        $kelasId = $filters['kelas_id'] ?? null;
        $tanggalMulai = $filters['tanggal_mulai'] ?? today()->startOfMonth()->toDateString();
        $tanggalBerakhir = $filters['tanggal_berakhir'] ?? today()->toDateString();

        $rekapSiswa = [];
        $namaKelas = null;
        $stats = [
            'rata_hadir' => 0,
            'total_sakit' => 0,
            'total_izin' => 0,
            'total_alpa' => 0,
        ];

        if ($kelasId) {
            $selectedKelas = $kelas->firstWhere('id', (int) $kelasId);

            abort_if($selectedKelas === null, 404);

            $siswas = Siswa::query()
                ->select(['id', 'nama_siswa'])
                ->where(function ($query) use ($kelasId, $tanggalBerakhir, $tanggalMulai): void {
                    $query->where('kelas_id', $kelasId)
                        ->orWhereHas('absensis', function ($query) use ($kelasId, $tanggalBerakhir, $tanggalMulai): void {
                            $query->where('kelas_id', $kelasId)
                                ->whereBetween('tanggal', [$tanggalMulai, $tanggalBerakhir]);
                        });
                })
                ->orderBy('nama_siswa')
                ->get();

            $absensiTotals = Absensi::query()
                ->select('siswa_id')
                ->selectRaw("SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) AS hadir")
                ->selectRaw("SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) AS sakit")
                ->selectRaw("SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) AS izin")
                ->selectRaw("SUM(CASE WHEN status = 'alpa' THEN 1 ELSE 0 END) AS alpa")
                ->where('kelas_id', $kelasId)
                ->whereBetween('tanggal', [$tanggalMulai, $tanggalBerakhir])
                ->whereIn('siswa_id', $siswas->pluck('id'))
                ->groupBy('siswa_id')
                ->get()
                ->keyBy('siswa_id');

            $totalPersentaseSemuaSiswa = 0;
            $namaKelas = $selectedKelas->nama_kelas;

            foreach ($siswas as $siswa) {
                $totals = $absensiTotals->get($siswa->id);
                $hadir = $totals->hadir ?? 0;
                $sakit = $totals->sakit ?? 0;
                $izin = $totals->izin ?? 0;
                $alpa = $totals->alpa ?? 0;

                $totalHariMasuk = $hadir + $sakit + $izin + $alpa;
                $persentase = $totalHariMasuk > 0 ? round(($hadir / $totalHariMasuk) * 100, 1) : 0;

                $rekapSiswa[] = [
                    'nama_siswa' => $siswa->nama_siswa,
                    'nama_kelas' => $namaKelas,
                    'hadir' => $hadir,
                    'sakit' => $sakit,
                    'izin' => $izin,
                    'alpa' => $alpa,
                    'persentase' => $persentase,
                ];

                // Akumulasi untuk Widget Card Atas
                $stats['total_sakit'] += $sakit;
                $stats['total_izin'] += $izin;
                $stats['total_alpa'] += $alpa;
                $totalPersentaseSemuaSiswa += $persentase;
            }

            // Hitung rata-rata kehadiran kelas keseluruhan
            if ($siswas->count() > 0) {
                $stats['rata_hadir'] = round($totalPersentaseSemuaSiswa / $siswas->count(), 1);
            }
        }

        return compact('kelas', 'rekapSiswa', 'kelasId', 'tanggalMulai', 'tanggalBerakhir', 'stats', 'namaKelas');
    }
}
