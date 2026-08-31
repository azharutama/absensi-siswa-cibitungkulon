<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $totalKelas = Kelas::query()->accessibleBy($user)->count();
        $totalSiswa = Siswa::query()
            ->whereHas('kelas', fn ($query) => $query->accessibleBy($user))
            ->count();
        $totalGuru = in_array($user->role, ['operator', 'kepala_sekolah'], true)
            ? User::query()->where('role', 'guru')->count()
            : 0;

        return view('dashboard', compact('totalKelas', 'totalSiswa', 'totalGuru'));
    }
}