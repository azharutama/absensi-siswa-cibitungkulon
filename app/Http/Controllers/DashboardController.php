<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Kelas;
use App\Models\Periode;
use App\Models\Siswa;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $totalKelas = Kelas::query()->accessibleBy($user)->count();
        $totalSiswa = Siswa::query()
            ->whereHas('kelas', fn ($query) => $query->accessibleBy($user))
            ->count();
        $totalGuru = in_array($user->role, ['operator', 'kepala_sekolah'], true)
            ? User::query()->where('role', 'guru')->count()
            : 0;

        $kelasBelumAbsen = $this->kelasBelumAbsenHariIni($user);

        // Kirim data ke view dashboard
        return view('dashboard', compact('totalKelas', 'totalSiswa', 'totalGuru', 'kelasBelumAbsen'));
    }

    /**
     * Kelas yang belum memiliki data absensi hari ini, hanya jika hari ini
     * merupakan hari aktif (di dalam periode dan bukan hari libur).
     *
     * @return Collection<int, Kelas>
     */
    private function kelasBelumAbsenHariIni(User $user): Collection
    {
        $tanggal = today()->toDateString();

        $periode = Periode::query()
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->first();

        if (! $periode || $this->isHariLibur($periode, $tanggal)) {
            return collect();
        }

        $kelasIds = Kelas::query()
            ->accessibleBy($user)
            ->where('status', 'aktif')
            ->pluck('id');

        if ($kelasIds->isEmpty()) {
            return collect();
        }

        $kelasDenganAbsensi = Absensi::query()
            ->where('tanggal', $tanggal)
            ->whereIn('kelas_id', $kelasIds)
            ->pluck('kelas_id')
            ->unique();

        return Kelas::query()
            ->whereIn('id', $kelasIds)
            ->whereNotIn('id', $kelasDenganAbsensi)
            ->orderBy('nama_kelas')
            ->get(['id', 'nama_kelas']);
    }

    private function isHariLibur(Periode $periode, string $tanggal): bool
    {
        $namaHari = $this->namaHariIndonesia(Carbon::parse($tanggal)->dayOfWeek);

        return HariLibur::query()
            ->where('periode_id', $periode->id)
            ->where(function ($query) use ($tanggal, $namaHari) {
                $query->whereDate('tanggal', $tanggal)
                    ->orWhere(function ($query) use ($namaHari) {
                        $query->where('tipe', 'mingguan')->where('hari', $namaHari);
                    });
            })
            ->exists();
    }

    private function namaHariIndonesia(int $dayOfWeek): string
    {
        return [
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ][$dayOfWeek];
    }
}
