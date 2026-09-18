<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    /**
     * Tampilkan riwayat aktivitas pengguna dengan filter dan pagination.
     *
     * Log dimuat bersama nama pengguna yang melakukan aktivitas, kemudian
     * diurutkan dari aktivitas terbaru agar perubahan terakhir mudah ditelusuri.
     */
    public function index(Request $request): View
    {
        // Validasi filter agar hanya jenis model dan aktivitas yang didukung yang diproses.
        $filters = $request->validate([
            'model_type' => ['nullable', 'string', 'in:Siswa,Guru,Kelas,Periode'],
            'activity_type' => ['nullable', 'string', 'in:create,update,delete'],
        ]);

        // Ambil nama user melalui eager loading agar tidak terjadi query berulang saat view dirender.
        $logs = ActivityLog::query()
            ->with('user:id,nama')
            // Terapkan filter model hanya jika pengguna mengirimkannya.
            ->when($filters['model_type'] ?? null, fn($query, $type) => $query->where('model_type', $type))
            // Terapkan filter jenis aktivitas hanya jika pengguna mengirimkannya.
            ->when($filters['activity_type'] ?? null, fn($query, $type) => $query->where('activity_type', $type))
            // Aktivitas terbaru harus muncul di bagian paling atas.
            ->latest()
            // Batasi hasil per halaman agar daftar log tetap ringan.
            ->paginate(20)
            // Pertahankan filter saat pengguna berpindah halaman.
            ->withQueryString();

        // Kirim hasil query ke halaman riwayat aktivitas.
        return view('activity-log.index', compact('logs'));
    }
}
