<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();


        $totalKelas = 0;
        $totalSiswa = 0;
        $totalGuru = 0;

        if ($user->role === 'operator' || $user->role === 'kepala sekolah') {
            // Operator & Kepala 
            $totalKelas = Kelas::count();
            $totalSiswa = Siswa::count();
            $totalGuru  = User::where('role', 'guru')->count();
        } elseif ($user->role === 'guru') {
            // Guru HANYA melihat kelas yang diajar/diwalii
            $totalKelas = Kelas::whereHas('gurus', function ($query) use ($user) {
                $query->where('users.id', $user->id);
            })->count();

            // Guru HANYA melihat total siswa dari kelas-kelas yang dia ajar
            $totalSiswa = Siswa::whereHas('kelas.gurus', function ($query) use ($user) {
                $query->where('users.id', $user->id);
            })->count();
        }

        // Kirim data ke view dashboard
        return view('dashboard', compact('totalKelas', 'totalSiswa', 'totalGuru'));
    }
}
