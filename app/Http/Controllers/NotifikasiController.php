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
        $kelas = Kelas::orderBy('nama_kelas')->get();

        $notifikasi = WhatsappNotification::with(['siswa.kelas', 'absensi'])
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
