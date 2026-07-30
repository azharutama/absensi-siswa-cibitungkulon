<?php

use App\Http\Controllers\AbsensiController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GuruController;
use App\Http\Controllers\KelasController;
use App\Http\Controllers\NotifikasiController;
use App\Http\Controllers\PeriodeController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RekapController;
use App\Http\Controllers\SiswaController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/', function (Request $request) {
    return redirect()->route($request->user() ? 'dashboard' : 'login');
});

Route::middleware(['auth'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Log Aktivitas - bisa diakses semua role yang login
    Route::get('/activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

    Route::middleware('role:operator')->group(function () {

        // Kelola Guru (Otomatis menghasilkan URL: /guru, /guru/create, dll)
        Route::resource('guru', GuruController::class)->except('show');

        // Kelola Siswa (Otomatis menghasilkan URL: /siswa, /siswa/create, dll)
        Route::get('/siswa/ubah-kelas', [SiswaController::class, 'ubahKelasForm'])->name('siswa.ubah-kelas.form');
        Route::post('/siswa/ubah-kelas', [SiswaController::class, 'ubahKelas'])->name('siswa.ubah-kelas');
        Route::get('/siswa/import', [SiswaController::class, 'importForm'])->name('siswa.import.form');
        Route::post('/siswa/import', [SiswaController::class, 'import'])->name('siswa.import');
        Route::get('/siswa/template-import', [SiswaController::class, 'downloadTemplate'])->name('siswa.template-import');
        Route::resource('siswa', SiswaController::class)->except('show');

        Route::resource('kelas', KelasController::class)->except('show');

        Route::resource('periode', PeriodeController::class)->except('show');
    });

    Route::middleware('role:guru')->group(function () {
        Route::get('/absensi', [AbsensiController::class, 'index'])->name('absensi.index');
        Route::get('/absensi/create', [AbsensiController::class, 'create'])->name('absensi.create');
        Route::post('/absensi/store', [AbsensiController::class, 'store'])->name('absensi.store');
        Route::get('/absensi/edit', [AbsensiController::class, 'edit'])->name('absensi.edit');
        Route::put('/absensi/update', [AbsensiController::class, 'update'])->name('absensi.update');
    });

    Route::middleware('role:operator,guru,kepala_sekolah')->group(function () {

        Route::get('/rekap-absensi', [RekapController::class, 'index'])->name('rekap.index');
        Route::get('/rekap-absensi/export', [RekapController::class, 'export'])->name('rekap.export');
    });

    Route::middleware('role:operator,guru')->group(function () {
        Route::get('/notifikasi', [NotifikasiController::class, 'index'])->name('notifikasi.index');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

require __DIR__.'/auth.php';
