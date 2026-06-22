<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('auth.login');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('role:operator')->group(function () {
        Route::get('/siswa', fn() => view('pages.placeholder', ['title' => 'Data Siswa']))->name('siswa.index');
        Route::get('/kelas', fn() => view('pages.placeholder', ['title' => 'Data Kelas']))->name('kelas.index');
        Route::get('/guru', fn() => view('pages.placeholder', ['title' => 'Data Guru']))->name('guru.index');
        Route::get('/periode', fn() => view('pages.placeholder', ['title' => 'Data Periode']))->name('periode.index');
    });

    Route::middleware('role:guru')->group(function () {
        Route::get('/absensi', fn() => view('pages.placeholder', ['title' => 'Absensi']))->name('absensi.index');
    });

    Route::middleware('role:operator,guru,kepala_sekolah')->group(function () {
        Route::get('/rekap', fn() => view('pages.placeholder', ['title' => 'Rekap Absensi']))->name('rekap.index');
    });

    Route::middleware('role:operator,guru')->group(function () {
        Route::get('/notifikasi', fn() => view('pages.placeholder', ['title' => 'Notifikasi WhatsApp']))->name('notifikasi.index');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
