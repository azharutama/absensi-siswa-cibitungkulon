<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Siswa;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;

class RekapController extends Controller
{
    public function index(Request $request): View
    {
        $kelas = Kelas::query()
            ->select(['id', 'nama_kelas'])
            ->orderBy('nama_kelas')
            ->get();

        $kelasId = $request->input('kelas_id');
        // Default rentang tanggal: awal bulan ini sampai hari ini (Tahun 2026)
        $tanggalMulai = $request->input('tanggal_mulai', date('Y-m-01'));
        $tanggalBerakhir = $request->input('tanggal_berakhir', date('Y-m-d'));

        $rekapSiswa = [];
        $stats = [
            'rata_hadir' => 0,
            'total_sakit' => 0,
            'total_izin' => 0,
            'total_alpa' => 0
        ];

        if ($kelasId) {
            $selectedKelas = $kelas->firstWhere('id', (int) $kelasId);

            $siswas = Siswa::query()
                ->select(['id', 'nama_siswa'])
                ->where('kelas_id', $kelasId)
                ->orderBy('nama_siswa')
                ->get();

            $absensiTotals = Siswa::query()
                ->where('kelas_id', $kelasId)
                ->withCount([
                    'absensis as hadir' => fn($query) => $query->where('status', 'hadir')->whereBetween('tanggal', [$tanggalMulai, $tanggalBerakhir]),
                    'absensis as sakit' => fn($query) => $query->where('status', 'sakit')->whereBetween('tanggal', [$tanggalMulai, $tanggalBerakhir]),
                    'absensis as izin' => fn($query) => $query->where('status', 'izin')->whereBetween('tanggal', [$tanggalMulai, $tanggalBerakhir]),
                    'absensis as alpa' => fn($query) => $query->where('status', 'alpa')->whereBetween('tanggal', [$tanggalMulai, $tanggalBerakhir]),
                ])
                ->get(['id'])
                ->keyBy('id');

            $totalPersentaseSemuaSiswa = 0;
            $namaKelas = $selectedKelas?->nama_kelas ?? '-';

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
                    'persentase' => $persentase
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

        return view('rekap.index', compact('kelas', 'rekapSiswa', 'kelasId', 'tanggalMulai', 'tanggalBerakhir', 'stats'));
    }
}
