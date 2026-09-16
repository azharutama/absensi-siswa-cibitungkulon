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

        // Batasi notifikasi berdasarkan kelas yang dapat diakses pengguna.
        $kelasIds = $kelas->pluck('id'); //Ambil semua id dari data kelas
        $kelasId = isset($filters['kelas_id']) ? (int) $filters['kelas_id'] : null; //Kalau kelas_id ada di $filters, masukkan nilainya ke $kelasId. Kalau tidak ada, $kelasId diisi null
        if ($kelasId === null && $kelas->count() === 1) {
            $kelasId = $kelas->first()->id;
        } //Jika user belum memilih kelas DAN user hanya memiliki satu kelas, maka sistem otomatis memilih kelas tersebut.

        abort_if($kelasId !== null && ! $kelasIds->contains($kelasId), 404); //Jika user meminta kelas tertentu, tetapi kelas tersebut tidak termasuk kelas yang boleh dia akses, hentikan request dengan HTTP 404.

        //Mulai mengambil data notifikasi
        $notifikasi = WhatsappNotification::query()
            ->select(['id', 'absensi_id', 'siswa_id', 'parent_phone', 'status', 'last_error', 'sent_at', 'updated_at', 'created_at'])
            ->with([
                'siswa:id,nama_siswa,kelas_id',
                'absensi:id,kelas_id',
                'absensi.kelas:id,nama_kelas',
            ])
            ->when($user->role === 'guru' || $kelasId !== null, function (Builder $query) use ($user, $kelasIds, $kelasId): void { //user adalah guru, atau user memilih kelas.
                $query->whereHas('absensi', function (Builder $query) use ($user, $kelasIds, $kelasId): void { //Jalankan fungsi ini, berikan objek query ke $query, dan izinkan fungsi menggunakan variabel $user, $kelasIds, dan $kelasId dari luar.
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
     * Dengan error handling untuk format yang tidak valid
     */
    private function parseDate(string $date): Carbon
    {
        if (empty($date)) {
            return today();
        }

        // Coba parse format d/m/Y
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
            try {
                return Carbon::createFromFormat('d/m/Y', $date);
            } catch (\Exception $e) {
                // Jika gagal, fallback ke Carbon::parse()
            }
        }

        // Coba parse format Y-m-d atau format lain yang valid
        try {
            return Carbon::parse($date);
        } catch (\Exception $e) {
            // Jika semua gagal, return today sebagai fallback
            return today();
        }
    }
}
