<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\WhatsappNotification;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    public function index(Request $request): View
    {
        $kelas = Kelas::query()
            ->select(['id', 'nama_kelas'])
            ->orderBy('nama_kelas')
            ->get();

        $notifikasi = WhatsappNotification::query()
            ->select(['id', 'siswa_id', 'parent_phone', 'status', 'last_error', 'sent_at', 'updated_at', 'created_at'])
            ->with([
                'siswa:id,nama_siswa,kelas_id',
                'siswa.kelas:id,nama_kelas',
            ])
            ->when($request->filled('kelas_id'), function ($query) use ($request) {
                $query->whereHas('siswa', function ($query) use ($request) {
                    $query->where('kelas_id', $request->kelas_id);
                });
            })
            ->when($request->filled('tanggal_mulai'), function ($query) use ($request) {
                $query->whereDate('created_at', '>=', $request->tanggal_mulai);
            })
            ->when($request->filled('tanggal_berakhir'), function ($query) use ($request) {
                $query->whereDate('created_at', '<=', $request->tanggal_berakhir);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('notifikasi.index', compact('kelas', 'notifikasi'));
    }
}
