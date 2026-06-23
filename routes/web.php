<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuruController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\PeriodeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SiswaController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('auth.login');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::middleware('role:operator')->group(function () {

        // Kelola Guru (Otomatis menghasilkan URL: /guru, /guru/create, dll)
        Route::resource('guru', GuruController::class);

        // Kelola Siswa (Otomatis menghasilkan URL: /siswa, /siswa/create, dll)
        Route::resource('siswa', SiswaController::class);

        Route::resource('kelas', KelasController::class);

        Route::resource('periode', PeriodeController::class);
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
