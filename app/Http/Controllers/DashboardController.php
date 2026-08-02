<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Periode;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\Request;

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

        $endingSoonPeriod = Periode::query()
            ->whereDate('tanggal_mulai', '<=', today())
            ->whereDate('tanggal_selesai', '>=', today())
            ->first();

        if ($endingSoonPeriod && ! $endingSoonPeriod->isEndingSoon()) {
            $endingSoonPeriod = null;
        }

        // Kirim data ke view dashboard
        return view('dashboard', compact('totalKelas', 'totalSiswa', 'totalGuru', 'endingSoonPeriod'));
    }
}
