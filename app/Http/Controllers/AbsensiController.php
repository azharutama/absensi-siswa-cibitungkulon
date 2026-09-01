<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\AutoSelectsSingleKelas;
use App\Jobs\SendAlpaWhatsappBatchJob;
use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Kelas;
use App\Models\Periode;
use App\Models\Siswa;
use App\Models\User;
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
        $date = $this->parseDate($tanggalInput);
        $tanggal = $date->toDateString();
        $tanggalDisplay = $date->format('d/m/Y');

        $user = $request->user();
        $userKelas = $user->kelas; // Relasi HasOne ke model Kelas

        // Guru otomatis menggunakan kelas yang diampu
        if ($user->role === 'guru') {
            if (! $userKelas) {
                return view('absensi.create', [
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
            // Operator/Kepala Sekolah bisa memilih kelas
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

        // Cek apakah ada periode aktif untuk tanggal yang dipilih
        $activePeriode = Periode::query()
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->first();

        $periodeWarning = null;
        $activeDates = [];

        if (! $activePeriode) {
            $periodeWarning = 'Periode akademik belum dikonfigurasi. Silakan hubungi operator untuk menambahkan periode terlebih dahulu sebelum dapat melakukan input absensi.';
        } else {
            // Ambil daftar tanggal aktif (bukan hari libur/akhir pekan) untuk periode ini
            $activeDates = $this->getActiveDatesForPeriode($activePeriode);

            // Validasi: tanggal harus hari sekolah aktif (bukan libur/akhir pekan)
            $today = today()->toDateString();

            if (! in_array($tanggal, $activeDates)) {
                // Cari tanggal aktif terdekat (prioritas: hari ini, lalu hari sebelumnya, lalu hari sesudahnya)
                $nearestDate = $this->findNearestActiveDate($tanggal, $activeDates, $today);

                if ($nearestDate !== $tanggal) {
                    return redirect()->route('absensi.create', array_merge(
                        $request->except('tanggal'),
                        ['tanggal' => $nearestDate]
                    ))->with('warning', "Tanggal {$tanggal} bukan hari aktif sekolah. Otomatis diarahkan ke tanggal aktif terdekat: " . Carbon::parse($nearestDate)->format('d/m/Y'));
                }
            }

            // Tidak boleh memilih tanggal di masa depan
            if ($tanggal > $today) {
                return redirect()->route('absensi.create', array_merge(
                    $request->except('tanggal'),
                    ['tanggal' => $today]
                ))->with('warning', 'Tidak bisa memilih tanggal di masa depan. Otomatis diarahkan ke hari ini: ' . Carbon::parse($today)->format('d/m/Y'));
            }
        }

        $siswas = [];
        $absensiSiswa = [];
        $isLocked = false;
        $holidayMessage = null;
        $stats = ['total' => 0, 'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];

        if ($kelasId && $activePeriode) {
            $this->ensureKelasAccessibleTo($user, (int) $kelasId);

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

        return view('absensi.create', compact('kelas', 'siswas', 'absensiSiswa', 'kelasId', 'tanggal', 'stats', 'isLocked', 'holidayMessage', 'periodeWarning', 'activeDates'));
    }

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Validasi berbeda untuk guru vs operator/kepala sekolah
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

        // Dapatkan periode aktif untuk tanggal ini (atau error 404)
        $activePeriode = $this->activePeriodeOrFail($tanggal);

        // Pastikan user punya akses ke kelas ini
        $this->ensureKelasAccessibleTo($user, $kelasId);

        // Cek apakah tanggal valid untuk absensi (dalam range periode)
        if ($dateError = $this->attendanceDateError($activePeriode, $tanggal)) {
            return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', $dateError);
        }

        // Cek apakah tanggal jatuh pada hari libur
        $holiday = $this->findHariLibur($activePeriode->id, $tanggal);
        if ($holiday) {
            return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', $this->formatHariLiburMessage($holiday, $tanggal));
        }

        // Ambil semua ID siswa di kelas ini
        $siswaIds = Siswa::query()
            ->where('kelas_id', $kelasId)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        // Validasi payload absensi lengkap untuk semua siswa
        $this->ensureCompleteAttendancePayload($data['absensi'], $siswaIds);

        // Cek apakah sudah ada absensi untuk kelas & tanggal ini
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
        $notifSiswaIds = [];

        // Siapkan data untuk insert batch
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
            // Kumpulkan ID siswa yang perlu notifikasi (sakit/izin/alpa)
            if (in_array($status, ['alpa', 'sakit', 'izin'])) {
                $notifSiswaIds[] = $siswaId;
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
            // Handle race condition: duplicate entry (kode error 23000)
            if (($exception->errorInfo[0] ?? null) !== '23000') {
                throw $exception;
            }

            return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', 'Absensi pada tanggal tersebut sudah disimpan oleh proses lain. Muat ulang halaman.');
        }

        // Kirim notifikasi WhatsApp untuk siswa yang sakit/izin/alpa
        $this->queueAbsensiNotificationsFor($notifSiswaIds, $tanggal);

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
                    'activeDates' => [],
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

        // Cari periode aktif untuk tanggal ini
        $activePeriode = Periode::query()
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->first();

        $periodeWarning = null;
        $activeDates = [];

        if (! $activePeriode) {
            $periodeWarning = 'Periode akademik belum dikonfigurasi. Silakan hubungi operator untuk menambahkan periode terlebih dahulu sebelum dapat melakukan edit absensi.';
        } else {
            // Ambil daftar tanggal aktif (bukan hari libur/akhir pekan) untuk periode ini
            $activeDates = $this->getActiveDatesForPeriode($activePeriode);

            // Validasi: tanggal harus hari sekolah aktif (bukan libur/akhir pekan)
            $today = today()->toDateString();

            if (! in_array($tanggal, $activeDates)) {
                // Cari tanggal aktif terdekat
                $nearestDate = $this->findNearestActiveDate($tanggal, $activeDates, $today);

                if ($nearestDate !== $tanggal) {
                    return redirect()->route('absensi.edit', array_merge(
                        $request->except('tanggal'),
                        ['tanggal' => $nearestDate]
                    ))->with('warning', "Tanggal {$tanggal} bukan hari aktif sekolah. Otomatis diarahkan ke tanggal aktif terdekat: " . Carbon::parse($nearestDate)->format('d/m/Y'));
                }
            }

            // Tidak boleh memilih tanggal di masa depan
            if ($tanggal > $today) {
                return redirect()->route('absensi.edit', array_merge(
                    $request->except('tanggal'),
                    ['tanggal' => $today]
                ))->with('warning', 'Tidak bisa memilih tanggal di masa depan. Otomatis diarahkan ke hari ini: ' . Carbon::parse($today)->format('d/m/Y'));
            }
        }

        $siswas = [];
        $absensiSiswa = [];
        $isLocked = false;
        $holidayMessage = null;
        $stats = ['total' => 0, 'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];

        if ($kelasId && $activePeriode) {
            $this->ensureKelasAccessibleTo($user, (int) $kelasId);

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

            // Ambil data absensi existing untuk tanggal ini
            $absensiSiswa = Absensi::where('tanggal', $tanggal)
                ->whereIn('siswa_id', $siswas->pluck('id'))
                ->pluck('status', 'siswa_id')
                ->toArray();

            // Hitung statistik kehadiran
            foreach ($siswas as $s) {
                $status = strtolower($absensiSiswa[$s->id] ?? 'hadir');
                $stats[$status]++;
            }
        }

        return view('absensi.edit', compact('kelas', 'siswas', 'absensiSiswa', 'kelasId', 'tanggal', 'stats', 'isLocked', 'holidayMessage', 'periodeWarning', 'activeDates'));
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

        // Dapatkan periode aktif untuk tanggal ini (atau error 404)
        $activePeriode = $this->activePeriodeOrFail($tanggal);

        // Pastikan user punya akses ke kelas ini
        $this->ensureKelasAccessibleTo($user, $kelasId);

        // Cek apakah tanggal valid untuk absensi (dalam range periode)
        if ($dateError = $this->attendanceDateError($activePeriode, $tanggal)) {
            return redirect()->route('absensi.edit', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', $dateError);
        }

        // Cek apakah tanggal jatuh pada hari libur
        $holiday = $this->findHariLibur($activePeriode->id, $tanggal);
        if ($holiday) {
            return redirect()->route('absensi.edit', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', $this->formatHariLiburMessage($holiday, $tanggal));
        }

        // Ambil semua ID siswa di kelas ini
        $siswaIds = Siswa::query()
            ->where('kelas_id', $kelasId)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        // Validasi payload absensi lengkap untuk semua siswa
        $this->ensureCompleteAttendancePayload($data['absensi'], $siswaIds);

        // Ambil data absensi existing untuk comparison
        $existingAbsensis = Absensi::query()
            ->where('kelas_id', $kelasId)
            ->where('tanggal', $tanggal)
            ->whereIn('siswa_id', $siswaIds)
            ->get()
            ->keyBy('siswa_id');

        $now = now();
        $upsertRows = [];
        $notifSiswaIds = [];
        $notifHadirSiswaIds = [];
        $hasChanges = false;

        // Bandingkan data lama vs baru, siapkan untuk upsert
        foreach ($siswaIds as $siswaId) {
            $status = $data['absensi'][$siswaId];
            $existing = $existingAbsensis->get($siswaId);
            $oldStatus = $existing?->status;

            // Deteksi apakah ada perubahan: status, user_id, atau kelas_id
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

            // Notifikasi jika status berubah ke sakit/izin/alpa
            if (in_array($status, ['alpa', 'sakit', 'izin']) && $oldStatus !== $status) {
                $notifSiswaIds[] = $siswaId;
            }

            // Notifikasi jika status berubah dari alfa/sakit/izin ke hadir (hanya melalui edit)
            if ($status === 'hadir' 
                && $oldStatus !== null 
                && $oldStatus !== 'hadir' 
                && in_array($oldStatus, ['alpa', 'sakit', 'izin'])) {
                $notifHadirSiswaIds[] = $siswaId;
            }
        }

        if (! $hasChanges) {
            return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('success', 'Tidak ada perubahan data absensi.');
        }

        DB::transaction(function () use ($upsertRows): void {
            Absensi::upsert(
                $upsertRows,
                ['siswa_id', 'tanggal'], // Unique key untuk upsert
                ['kelas_id', 'user_id', 'periode_id', 'status', 'updated_at']
            );
        });

        // Kirim notifikasi WhatsApp untuk siswa yang statusnya berubah jadi sakit/izin/alpa
        $this->queueAbsensiNotificationsFor($notifSiswaIds, $tanggal);

        // Kirim notifikasi WhatsApp untuk siswa yang statusnya berubah dari alfa/sakit/izin ke hadir
        $this->queueHadirNotificationsFor($notifHadirSiswaIds, $tanggal);

        return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
            ->with('success', 'Data riwayat absensi berhasil diperbarui.');
    }

    /**
     * Memastikan payload absensi berisi semua siswa aktif kelas
     * 
     * @param  array<int|string, string>  $attendance  Data absensi dari request
     * @param  array<int, int>  $expectedStudentIds  ID siswa yang seharusnya ada
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

    /**
     * Memastikan user memiliki akses ke kelas yang diminta
     * Guru hanya bisa akses kelas yang diampunya
     * Operator/kepala sekolah cek via policy
     */
    private function ensureKelasAccessibleTo(User $user, int $kelasId): void
    {
        if ($user->role === 'guru') {
            if ($user->kelas?->id !== $kelasId) {
                abort(403, 'Anda tidak memiliki akses ke kelas ini.');
            }

            return;
        }

        Kelas::query()
            ->accessibleBy($user)
            ->findOrFail($kelasId);
    }

    /**
     * Cari periode aktif untuk tanggal tertentu, error 404 jika tidak ada
     */
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

    /**
     * Validasi apakah tanggal berada dalam range periode
     * Return error message jika di luar range, null jika valid
     */
    private function attendanceDateError(Periode $periode, string $tanggal): ?string
    {
        $tanggalMulai = $periode->tanggal_mulai->toDateString();
        $tanggalSelesai = $periode->tanggal_selesai->toDateString();

        if ($tanggal < $tanggalMulai || $tanggal > $tanggalSelesai) {
            return "Tanggal absensi harus berada dalam periode {$tanggalMulai} sampai {$tanggalSelesai}.";
        }

        return null;
    }

    /**
     * Cari data hari libur (nasional atau mingguan) untuk tanggal & periode tertentu
     */
    private function findHariLibur(?int $periodeId, string $tanggal): ?HariLibur
    {
        $namaHari = $this->namaHariIndonesia(Carbon::parse($tanggal)->dayOfWeek);

        return HariLibur::where('periode_id', $periodeId)
            ->where(function ($query) use ($tanggal, $namaHari) {
                $query->whereDate('tanggal', $tanggal)      // Libur nasional (tanggal spesifik)
                    ->orWhere(function ($query) use ($namaHari) {
                        $query->where('tipe', 'mingguan')   // Libur mingguan (berulang per hari)
                            ->where('hari', $namaHari);
                    });
            })
            ->first();
    }

    /**
     * Format pesan error untuk hari libur
     */
    private function formatHariLiburMessage(HariLibur $hariLibur, string $tanggal): string
    {
        $tanggalFormatted = Carbon::parse($tanggal)->format('d-m-Y');
        $keterangan = $hariLibur->keterangan ?: 'Hari libur';

        return "Tanggal {$tanggalFormatted} termasuk {$keterangan}. Guru tidak dapat melakukan input absensi pada hari libur.";
    }

    /**
     * Konversi angka hari (0-6) ke nama hari Indonesia
     */
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
     * Ambil daftar tanggal aktif (hari sekolah, bukan libur/akhir pekan) untuk satu periode
     * 
     * @return array<string> Format Y-m-d
     */
    private function getActiveDatesForPeriode(Periode $periode): array
    {
        $mulai = Carbon::parse($periode->tanggal_mulai);
        $akhir = Carbon::parse($periode->tanggal_selesai);

        // Ambil hari libur nasional untuk periode ini
        $hariLiburNasional = HariLibur::where('periode_id', $periode->id)
            ->where('tipe', 'nasional')
            ->whereBetween('tanggal', [$mulai->toDateString(), $akhir->toDateString()])
            ->pluck('tanggal')
            ->map(fn($t) => Carbon::parse($t)->toDateString())
            ->unique()
            ->values();

        // Ambil hari libur mingguan untuk periode ini (misal: Sabtu, Minggu)
        $hariLiburMingguan = HariLibur::where('periode_id', $periode->id)
            ->where('tipe', 'mingguan')
            ->pluck('hari')
            ->unique()
            ->values();

        $namaHari = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];

        $activeDates = [];
        $hari = $mulai->copy();

        // Loop setiap hari dalam range periode
        while ($hari->lte($akhir)) {
            $tanggalStr = $hari->toDateString();
            $namaHariIni = $namaHari[$hari->dayOfWeek];

            // Cek apakah hari ini libur (nasional atau mingguan)
            $isHariLibur = $hariLiburNasional->contains($tanggalStr)
                || $hariLiburMingguan->contains($namaHariIni);

            if (! $isHariLibur) {
                $activeDates[] = $tanggalStr;
            }

            $hari->addDay();
        }

        return $activeDates;
    }

    /**
     * Cari tanggal aktif terdekat dari tanggal target
     * Logika:
     * - Jika target di masa depan: pakai hari ini (jika aktif) atau hari aktif terakhir sebelum hari ini
     * - Jika target di masa lalu: cari tanggal aktif dengan selisih hari terkecil
     */
    private function findNearestActiveDate(string $targetDate, array $activeDates, string $today): string
    {
        if (in_array($targetDate, $activeDates)) {
            return $targetDate;
        }

        $targetCarbon = Carbon::parse($targetDate);
        $todayCarbon = Carbon::parse($today);

        // Jika target di masa depan
        if ($targetCarbon->gt($todayCarbon)) {
            if (in_array($today, $activeDates)) {
                return $today;
            }
            // Cari tanggal aktif terakhir sebelum hari ini
            $pastDates = array_filter($activeDates, fn($d) => $d <= $today);
            return $pastDates ? max($pastDates) : ($activeDates ? $activeDates[0] : $today);
        }

        // Target di masa lalu - cari tanggal aktif dengan selisih terkecil
        $closest = null;
        $minDiff = PHP_INT_MAX;

        foreach ($activeDates as $activeDate) {
            $diff = abs(Carbon::parse($activeDate)->diffInDays($targetCarbon));
            if ($diff < $minDiff) {
                $minDiff = $diff;
                $closest = $activeDate;
            }
        }

        return $closest ?? $targetDate;
    }

    /**
     * Antrekan notifikasi WhatsApp untuk siswa yang sakit/izin/alpa
     * 
     * @param  array<int, int>  $siswaIds
     */
    private function queueAbsensiNotificationsFor(array $siswaIds, string $tanggal): void
    {
        if ($siswaIds === []) {
            return;
        }

        $notificationIds = Absensi::query()
            ->with(['siswa', 'kelas'])
            ->where('tanggal', $tanggal)
            ->whereIn('siswa_id', array_unique($siswaIds))
            ->whereIn('status', ['alpa', 'sakit', 'izin'])
            ->get()
            ->flatMap(fn(Absensi $absensi) => $this->upsertAbsensiWhatsappNotifications($absensi))
            ->filter(fn(?int $id) => $id !== null)
            ->values()
            ->all();

        if ($notificationIds !== []) {
            SendAlpaWhatsappBatchJob::dispatch($notificationIds);
        }
    }

    /**
     * Antrekan notifikasi WhatsApp untuk siswa yang berubah status dari alfa/sakit/izin ke hadir
     * 
     * @param  array<int, int>  $siswaIds
     */
    private function queueHadirNotificationsFor(array $siswaIds, string $tanggal): void
    {
        if ($siswaIds === []) {
            return;
        }

        $notificationIds = Absensi::query()
            ->with(['siswa', 'kelas'])
            ->where('tanggal', $tanggal)
            ->whereIn('siswa_id', array_unique($siswaIds))
            ->where('status', 'hadir')
            ->get()
            ->flatMap(fn(Absensi $absensi) => $this->upsertHadirWhatsappNotifications($absensi))
            ->filter(fn(?int $id) => $id !== null)
            ->values()
            ->all();

        if ($notificationIds !== []) {
            SendAlpaWhatsappBatchJob::dispatch($notificationIds);
        }
    }

    /**
     * Buat/update notifikasi WhatsApp untuk satu data absensi
     * Return array ID notifikasi yang dibuat
     * 
     * @return array<int, int>
     */
    private function upsertAbsensiWhatsappNotifications(Absensi $absensi): array
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

        // Jika sudah terkirim, jangan buat ulang
        if ($notification->status === 'sent') {
            return [(int) $notification->id];
        }

        $message = $this->buildAbsensiWhatsappMessage($absensi, $primary[0]);

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

    /**
     * Buat notifikasi WhatsApp baru untuk siswa yang berubah status ke hadir
     * Notifikasi lama tetap dipertahankan di riwayat
     * 
     * @return array<int, int>
     */
    private function upsertHadirWhatsappNotifications(Absensi $absensi): array
    {
        $siswa = $absensi->siswa;

        if (! $siswa) {
            return [];
        }

        $contacts = $this->resolveParentContacts($siswa);

        if ($contacts === []) {
            return [];
        }

        $primary = $contacts[0];
        $fallback = $contacts[1] ?? null;

        $message = $this->buildHadirWhatsappMessage($absensi, $primary[0]);

        if ($fallback) {
            $message .= "\n\n[Fallback: {$fallback[0]} - {$fallback[1]}]";
        }

        // Buat notifikasi baru tanpa menghapus/update yang lama
        // Riwayat notifikasi lama tetap tersimpan
        $notification = new WhatsappNotification();
        $notification->fill([
            'absensi_id' => $absensi->id,
            'siswa_id' => $absensi->siswa_id,
            'provider' => 'fonnte',
            'parent_name' => $primary[0],
            'parent_phone' => $primary[1],
            'message' => $message,
            'status' => 'pending',
            'last_error' => null,
            'sent_at' => null,
        ])->save();

        return [(int) $notification->id];
    }

    /**
     * Simpan notifikasi gagal karena tidak ada nomor WA orang tua
     */
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
            'message' => $this->buildAbsensiWhatsappMessage($absensi, null),
            'status' => 'failed',
            'last_error' => 'Nomor WhatsApp orang tua/wali tidak tersedia.',
            'sent_at' => null,
        ])->save();
    }

    /**
     * Ambil daftar kontak orang tua yang unik (berdasarkan nomor WA)
     * Prioritas: Ibu dulu, lalu Ayah. Duplikat nomor diabaikan.
     * 
     * @return array<int, array{0: ?string, 1: string}>
     */
    private function resolveParentContacts(Siswa $siswa): array
    {
        $contacts = [];
        $seen = [];

        foreach (
            [
                [$siswa->nama_ibu, $siswa->no_whatsapp_ibu],
                [$siswa->nama_ayah, $siswa->no_whatsapp_ayah],
            ] as [$name, $phone]
        ) {
            $normalized = $this->normalizeWhatsappNumber($phone);

            if (blank($normalized) || isset($seen[$normalized])) {
                continue;
            }

            $seen[$normalized] = true;
            $contacts[] = [$name, $normalized];
        }

        return $contacts;
    }

    /**
     * Normalisasi nomor WA ke format internasional (62xxx)
     * Contoh: 0812... -> 62812..., 812... -> 62812...
     */
    private function normalizeWhatsappNumber(?string $phone): ?string
    {
        if (blank($phone)) {
            return null;
        }

        $number = (string) preg_replace('/\D+/', '', $phone);

        if (str_starts_with($number, '0')) {
            return '62' . substr($number, 1);
        }

        if (str_starts_with($number, '8')) {
            return '62' . $number;
        }

        return $number ?: null;
    }

    /**
     * Bangun pesan WhatsApp untuk notifikasi absensi
     * Format rapi dan formal dengan struktur yang jelas
     */
    private function buildAbsensiWhatsappMessage(Absensi $absensi, ?string $parentName): string
    {
        $siswa = $absensi->siswa;
        $tanggal = Carbon::parse($absensi->tanggal)->format('d/m/Y');
        $hari = $this->namaHariIndonesia(Carbon::parse($absensi->tanggal)->dayOfWeek);
        $sapaan = $parentName ? "Yth. Bapak/Ibu {$parentName}" : 'Yth. Bapak/Ibu Orang Tua/Wali';
        $kelas = $absensi->kelas?->nama_kelas ?? '-';
        $status = strtoupper($absensi->status);
        $namaSekolah = config('app.name', 'SD Cibitung Kulon');

        $keterangan = match (strtolower($absensi->status)) {
            'sakit' => 'Siswa tidak hadir karena sakit.',
            'izin' => 'Siswa tidak hadir karena izin.',
            'alpa' => 'Siswa tidak hadir tanpa keterangan.',
            default => 'Informasi kehadiran siswa.',
        };

        $pesan = "NOTIFIKASI KEHADIRAN SISWA\n";
        $pesan .= "{$namaSekolah}\n\n";
        $pesan .= "{$sapaan},\n\n";
        $pesan .= "Berikut informasi kehadiran putra/putri Anda:\n\n";
        $pesan .= "Nama    : {$siswa->nama_siswa}\n";
        $pesan .= "Kelas   : {$kelas}\n";
        $pesan .= "Hari    : {$hari}, {$tanggal}\n";
        $pesan .= "Status  : {$status}\n\n";
        $pesan .= "{$keterangan}\n\n";
        $pesan .= "Untuk informasi lebih lanjut, silakan hubungi wali kelas.\n\n";
        $pesan .= "Terima kasih.\n";
        $pesan .= "Sistem Informasi Absensi";

        return $pesan;
    }

    /**
     * Bangun pesan WhatsApp untuk notifikasi perubahan status ke hadir
     * Format rapi dan formal dengan struktur yang jelas
     */
    private function buildHadirWhatsappMessage(Absensi $absensi, ?string $parentName): string
    {
        $siswa = $absensi->siswa;
        $tanggal = Carbon::parse($absensi->tanggal)->format('d/m/Y');
        $hari = $this->namaHariIndonesia(Carbon::parse($absensi->tanggal)->dayOfWeek);
        $sapaan = $parentName ? "Yth. Bapak/Ibu {$parentName}" : 'Yth. Bapak/Ibu Orang Tua/Wali';
        $kelas = $absensi->kelas?->nama_kelas ?? '-';
        $namaSekolah = config('app.name', 'SD Cibitung Kulon');

        $pesan = "PEMBARUAN KEHADIRAN SISWA\n";
        $pesan .= "{$namaSekolah}\n\n";
        $pesan .= "{$sapaan},\n\n";
        $pesan .= "Berikut pembaruan kehadiran putra/putri Anda:\n\n";
        $pesan .= "Nama    : {$siswa->nama_siswa}\n";
        $pesan .= "Kelas   : {$kelas}\n";
        $pesan .= "Hari    : {$hari}, {$tanggal}\n";
        $pesan .= "Status  : HADIR (Diperbarui)\n\n";
        $pesan .= "Data kehadiran telah dikoreksi oleh wali kelas.\n";
        $pesan .= "Siswa tercatat hadir pada tanggal tersebut.\n\n";
        $pesan .= "Untuk informasi lebih lanjut, silakan hubungi wali kelas.\n\n";
        $pesan .= "Terima kasih.\n";
        $pesan .= "Sistem Informasi Absensi";

        return $pesan;
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
