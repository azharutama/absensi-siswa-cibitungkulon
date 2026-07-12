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
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        $filters = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'tanggal' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        $kelas = Kelas::query()
            ->accessibleBy($request->user())
            ->select(['id', 'nama_kelas'])
            ->orderBy('nama_kelas')
            ->get();
        $kelasId = $filters['kelas_id'] ?? null;
        $tanggal = $filters['tanggal'] ?? today()->toDateString();

        $siswas = [];
        $absensiSiswa = [];
        $isLocked = false;
        $holidayMessage = null;
        $stats = ['total' => 0, 'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];

        if ($kelasId) {
            $selectedKelas = Kelas::query()
                ->accessibleBy($request->user())
                ->select(['id', 'periode_id'])
                ->with('periode:id,tanggal_mulai,tanggal_selesai')
                ->findOrFail($kelasId);

            $holidayMessage = $this->attendanceDateError($selectedKelas, $tanggal);
            $holiday = $holidayMessage === null
                ? $this->findHariLibur($selectedKelas->periode_id, $tanggal)
                : null;

            if ($holiday) {
                $holidayMessage = $this->formatHariLiburMessage($holiday, $tanggal);
            }

            $siswas = Siswa::query()
                ->select(['id', 'nama_siswa'])
                ->where('kelas_id', $kelasId)
                ->where('status', 'aktif')
                ->orderBy('nama_siswa')
                ->get();
            $siswaIds = $siswas->pluck('id');
            $stats['total'] = $siswas->count();

            // 1. Ambil seluruh rekam data absensi pada hari tersebut jika sudah ada
            $absensiSiswa = Absensi::where('tanggal', $tanggal)
                ->whereIn('siswa_id', $siswaIds)
                ->pluck('status', 'siswa_id')
                ->toArray();

            // 2. Tentukan status kunci: jika array tidak kosong, otomatis LOCK form isi baru
            if (! empty($absensiSiswa)) {
                $isLocked = true;
            }

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
        $data = $request->validate([
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'absensi' => ['required', 'array'],
            'absensi.*' => ['required', 'in:hadir,izin,sakit,alpa'],
        ]);

        $kelasId = (int) $data['kelas_id'];
        $tanggal = $data['tanggal'];
        $userId = $request->user()->getKey();
        $kelas = Kelas::query()
            ->accessibleBy($request->user())
            ->select(['id', 'periode_id'])
            ->with('periode:id,tanggal_mulai,tanggal_selesai')
            ->findOrFail($kelasId);

        if ($dateError = $this->attendanceDateError($kelas, $tanggal)) {
            return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', $dateError);
        }

        $holiday = $this->findHariLibur($kelas->periode_id, $tanggal);
        if ($holiday) {
            return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', $this->formatHariLiburMessage($holiday, $tanggal));
        }

        $siswaIds = Siswa::query()
            ->where('kelas_id', $kelasId)
            ->where('status', 'aktif')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->ensureCompleteAttendancePayload($data['absensi'], $siswaIds);

        $sudahAbsen = Absensi::query()
            ->where('kelas_id', $kelasId)
            ->where('tanggal', $tanggal)
            ->exists();

        if ($sudahAbsen) {
            return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', 'Data absensi kelas ini pada tanggal tersebut sudah terisi. Gunakan menu Edit Absensi untuk melakukan perubahan.');
        }

        $now = now();
        $rows = [];
        $alpaSiswaIds = [];

        foreach ($siswaIds as $siswaId) {
            $status = $data['absensi'][$siswaId];
            $rows[] = [
                'siswa_id' => $siswaId,
                'kelas_id' => $kelasId,
                'user_id' => $userId,
                'periode_id' => $kelas->periode_id,
                'tanggal' => $tanggal,
                'status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            if ($status === 'alpa') {
                $alpaSiswaIds[] = $siswaId;
            }
        }

        if ($rows === []) {
            return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', 'Tidak ada data siswa yang valid untuk disimpan.');
        }

        try {
            DB::transaction(function () use ($rows): void {
                Absensi::insert($rows);
            });
        } catch (QueryException $exception) {
            if (($exception->errorInfo[0] ?? null) !== '23000') {
                throw $exception;
            }

            return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', 'Absensi pada tanggal tersebut sudah disimpan oleh proses lain. Muat ulang halaman.');
        }

        $this->dispatchAlpaNotificationsFor($alpaSiswaIds, $tanggal);

        return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
            ->with('success', 'Data absensi baru berhasil disimpan.');
    }

    /**
     * Halaman Perbarui / Edit Absensi
     */
    public function edit(Request $request): View
    {
        $filters = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'tanggal' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
        ]);

        $kelas = Kelas::query()
            ->accessibleBy($request->user())
            ->select(['id', 'nama_kelas'])
            ->orderBy('nama_kelas')
            ->get();
        $kelasId = $filters['kelas_id'] ?? null;
        $tanggal = $filters['tanggal'] ?? today()->toDateString();

        $siswas = [];
        $absensiSiswa = [];
        $isLocked = false; // Halaman edit tidak pernah dikunci (selalu bisa diubah)
        $holidayMessage = null;
        $stats = ['total' => 0, 'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];

        if ($kelasId) {
            $selectedKelas = Kelas::query()
                ->accessibleBy($request->user())
                ->select(['id', 'periode_id'])
                ->with('periode:id,tanggal_mulai,tanggal_selesai')
                ->findOrFail($kelasId);

            $holidayMessage = $this->attendanceDateError($selectedKelas, $tanggal);
            $holiday = $holidayMessage === null
                ? $this->findHariLibur($selectedKelas->periode_id, $tanggal)
                : null;

            if ($holiday) {
                $holidayMessage = $this->formatHariLiburMessage($holiday, $tanggal);
            }

            $siswas = Siswa::query()
                ->select(['id', 'nama_siswa'])
                ->where('kelas_id', $kelasId)
                ->where('status', 'aktif')
                ->orderBy('nama_siswa')
                ->get();
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
        $data = $request->validate([
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'tanggal' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'absensi' => ['required', 'array'],
            'absensi.*' => ['required', 'in:hadir,izin,sakit,alpa'],
        ]);

        $kelasId = (int) $data['kelas_id'];
        $tanggal = $data['tanggal'];
        $userId = $request->user()->getKey();
        $kelas = Kelas::query()
            ->accessibleBy($request->user())
            ->select(['id', 'periode_id'])
            ->with('periode:id,tanggal_mulai,tanggal_selesai')
            ->findOrFail($kelasId);

        if ($dateError = $this->attendanceDateError($kelas, $tanggal)) {
            return redirect()->route('absensi.edit', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', $dateError);
        }

        $holiday = $this->findHariLibur($kelas->periode_id, $tanggal);
        if ($holiday) {
            return redirect()->route('absensi.edit', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', $this->formatHariLiburMessage($holiday, $tanggal));
        }

        $siswaIds = Siswa::query()
            ->where('kelas_id', $kelasId)
            ->where('status', 'aktif')
            ->orderBy('id')
            ->pluck('id')
            ->all();

        $this->ensureCompleteAttendancePayload($data['absensi'], $siswaIds);

        $existingAbsensis = Absensi::query()
            ->where('kelas_id', $kelasId)
            ->where('tanggal', $tanggal)
            ->whereIn('siswa_id', $siswaIds)
            ->get()
            ->keyBy('siswa_id');

        $now = now();
        $upsertRows = [];
        $alpaSiswaIds = [];
        $hasChanges = false;

        foreach ($siswaIds as $siswaId) {
            $status = $data['absensi'][$siswaId];
            $existing = $existingAbsensis->get($siswaId);
            $oldStatus = $existing?->status;

            $hasChanges = $hasChanges
                || ! $existing
                || $oldStatus !== $status
                || (int) $existing->user_id !== (int) $userId
                || (int) $existing->kelas_id !== $kelasId;

            $upsertRows[] = [
                'siswa_id' => $siswaId,
                'kelas_id' => $kelasId,
                'user_id' => $userId,
                'periode_id' => $kelas->periode_id,
                'tanggal' => $tanggal,
                'status' => $status,
                'created_at' => $existing?->created_at ?? $now,
                'updated_at' => $now,
            ];

            if ($status === 'alpa' && $oldStatus !== 'alpa') {
                $alpaSiswaIds[] = $siswaId;
            }
        }

        if (! $hasChanges) {
            return redirect()->route('absensi.edit', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('success', 'Tidak ada perubahan data absensi.');
        }

        DB::transaction(function () use ($upsertRows): void {
            Absensi::upsert(
                $upsertRows,
                ['siswa_id', 'tanggal'],
                ['kelas_id', 'user_id', 'periode_id', 'status', 'updated_at']
            );
        });

        $this->dispatchAlpaNotificationsFor($alpaSiswaIds, $tanggal);

        return redirect()->route('absensi.edit', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
            ->with('success', 'Data riwayat absensi berhasil diperbarui.');
    }

    /**
     * @param  array<int|string, string>  $attendance
     * @param  array<int, int>  $expectedStudentIds
     */
    private function ensureCompleteAttendancePayload(array $attendance, array $expectedStudentIds): void
    {
        $submittedStudentIds = [];

        foreach (array_keys($attendance) as $studentId) {
            if (! ctype_digit((string) $studentId) || (int) $studentId < 1) {
                throw ValidationException::withMessages([
                    'absensi' => 'Payload absensi mengandung ID siswa yang tidak valid.',
                ]);
            }

            $submittedStudentIds[] = (int) $studentId;
        }

        sort($submittedStudentIds);
        sort($expectedStudentIds);

        if ($expectedStudentIds === []) {
            throw ValidationException::withMessages([
                'absensi' => 'Kelas ini belum memiliki siswa aktif.',
            ]);
        }

        if ($submittedStudentIds !== $expectedStudentIds) {
            throw ValidationException::withMessages([
                'absensi' => 'Absensi harus memuat seluruh siswa aktif dari kelas yang dipilih.',
            ]);
        }
    }

    private function attendanceDateError(Kelas $kelas, string $tanggal): ?string
    {
        $periode = $kelas->periode;

        if (! $periode) {
            return 'Kelas ini tidak memiliki periode akademik yang valid.';
        }

        $tanggalMulai = $periode->tanggal_mulai->toDateString();
        $tanggalSelesai = $periode->tanggal_selesai->toDateString();

        if ($tanggal < $tanggalMulai || $tanggal > $tanggalSelesai) {
            return "Tanggal absensi harus berada dalam periode {$tanggalMulai} sampai {$tanggalSelesai}.";
        }

        return null;
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
        $absensi->loadMissing(['siswa', 'kelas']);

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

    /**
     * @param  array<int, int>  $siswaIds
     */
    private function dispatchAlpaNotificationsFor(array $siswaIds, string $tanggal): void
    {
        if ($siswaIds === []) {
            return;
        }

        Absensi::query()
            ->with(['siswa', 'kelas'])
            ->where('tanggal', $tanggal)
            ->whereIn('siswa_id', array_unique($siswaIds))
            ->where('status', 'alpa')
            ->get()
            ->each(fn (Absensi $absensi) => $this->dispatchAlpaWhatsappNotification($absensi));
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

        $number = (string) preg_replace('/\D+/', '', $phone);

        if (str_starts_with($number, '0')) {
            return '62'.substr($number, 1);
        }

        if (str_starts_with($number, '8')) {
            return '62'.$number;
        }

        return $number ?: null;
    }

    private function buildAlpaWhatsappMessage(Absensi $absensi, ?string $parentName): string
    {
        $siswa = $absensi->siswa;
        $tanggal = Carbon::parse($absensi->tanggal)->format('d-m-Y');
        $sapaan = $parentName ? "Bapak/Ibu {$parentName}" : 'Bapak/Ibu Orang Tua/Wali';
        $kelas = $absensi->kelas?->nama_kelas ? " kelas {$absensi->kelas->nama_kelas}" : '';

        return "Assalamu'alaikum {$sapaan},\n\n"
            ."Kami informasikan bahwa ananda {$siswa->nama_siswa}{$kelas} tercatat tidak hadir tanpa keterangan (alpa) pada tanggal {$tanggal}.\n\n"
            .'Mohon konfirmasi kepada wali kelas/sekolah. Terima kasih.';
    }
}
