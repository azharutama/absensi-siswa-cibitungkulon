<?php

namespace App\Http\Controllers;

use App\Jobs\SendAlpaWhatsappNotificationJob;
use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Kelas;
use App\Models\Siswa;
use App\Models\WhatsappNotification;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth; // <-- Pastikan ini sudah di-import

class AbsensiController extends Controller
{
    /**
     * Halaman Utama Menu Absensi (Menampilkan 2 Tombol Utama)
     */
    public function index(): View
    {
        return view('absensi.index');
    }

    /**
     * Halaman Buat/Isi Absensi Baru (Create)
     */
    public function create(Request $request): View
    {
        $kelas = Kelas::orderBy('nama_kelas')->get();
        $kelasId = $request->input('kelas_id');
        $tanggal = $request->input('tanggal', date('Y-m-d'));

        $siswas = [];
        $absensiSiswa = [];
        $isLocked = false;
        $holidayMessage = null;
        $stats = ['total' => 0, 'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];

        if ($kelasId) {
            $selectedKelas = Kelas::find($kelasId);

            if ($selectedKelas) {
                $holiday = $this->findHariLibur($selectedKelas->periode_id, $tanggal);

                if ($holiday) {
                    $holidayMessage = $this->formatHariLiburMessage($holiday, $tanggal);
                }
            }

            // 1. Ambil seluruh rekam data absensi pada hari tersebut jika sudah ada
            $absensiSiswa = Absensi::where('tanggal', $tanggal)
                ->whereHas('siswa', function ($query) use ($kelasId) {
                    $query->where('kelas_id', $kelasId);
                })
                ->pluck('status', 'siswa_id')
                ->toArray();

            // 2. Tentukan status kunci: jika array tidak kosong, otomatis LOCK form isi baru
            if (!empty($absensiSiswa)) {
                $isLocked = true;
            }

            $siswas = Siswa::where('kelas_id', $kelasId)->orderBy('nama_siswa')->get();
            $stats['total'] = $siswas->count();

            // 3. Kalkulasi data statistik untuk widget card atas
            if ($isLocked) {
                foreach ($siswas as $s) {
                    $status = strtolower($absensiSiswa[$s->id] ?? 'hadir');
                    $stats[$status]++;
                }
            } else {
                $stats['hadir'] = $siswas->count(); // Default awal halaman create adalah hadir semua
            }
        }

        return view('absensi.create', compact('kelas', 'siswas', 'absensiSiswa', 'kelasId', 'tanggal', 'stats', 'isLocked', 'holidayMessage'));
    }

    /**
     * Memproses Penyimpanan Data Absensi Baru (Store)
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            'kelas_id' => 'required|exists:kelas,id',
            'tanggal' => 'required|date',
            'absensi' => 'required|array',
            'absensi.*' => 'required|in:hadir,izin,sakit,alpa',
        ]);

        $kelasId = $request->kelas_id;
        $tanggal = $request->tanggal;
        $kelas = Kelas::findOrFail($kelasId);

        $holiday = $this->findHariLibur($kelas->periode_id, $tanggal);
        if ($holiday) {
            return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', $this->formatHariLiburMessage($holiday, $tanggal));
        }

        // Double Security di sisi server sebelum proses insert data
        $sudahAbsen = Absensi::where('tanggal', $tanggal)
            ->whereHas('siswa', function ($query) use ($kelasId) {
                $query->where('kelas_id', $kelasId);
            })->exists();

        if ($sudahAbsen) {
            return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', 'Data absensi kelas ini pada tanggal tersebut sudah terisi. Gunakan menu Edit Absensi untuk melakukan perubahan.');
        }

        foreach ($request->absensi as $siswaId => $status) {
            $absensi = Absensi::create([
                'siswa_id' => $siswaId,
                'user_id' => Auth::id(), // <-- Sudah menggunakan Auth::id()
                'periode_id' => $kelas->periode_id,
                'tanggal' => $tanggal,
                'status' => $status,
            ]);

            if ($status === 'alpa') {
                $this->dispatchAlpaWhatsappNotification($absensi);
            }
        }

        return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
            ->with('success', 'Data absensi baru berhasil disimpan.');
    }

    /**
     * Halaman Perbarui / Edit Absensi
     */
    public function edit(Request $request): View
    {
        $kelas = Kelas::orderBy('nama_kelas')->get();
        $kelasId = $request->input('kelas_id');
        $tanggal = $request->input('tanggal', date('Y-m-d'));

        $siswas = [];
        $absensiSiswa = [];
        $isLocked = false; // Halaman edit tidak pernah dikunci (selalu bisa diubah)
        $holidayMessage = null;
        $stats = ['total' => 0, 'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];

        if ($kelasId) {
            $selectedKelas = Kelas::find($kelasId);

            if ($selectedKelas) {
                $holiday = $this->findHariLibur($selectedKelas->periode_id, $tanggal);

                if ($holiday) {
                    $holidayMessage = $this->formatHariLiburMessage($holiday, $tanggal);
                }
            }

            $siswas = Siswa::where('kelas_id', $kelasId)->orderBy('nama_siswa')->get();
            $stats['total'] = $siswas->count();

            $absensiSiswa = Absensi::where('tanggal', $tanggal)
                ->whereIn('siswa_id', $siswas->pluck('id'))
                ->pluck('status', 'siswa_id')
                ->toArray();

            foreach ($siswas as $s) {
                $status = strtolower($absensiSiswa[$s->id] ?? 'hadir');
                $stats[$status]++;
            }
        }

        return view('absensi.edit', compact('kelas', 'siswas', 'absensiSiswa', 'kelasId', 'tanggal', 'stats', 'isLocked', 'holidayMessage'));
    }

    /**
     * Memproses Pembaruan Data Absensi Lama (Update)
     */
    public function update(Request $request): RedirectResponse
    {
        $request->validate([
            'kelas_id' => 'required|exists:kelas,id',
            'tanggal' => 'required|date',
            'absensi' => 'required|array',
            'absensi.*' => 'required|in:hadir,izin,sakit,alpa',
        ]);

        $kelasId = $request->kelas_id;
        $tanggal = $request->tanggal;
        $kelas = Kelas::findOrFail($kelasId);

        $holiday = $this->findHariLibur($kelas->periode_id, $tanggal);
        if ($holiday) {
            return redirect()->route('absensi.edit', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', $this->formatHariLiburMessage($holiday, $tanggal));
        }

        foreach ($request->absensi as $siswaId => $status) {
            $absensi = Absensi::where('siswa_id', $siswaId)
                ->where('tanggal', $tanggal)
                ->first();

            $oldStatus = $absensi?->status;

            $absensi = Absensi::updateOrCreate(
                [
                    'siswa_id' => $siswaId,
                    'tanggal' => $tanggal,
                ],
                [
                    'user_id' => Auth::id(), // <-- Sudah menggunakan Auth::id()
                    'periode_id' => $kelas->periode_id,
                    'status' => $status,
                ]
            );

            if ($status === 'alpa' && $oldStatus !== 'alpa') {
                $this->dispatchAlpaWhatsappNotification($absensi);
            }
        }

        return redirect()->route('absensi.edit', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
            ->with('success', 'Data riwayat absensi berhasil diperbarui.');
    }

    private function findHariLibur(?int $periodeId, string $tanggal): ?HariLibur
    {
        $namaHari = $this->namaHariIndonesia(Carbon::parse($tanggal)->dayOfWeek);

        return HariLibur::where('periode_id', $periodeId)
            ->where(function ($query) use ($tanggal, $namaHari) {
                $query->whereDate('tanggal', $tanggal)
                    ->orWhere(function ($query) use ($namaHari) {
                        $query->where('tipe', 'mingguan')
                            ->where('hari', $namaHari);
                    });
            })
            ->first();
    }

    private function formatHariLiburMessage(HariLibur $hariLibur, string $tanggal): string
    {
        $tanggalFormatted = Carbon::parse($tanggal)->format('d-m-Y');
        $keterangan = $hariLibur->keterangan ?: 'Hari libur';

        return "Tanggal {$tanggalFormatted} termasuk {$keterangan}. Guru tidak dapat melakukan input absensi pada hari libur.";
    }

    private function namaHariIndonesia(int $dayOfWeek): string
    {
        return [
            0 => 'Minggu',
            1 => 'Senin',
            2 => 'Selasa',
            3 => 'Rabu',
            4 => 'Kamis',
            5 => 'Jumat',
            6 => 'Sabtu',
        ][$dayOfWeek];
    }

    private function dispatchAlpaWhatsappNotification(Absensi $absensi): void
    {
        $absensi->loadMissing('siswa.kelas');

        if (! $absensi->siswa) {
            return;
        }

        [$parentName, $parentPhone] = $this->resolveParentContact($absensi->siswa);
        $normalizedPhone = $this->normalizeWhatsappNumber($parentPhone);

        $notification = WhatsappNotification::firstOrCreate(
            [
                'absensi_id' => $absensi->id,
                'provider' => 'fonnte',
            ],
            [
                'siswa_id' => $absensi->siswa_id,
                'parent_name' => $parentName,
                'parent_phone' => $normalizedPhone,
                'message' => $this->buildAlpaWhatsappMessage($absensi, $parentName),
                'status' => 'pending',
            ]
        );

        if (! $notification->wasRecentlyCreated && in_array($notification->status, ['failed', 'cancelled'], true)) {
            $notification->update([
                'parent_name' => $parentName,
                'parent_phone' => $normalizedPhone,
                'message' => $this->buildAlpaWhatsappMessage($absensi, $parentName),
                'status' => 'pending',
                'last_error' => null,
            ]);
        }

        if ($notification->wasRecentlyCreated || $notification->wasChanged('status')) {
            SendAlpaWhatsappNotificationJob::dispatch($notification->id);
        }
    }

    private function resolveParentContact(Siswa $siswa): array
    {
        $contacts = [
            [$siswa->nama_wali, $siswa->no_whatsapp_wali],
            [$siswa->nama_ayah, $siswa->no_whatsapp_ayah],
            [$siswa->nama_ibu, $siswa->no_whatsapp_ibu],
        ];

        foreach ($contacts as [$name, $phone]) {
            if (filled($phone)) {
                return [$name, $phone];
            }
        }

        return [null, null];
    }

    private function normalizeWhatsappNumber(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $number = preg_replace('/\D+/', '', $phone);

        if (str_starts_with($number, '0')) {
            return '62' . substr($number, 1);
        }

        if (str_starts_with($number, '8')) {
            return '62' . $number;
        }

        return $number ?: null;
    }

    private function buildAlpaWhatsappMessage(Absensi $absensi, ?string $parentName): string
    {
        $siswa = $absensi->siswa;
        $tanggal = Carbon::parse($absensi->tanggal)->format('d-m-Y');
        $sapaan = $parentName ? "Bapak/Ibu {$parentName}" : 'Bapak/Ibu Orang Tua/Wali';
        $kelas = $siswa->kelas?->nama_kelas ? " kelas {$siswa->kelas->nama_kelas}" : '';

        return "Assalamu'alaikum {$sapaan},\n\n"
            . "Kami informasikan bahwa ananda {$siswa->nama_siswa}{$kelas} tercatat tidak hadir tanpa keterangan (alpa) pada tanggal {$tanggal}.\n\n"
            . "Mohon konfirmasi kepada wali kelas/sekolah. Terima kasih.";
    }
}
