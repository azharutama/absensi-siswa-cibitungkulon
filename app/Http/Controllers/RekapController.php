<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\Absensi;
use Illuminate\Http\Request;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;

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
            // 1. Ambil daftar siswa aktif di kelas tersebut
            $siswas = Siswa::query()
                ->select(['id', 'nama_siswa', 'kelas_id'])
                ->with('kelas:id,nama_kelas')
                ->where('kelas_id', $kelasId)
                ->orderBy('nama_siswa')
                ->get();

            // 2. Ambil data absensi grup berdasarkan siswa_id dan status dalam rentang tanggal
            $absensiData = Absensi::whereBetween('tanggal', [$tanggalMulai, $tanggalBerakhir])
                ->whereIn('siswa_id', $siswas->pluck('id'))
                ->select('siswa_id', 'status', DB::raw('count(*) as total'))
                ->groupBy('siswa_id', 'status')
                ->get()
                ->groupBy('siswa_id');

            $totalPersentaseSemuaSiswa = 0;

            foreach ($siswas as $siswa) {
                // Kelompokkan hitungan per siswa
                $hadir = $absensiData->get($siswa->id)?->where('status', 'hadir')->first()?->total ?? 0;
                $sakit = $absensiData->get($siswa->id)?->where('status', 'sakit')->first()?->total ?? 0;
                $izin = $absensiData->get($siswa->id)?->where('status', 'izin')->first()?->total ?? 0;
                $alpa = $absensiData->get($siswa->id)?->where('status', 'alpa')->first()?->total ?? 0;

                $totalHariMasuk = $hadir + $sakit + $izin + $alpa;
                $persentase = $totalHariMasuk > 0 ? round(($hadir / $totalHariMasuk) * 100, 1) : 0;

                $rekapSiswa[] = [
                    'nama_siswa' => $siswa->nama_siswa,
                    'nama_kelas' => $siswa->kelas->nama_kelas ?? '-',
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
