<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\WhatsappNotification;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class NotifikasiController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $filters = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_berakhir' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        $tanggalMulai = ($filters['tanggal_mulai'] ?? null) ? $this->parseDate($filters['tanggal_mulai'])->startOfDay() : null;
        $tanggalBerakhir = ($filters['tanggal_berakhir'] ?? null) ? $this->parseDate($filters['tanggal_berakhir'])->endOfDay() : null;

        $kelas = Kelas::query()
            ->accessibleBy($user)
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
            ->when($user->role === 'guru' || $kelasId !== null, function (Builder $query) use ($user, $kelasIds, $kelasId): void {
                $query->whereHas('absensi', function (Builder $query) use ($user, $kelasIds, $kelasId): void {
                    if ($user->role === 'guru') {
                        $query->whereIn('kelas_id', $kelasIds);
                    }

                    if ($kelasId !== null) {
                        $query->where('kelas_id', $kelasId);
                    }
                });
            })
            ->when($tanggalMulai, function (Builder $query) use ($tanggalMulai): void {
                $query->where('created_at', '>=', $tanggalMulai);
            })
            ->when($tanggalBerakhir, function (Builder $query) use ($tanggalBerakhir): void {
                $query->where('created_at', '<=', $tanggalBerakhir);
            })
            ->latest()
            ->paginate(10)
            ->withQueryString();

        return view('notifikasi.index', compact('kelas', 'notifikasi'));
    }

    /**
     * Parse string tanggal support format d/m/Y dan Y-m-d
     */
    private function parseDate(string $date): Carbon
    {
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
            return Carbon::createFromFormat('d/m/Y', $date);
        }

        return Carbon::parse($date);
    }
}