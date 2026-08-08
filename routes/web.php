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

        // Kelola Guru
        Route::get('/guru', [GuruController::class, 'index'])->name('guru.index');
        Route::get('/guru/create', [GuruController::class, 'create'])->name('guru.create');
        Route::post('/guru', [GuruController::class, 'store'])->name('guru.store');
        Route::get('/guru/{guru}/edit', [GuruController::class, 'edit'])->name('guru.edit');
        Route::put('/guru/{guru}', [GuruController::class, 'update'])->name('guru.update');
        Route::delete('/guru/{guru}', [GuruController::class, 'destroy'])->name('guru.destroy');

        // Kelola Kelas
        Route::get('/kelas', [KelasController::class, 'index'])->name('kelas.index');
        Route::get('/kelas/create', [KelasController::class, 'create'])->name('kelas.create');
        Route::post('/kelas', [KelasController::class, 'store'])->name('kelas.store');
        Route::get('/kelas/{kelas}/edit', [KelasController::class, 'edit'])->name('kelas.edit');
        Route::put('/kelas/{kelas}', [KelasController::class, 'update'])->name('kelas.update');
        Route::delete('/kelas/{kelas}', [KelasController::class, 'destroy'])->name('kelas.destroy');

        // Kelola Periode
        Route::get('/periode', [PeriodeController::class, 'index'])->name('periode.index');
        Route::post('/periode', [PeriodeController::class, 'store'])->name('periode.store');
        Route::get('/periode/{periode}/edit', [PeriodeController::class, 'edit'])->name('periode.edit');
        Route::put('/periode/{periode}', [PeriodeController::class, 'update'])->name('periode.update');
        Route::delete('/periode/{periode}', [PeriodeController::class, 'destroy'])->name('periode.destroy');
        Route::post('/periode/reset', [PeriodeController::class, 'reset'])->name('periode.reset');

        // Kelola Siswa - operasi massal khusus operator
        Route::get('/siswa/ubah-kelas', [SiswaController::class, 'ubahKelasForm'])->name('siswa.ubah-kelas.form');
        Route::post('/siswa/ubah-kelas', [SiswaController::class, 'ubahKelas'])->name('siswa.ubah-kelas');
        Route::get('/siswa/import', [SiswaController::class, 'importForm'])->name('siswa.import.form');
        Route::post('/siswa/import', [SiswaController::class, 'import'])->name('siswa.import');
        Route::get('/siswa/template-import', [SiswaController::class, 'downloadTemplate'])->name('siswa.template-import');
    });

    Route::middleware('role:operator,guru')->group(function () {
        // Kelola Siswa - operator melihat semua, guru hanya kelas yang diajarnya
        Route::get('/siswa', [SiswaController::class, 'index'])->name('siswa.index');
        Route::get('/siswa/create', [SiswaController::class, 'create'])->name('siswa.create');
        Route::post('/siswa', [SiswaController::class, 'store'])->name('siswa.store');
        Route::get('/siswa/{siswa}/edit', [SiswaController::class, 'edit'])->name('siswa.edit');
        Route::put('/siswa/{siswa}', [SiswaController::class, 'update'])->name('siswa.update');
        Route::delete('/siswa/{siswa}', [SiswaController::class, 'destroy'])->name('siswa.destroy');
    });

    Route::middleware('role:guru')->group(function () {
        Route::get('/absensi', [AbsensiController::class, 'create'])->name('absensi.index');
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
