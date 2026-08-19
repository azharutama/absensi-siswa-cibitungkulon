<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\WhatsappNotification;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_berakhir' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        $tanggalMulai = $filters['tanggal_mulai'] ? $this->parseDate($filters['tanggal_mulai'])->format('Y-m-d') : null;
        $tanggalBerakhir = $filters['tanggal_berakhir'] ? $this->parseDate($filters['tanggal_berakhir'])->format('Y-m-d') : null;

        $kelas = Kelas::query()
            ->accessibleBy($request->user())
            ->select(['id', 'nama_kelas'])
            ->orderBy('nama_kelas')
            ->get();

        $kelasIds = $kelas->pluck('id');
        $kelasId = isset($filters['kelas_id']) ? (int) $filters['kelas_id'] : null;
        if ($kelasId === null && $kelas->count() === 1) {
            $kelasId = $kelas->first()->id;
        }

        abort_if($kelasId !== null && ! $kelasIds->contains($kelasId), 404);

        $notifikasi = WhatsappNotification::query()
            ->select(['id', 'absensi_id', 'siswa_id', 'parent_phone', 'status', 'last_error', 'sent_at', 'updated_at', 'created_at'])
            ->with([
                'siswa:id,nama_siswa,kelas_id',
                'absensi:id,kelas_id',
                'absensi.kelas:id,nama_kelas',
            ])
            ->when($request->user()->role === 'guru', function ($query) use ($kelasIds): void {
                $query->whereHas('absensi', fn ($query) => $query->whereIn('kelas_id', $kelasIds));
            })
            ->when($kelasId !== null, function ($query) use ($kelasId): void {
                $query->whereHas('absensi', fn ($query) => $query->where('kelas_id', $kelasId));
            })
            ->when($tanggalMulai, function ($query) use ($tanggalMulai): void {
                $query->where('created_at', '>=', $tanggalMulai . ' 00:00:00');
            })
            ->when($tanggalBerakhir, function ($query) use ($tanggalBerakhir): void {
                $query->where('created_at', '<=', $tanggalBerakhir . ' 23:59:59');
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('notifikasi.index', compact('kelas', 'notifikasi'));
    }

    /**
     * Parse date string supporting both d/m/Y and Y-m-d formats
     */
    private function parseDate(string $date): Carbon
    {
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
            return Carbon::createFromFormat('d/m/Y', $date);
        }
        return Carbon::parse($date);
    }
}
