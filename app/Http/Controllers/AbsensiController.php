<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AutoSelectsSingleKelas;
use App\Jobs\SendAlpaWhatsappBatchJob;
use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Kelas;
use App\Models\Periode;
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
    use AutoSelectsSingleKelas;

    public function create(Request $request): View|RedirectResponse
    {
        $filters = $request->validate([
            'tanggal' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $tanggalInput = $filters['tanggal'] ?? today()->format('d/m/Y');
        $tanggal = $this->parseDate($tanggalInput)->format('Y-m-d');
        $tanggalDisplay = $this->parseDate($tanggalInput)->format('d/m/Y');

        $user = $request->user();
        $userKelas = $user->kelas; // HasOne relasi ke Kelas

        // Jika guru, otomatis pakai kelas yang diampu
        if ($user->role === 'guru') {
            if (! $userKelas) {
                return view('absensi.create', [
                    'kelas' => collect(),
                    'siswas' => [],
                    'absensiSiswa' => [],
                    'kelas' => collect(),
                    'siswas' => [],
                    'absensiSiswa' => [],
                    'kelasId' => null,
                    'tanggal' => $tanggalDisplay,
                    'stats' => ['total' => 0, 'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0],
                    'isLocked' => false,
                    'holidayMessage' => null,
                    'periodeWarning' => 'Anda belum ditugaskan ke kelas manapun. Silakan hubungi operator.',
                ]);
            }
            $kelasId = $userKelas->id;
            $kelas = collect([$userKelas]);
        } else {
            // Operator/Kepala Sekolah - bisa pilih kelas
            $kelas = Kelas::query()
                ->accessibleBy($user)
                ->select(['id', 'nama_kelas'])
                ->orderBy('nama_kelas')
                ->get();

            $kelasId = $this->getKelasIdWithAutoSelect($request->get('kelas_id'), $kelas);
        }

        if ($user->role !== 'guru') {
            if ($redirect = $this->autoRedirectForSingleKelas($request, $kelas, 'absensi.create', ['tanggal' => $tanggal])) {
                return $redirect;
            }
        }

        // Cek apakah ada periode aktif
        $activePeriode = Periode::query()
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->first();

        $periodeWarning = null;
        if (! $activePeriode) {
            $periodeWarning = 'Periode akademik belum dikonfigurasi. Silakan hubungi operator untuk menambahkan periode terlebih dahulu sebelum dapat melakukan input absensi.';
        }

        $siswas = [];
        $absensiSiswa = [];
        $isLocked = false;
        $holidayMessage = null;
        $stats = ['total' => 0, 'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];

        if ($kelasId && $activePeriode) {
            $kelasModel = $user->role === 'guru' 
                ? $userKelas 
                : Kelas::query()->accessibleBy($user)->findOrFail($kelasId);

            $holidayMessage = $this->attendanceDateError($activePeriode, $tanggal);
            $holiday = $holidayMessage === null
                ? $this->findHariLibur($activePeriode->id, $tanggal)
                : null;

            if ($holiday) {
                $holidayMessage = $this->formatHariLiburMessage($holiday, $tanggal);
            }

            $siswas = Siswa::query()
                ->select(['id', 'nama_siswa', 'nisn'])
                ->where('kelas_id', $kelasId)
                ->orderBy('nama_siswa')
                ->get();
            $siswaIds = $siswas->pluck('id');
            $stats['total'] = $siswas->count();

            $absensiSiswa = Absensi::where('tanggal', $tanggal)
                ->whereIn('siswa_id', $siswaIds)
                ->pluck('status', 'siswa_id')
                ->toArray();

            if (! empty($absensiSiswa)) {
                $isLocked = true;
            }

            if ($isLocked) {
                foreach ($siswas as $s) {
                    $status = strtolower($absensiSiswa[$s->id] ?? 'hadir');
                    $stats[$status]++;
                }
            } else {
                $stats['hadir'] = $siswas->count();
            }
        }

        return view('absensi.create', compact('kelas', 'siswas', 'absensiSiswa', 'kelasId', 'tanggal', 'stats', 'isLocked', 'holidayMessage', 'periodeWarning'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        
        // Validasi berbeda untuk guru vs operator/kepala_sekolah
        if ($user->role === 'guru') {
            $userKelas = $user->kelas;
            if (! $userKelas) {
                return redirect()->route('absensi.create')
                    ->with('error', 'Anda belum ditugaskan ke kelas manapun.');
            }
            $kelasId = $userKelas->id;
            
            $data = $request->validate([
                'tanggal' => ['required', 'date', 'before_or_equal:today'],
                'absensi' => ['required', 'array'],
                'absensi.*' => ['required', 'in:hadir,izin,sakit,alpa'],
            ]);
            $tanggal = $this->parseDate($data['tanggal'])->format('Y-m-d');
        } else {
            $data = $request->validate([
                'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
                'tanggal' => ['required', 'date', 'before_or_equal:today'],
                'absensi' => ['required', 'array'],
                'absensi.*' => ['required', 'in:hadir,izin,sakit,alpa'],
            ]);
            $kelasId = (int) $data['kelas_id'];
            $tanggal = $this->parseDate($data['tanggal'])->format('Y-m-d');
        }

        $userId = $user->getKey();

        $activePeriode = $this->activePeriodeOrFail($tanggal);
        
        // Validasi akses ke kelas
        if ($user->role === 'guru') {
            $kelas = $user->kelas;
            if ($kelas->id !== $kelasId) {
                abort(403, 'Anda tidak memiliki akses ke kelas ini.');
            }
        } else {
            $kelas = Kelas::query()
                ->accessibleBy($user)
                ->findOrFail($kelasId);
        }

        if ($dateError = $this->attendanceDateError($activePeriode, $tanggal)) {
            return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', $dateError);
        }

        $holiday = $this->findHariLibur($activePeriode->id, $tanggal);
        if ($holiday) {
            return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', $this->formatHariLiburMessage($holiday, $tanggal));
        }

        $siswaIds = Siswa::query()
            ->where('kelas_id', $kelasId)
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
                'periode_id' => $activePeriode->id,
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

        $this->queueAlpaNotificationsFor($alpaSiswaIds, $tanggal);

        return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
            ->with('success', 'Data absensi baru berhasil disimpan.');
    }

    public function edit(Request $request): View|RedirectResponse
    {
        $filters = $request->validate([
            'tanggal' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        $user = $request->user();
        $userKelas = $user->kelas;

        if ($user->role === 'guru') {
            if (! $userKelas) {
                $tanggalDisplay = $filters['tanggal'] ?? today()->format('d/m/Y');
                return view('absensi.edit', [
                    'kelas' => collect(),
                    'siswas' => [],
                    'absensiSiswa' => [],
                    'kelasId' => null,
                    'tanggal' => $tanggalDisplay,
                    'stats' => ['total' => 0, 'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0],
                    'isLocked' => false,
                    'holidayMessage' => null,
                    'periodeWarning' => 'Anda belum ditugaskan ke kelas manapun. Silakan hubungi operator.',
                ]);
            }
            $kelasId = $userKelas->id;
            $kelas = collect([$userKelas]);
        } else {
            $kelas = Kelas::query()
                ->accessibleBy($user)
                ->select(['id', 'nama_kelas'])
                ->orderBy('nama_kelas')
                ->get();

            $kelasId = $this->getKelasIdWithAutoSelect($request->get('kelas_id'), $kelas);
        }

        $tanggalInput = $filters['tanggal'] ?? today()->format('d/m/Y');
        $tanggal = $this->parseDate($tanggalInput)->format('Y-m-d');

        if ($user->role !== 'guru') {
            if ($redirect = $this->autoRedirectForSingleKelas($request, $kelas, 'absensi.edit', ['tanggal' => $tanggal])) {
                return $redirect;
            }
        }

        $activePeriode = Periode::query()
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->first();

        $periodeWarning = null;
        if (! $activePeriode) {
            $periodeWarning = 'Periode akademik belum dikonfigurasi. Silakan hubungi operator untuk menambahkan periode terlebih dahulu sebelum dapat melakukan edit absensi.';
        }

        $siswas = [];
        $absensiSiswa = [];
        $isLocked = false;
        $holidayMessage = null;
        $stats = ['total' => 0, 'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];

        if ($kelasId && $activePeriode) {
            $kelasModel = $user->role === 'guru'
                ? $userKelas
                : Kelas::query()->accessibleBy($user)->findOrFail($kelasId);

            $holidayMessage = $this->attendanceDateError($activePeriode, $tanggal);
            $holiday = $holidayMessage === null
                ? $this->findHariLibur($activePeriode->id, $tanggal)
                : null;

            if ($holiday) {
                $holidayMessage = $this->formatHariLiburMessage($holiday, $tanggal);
            }

            $siswas = Siswa::query()
                ->select(['id', 'nama_siswa', 'nisn'])
                ->where('kelas_id', $kelasId)
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

        return view('absensi.edit', compact('kelas', 'siswas', 'absensiSiswa', 'kelasId', 'tanggal', 'stats', 'isLocked', 'holidayMessage', 'periodeWarning'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user->role === 'guru') {
            $userKelas = $user->kelas;
            if (! $userKelas) {
                return redirect()->route('absensi.edit')
                    ->with('error', 'Anda belum ditugaskan ke kelas manapun.');
            }
            $kelasId = $userKelas->id;

            $data = $request->validate([
                'tanggal' => ['required', 'date', 'before_or_equal:today'],
                'absensi' => ['required', 'array'],
                'absensi.*' => ['required', 'in:hadir,izin,sakit,alpa'],
            ]);
            $tanggal = $this->parseDate($data['tanggal'])->format('Y-m-d');
        } else {
            $data = $request->validate([
                'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
                'tanggal' => ['required', 'date', 'before_or_equal:today'],
                'absensi' => ['required', 'array'],
                'absensi.*' => ['required', 'in:hadir,izin,sakit,alpa'],
            ]);
            $kelasId = (int) $data['kelas_id'];
            $tanggal = $this->parseDate($data['tanggal'])->format('Y-m-d');
        }

        $userId = $user->getKey();

        $activePeriode = $this->activePeriodeOrFail($tanggal);

        if ($user->role === 'guru') {
            $kelas = $user->kelas;
            if ($kelas->id !== $kelasId) {
                abort(403, 'Anda tidak memiliki akses ke kelas ini.');
            }
        } else {
            $kelas = Kelas::query()
                ->accessibleBy($user)
                ->findOrFail($kelasId);
        }

        if ($dateError = $this->attendanceDateError($activePeriode, $tanggal)) {
            return redirect()->route('absensi.edit', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', $dateError);
        }

        $holiday = $this->findHariLibur($activePeriode->id, $tanggal);
        if ($holiday) {
            return redirect()->route('absensi.edit', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', $this->formatHariLiburMessage($holiday, $tanggal));
        }

        $siswaIds = Siswa::query()
            ->where('kelas_id', $kelasId)
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
                'periode_id' => $activePeriode->id,
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
            return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('success', 'Tidak ada perubahan data absensi.');
        }

        DB::transaction(function () use ($upsertRows): void {
            Absensi::upsert(
                $upsertRows,
                ['siswa_id', 'tanggal'],
                ['kelas_id', 'user_id', 'periode_id', 'status', 'updated_at']
            );
        });

        $this->queueAlpaNotificationsFor($alpaSiswaIds, $tanggal);

        return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
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

    private function activePeriodeOrFail(string $tanggal): Periode
    {
        $periode = Periode::query()
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->first();

        if (! $periode) {
            abort(404, 'Periode akademik tidak ditemukan untuk tanggal tersebut.');
        }

        return $periode;
    }

    private function attendanceDateError(Periode $periode, string $tanggal): ?string
    {
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

    /**
     * @param  array<int, int>  $siswaIds
     */
    private function queueAlpaNotificationsFor(array $siswaIds, string $tanggal): void
    {
        if ($siswaIds === []) {
            return;
        }

        $notificationIds = Absensi::query()
            ->with(['siswa', 'kelas'])
            ->where('tanggal', $tanggal)
            ->whereIn('siswa_id', array_unique($siswaIds))
            ->where('status', 'alpa')
            ->get()
            ->flatMap(fn (Absensi $absensi) => $this->upsertAlpaWhatsappNotifications($absensi))
            ->filter(fn (?int $id) => $id !== null)
            ->values()
            ->all();

        if ($notificationIds !== []) {
            SendAlpaWhatsappBatchJob::dispatch($notificationIds);
        }
    }

    /**
     * @return array<int, int>
     */
    private function upsertAlpaWhatsappNotifications(Absensi $absensi): array
    {
        $siswa = $absensi->siswa;

        if (! $siswa) {
            return [];
        }

        $contacts = $this->resolveParentContacts($siswa);

        if ($contacts === []) {
            $this->unsentParentNotification($absensi);

            return [];
        }

        $primary = $contacts[0];
        $fallback = $contacts[1] ?? null;

        $notification = WhatsappNotification::query()
            ->firstOrNew([
                'absensi_id' => $absensi->id,
                'provider' => 'fonnte',
                'parent_phone' => $primary[1],
            ]);

        if ($notification->status === 'sent') {
            return [(int) $notification->id];
        }

        $message = $this->buildAlpaWhatsappMessage($absensi, $primary[0]);

        if ($fallback) {
            $message .= "\n\n[Fallback: {$fallback[0]} - {$fallback[1]}]";
        }

        $notification->fill([
            'siswa_id' => $absensi->siswa_id,
            'parent_name' => $primary[0],
            'parent_phone' => $primary[1],
            'message' => $message,
            'status' => 'pending',
            'last_error' => null,
            'sent_at' => null,
        ])->save();

        return [(int) $notification->id];
    }

    private function unsentParentNotification(Absensi $absensi): void
    {
        $siswa = $absensi->siswa;

        $notification = WhatsappNotification::query()
            ->firstOrNew([
                'absensi_id' => $absensi->id,
                'provider' => 'fonnte',
                'parent_phone' => null,
            ]);

        if ($notification->status === 'sent') {
            return;
        }

        $notification->fill([
            'siswa_id' => $absensi->siswa_id,
            'parent_name' => null,
            'parent_phone' => null,
            'message' => $this->buildAlpaWhatsappMessage($absensi, null),
            'status' => 'failed',
            'last_error' => 'Nomor WhatsApp orang tua/wali tidak tersedia.',
            'sent_at' => null,
        ])->save();
    }

    /**
     * Mengembalikan daftar kontak orang tua yang unik (berdasarkan nomor
     * WhatsApp yang sudah dinormalisasi), dengan urutan prioritas ibu lalu ayah.
     *
     * @return array<int, array{0: ?string, 1: string}>
     */
    private function resolveParentContacts(Siswa $siswa): array
    {
        $contacts = [];
        $seen = [];

        foreach ([
            [$siswa->nama_ibu, $siswa->no_whatsapp_ibu],
            [$siswa->nama_ayah, $siswa->no_whatsapp_ayah],
        ] as [$name, $phone]) {
            $normalized = $this->normalizeWhatsappNumber($phone);

            if (blank($normalized) || isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $contacts[] = [$name, $normalized];
        }

        return $contacts;
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
