<?php

namespace App\Http\Controllers;

use App\Jobs\SendWhatsappBatchJob;
use App\Models\Absensi;
use App\Models\HariLibur;
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
    /**
     * Tampilkan halaman input absensi baru untuk guru
     * Guru hanya bisa input absensi untuk kelas yang diampunya
     */
    public function create(Request $request): View|RedirectResponse
    {
        // Validasi input tanggal dari query string
        $filters = $request->validate([
            'tanggal' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        // Parse tanggal input atau gunakan hari ini sebagai default
        $tanggalInput = $filters['tanggal'] ?? today()->format('d/m/Y');
        $date = $this->parseDate($tanggalInput);
        $tanggal = $date->toDateString(); // Format Y-m-d untuk query database
        $tanggalDisplay = $date->format('d/m/Y'); // Format d/m/Y untuk tampilan

        // Ambil data user yang sedang login
        $user = $request->user();
        $userKelas = $user->kelas; // Relasi HasOne ke model Kelas

        // Validasi: Hanya guru yang boleh akses halaman input absensi
        abort_unless($user->role === 'guru', 403, 'Akses absensi hanya tersedia untuk guru.');

        // Cek apakah guru sudah ditugaskan ke kelas
        // Jika belum, tampilkan halaman kosong dengan pesan warning
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

        // Ambil ID kelas yang diampu guru
        $kelasId = $userKelas->id;
        $kelas = collect([$userKelas]);

        // Cari periode akademik aktif untuk tanggal yang dipilih
        // Periode mengatur rentang waktu semester/tahun ajaran
        $activePeriode = Periode::query()
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->first();

        // Inisialisasi variabel untuk warning dan daftar tanggal aktif
        $periodeWarning = null;
        $activeDates = [];

        // Validasi periode akademik
        if (! $activePeriode) {
            // Jika tidak ada periode, tampilkan pesan error
            $periodeWarning = 'Periode akademik belum dikonfigurasi. Silakan hubungi operator untuk menambahkan periode terlebih dahulu sebelum dapat melakukan input absensi.';
        } else {
            // Ambil daftar tanggal aktif (hari sekolah, bukan libur/weekend)
            // untuk dropdown calendar di frontend
            $activeDates = $this->getActiveDatesForPeriode($activePeriode);

            // Cek apakah tanggal yang dipilih valid
            $today = today()->toDateString();

            // Tidak boleh memilih tanggal di masa depan
            // Redirect otomatis ke hari ini jika user coba akses tanggal future
            if ($tanggal > $today) {
                return redirect()->route('absensi.create', array_merge(
                    $request->except('tanggal'),
                    ['tanggal' => $today]
                ))->with('warning', 'Tidak bisa memilih tanggal di masa depan. Otomatis diarahkan ke hari ini: ' . Carbon::parse($today)->format('d/m/Y'));
            }
        }

        // Inisialisasi data untuk view
        $siswas = [];
        $absensiSiswa = [];
        $isLocked = false; // Flag apakah absensi sudah diinput (read-only mode)
        $holidayMessage = null; // Pesan jika tanggal adalah hari libur
        $stats = ['total' => 0, 'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];

        // Load data siswa jika kelas dan periode valid
        if ($kelasId && $activePeriode) {
            // Pastikan guru punya akses ke kelas ini
            $this->ensureKelasAccessibleTo($user, (int) $kelasId);

            // Cek apakah tanggal berada di luar range periode
            // (misalnya periode 2024-01-01 s/d 2024-06-30, tapi tanggal 2024-07-01)
            $holidayMessage = $this->attendanceDateError($activePeriode, $tanggal);

            // Jika tanggal valid, cek apakah hari libur (nasional atau mingguan)
            $holiday = $holidayMessage === null
                ? $this->findHariLibur($activePeriode->id, $tanggal)
                : null;

            // Format pesan hari libur jika ditemukan
            if ($holiday) {
                $holidayMessage = $this->formatHariLiburMessage($holiday, $tanggal);
            }

            // Jika tanggal adalah hari libur, jangan load data siswa
            // dan set form sebagai read-only (locked)
            if ($holidayMessage) {
                $isLocked = true;
            } else {
                // Load data siswa dari kelas yang dipilih
                // Hanya ambil kolom yang diperlukan untuk performa optimal
                $siswas = Siswa::query()
                    ->select(['id', 'nama_siswa', 'nisn'])
                    ->where('kelas_id', $kelasId)
                    ->orderBy('nama_siswa') // Urutkan alfabetis
                    ->get();

                // Ambil ID siswa untuk query absensi
                $siswaIds = $siswas->pluck('id');
                $stats['total'] = $siswas->count();

                // Cek apakah absensi untuk tanggal ini sudah diinput sebelumnya
                // Hasilnya: array [siswa_id => status]
                $absensiSiswa = Absensi::where('tanggal', $tanggal)
                    ->whereIn('siswa_id', $siswaIds)
                    ->pluck('status', 'siswa_id')
                    ->toArray();

                // Jika ada data absensi, berarti sudah pernah diinput
                // Set sebagai locked (read-only mode)
                if (! empty($absensiSiswa)) {
                    $isLocked = true;
                }

                // Hitung statistik kehadiran
                if ($isLocked) {
                    // Jika sudah diinput, hitung berdasarkan data real
                    foreach ($siswas as $s) {
                        $status = strtolower($absensiSiswa[$s->id] ?? 'hadir');
                        $stats[$status]++;
                    }
                } else {
                    // Jika belum diinput, default semua hadir
                    $stats['hadir'] = $siswas->count();
                }
            }
        }

        // Return view dengan semua data yang diperlukan
        return view('absensi.create', compact('kelas', 'siswas', 'absensiSiswa', 'kelasId', 'tanggal', 'stats', 'isLocked', 'holidayMessage', 'periodeWarning', 'activeDates'));
    }

    /**
     * Simpan data absensi baru ke database
     * Hanya guru yang bisa menyimpan absensi untuk kelas yang diampunya
     */
    public function store(Request $request): RedirectResponse
    {
        // Ambil data user yang sedang login
        $user = $request->user();

        // Validasi: Hanya guru yang boleh menyimpan absensi
        abort_unless($user->role === 'guru', 403, 'Akses absensi hanya tersedia untuk guru.');

        // Ambil kelas yang diampu guru
        $userKelas = $user->kelas;

        // Validasi: Guru harus sudah ditugaskan ke kelas
        if (! $userKelas) {
            return redirect()->route('absensi.create')
                ->with('error', 'Anda belum ditugaskan ke kelas manapun.');
        }

        $kelasId = $userKelas->id;

        // Validasi input dari form
        // absensi.* artinya: untuk setiap siswa_id sebagai key
        $data = $request->validate([
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'absensi' => ['required', 'array'],
            'absensi.*' => ['required', 'in:hadir,izin,sakit,alpa'],
        ]);

        // Parse tanggal ke format Y-m-d
        $tanggal = $this->parseDate($data['tanggal'])->format('Y-m-d');

        $userId = $user->getKey();

        // Cari periode aktif untuk tanggal yang dipilih
        // Jika tidak ada periode, return 404 error
        $activePeriode = $this->activePeriodeOrFail($tanggal);

        // Pastikan guru punya akses ke kelas ini (hanya bisa input kelas yang diampu)
        $this->ensureKelasAccessibleTo($user, $kelasId);

        // Validasi: tanggal harus dalam range periode akademik
        // (tidak boleh di luar tanggal_mulai dan tanggal_selesai periode)
        if ($dateError = $this->attendanceDateError($activePeriode, $tanggal)) {
            return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', $dateError);
        }

        // Validasi: tanggal tidak boleh jatuh pada hari libur
        // (baik libur nasional maupun libur mingguan seperti Sabtu/Minggu)
        $holiday = $this->findHariLibur($activePeriode->id, $tanggal);
        if ($holiday) {
            return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', $this->formatHariLiburMessage($holiday, $tanggal));
        }

        // Ambil semua ID siswa aktif di kelas ini
        $siswaIds = Siswa::query()
            ->where('kelas_id', $kelasId)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        // Validasi: Pastikan payload absensi lengkap untuk semua siswa
        // (tidak boleh ada siswa yang terlewat atau ID siswa yang tidak valid)
        $this->ensureCompleteAttendancePayload($data['absensi'], $siswaIds);

        // Cek apakah absensi untuk kelas & tanggal ini sudah pernah diinput
        $sudahAbsen = Absensi::query()
            ->where('kelas_id', $kelasId)
            ->where('tanggal', $tanggal)
            ->exists();

        // Jika sudah ada, redirect ke halaman edit (tidak boleh input ulang)
        if ($sudahAbsen) {
            return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', 'Data absensi kelas ini pada tanggal tersebut sudah terisi. Gunakan menu Edit Absensi untuk melakukan perubahan.');
        }

        $now = now();
        $rows = []; // Data untuk batch insert
        $notifSiswaIds = []; // ID siswa yang perlu dikirim notifikasi WA

        // Siapkan data untuk insert batch (lebih efisien daripada insert satu per satu)
        foreach ($siswaIds as $siswaId) {
            $status = $data['absensi'][$siswaId];

            // Siapkan row untuk insert
            $rows[] = [
                'siswa_id' => $siswaId,
                'kelas_id' => $kelasId,
                'user_id' => $userId, // ID guru yang input
                'periode_id' => $activePeriode->id,
                'tanggal' => $tanggal,
                'status' => $status,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            // Kumpulkan ID siswa yang statusnya sakit/izin/alpa
            // (hanya siswa dengan status ini yang perlu dikirim notifikasi WA)
            if (in_array($status, ['alpa', 'sakit', 'izin'])) {
                $notifSiswaIds[] = $siswaId;
            }
        }

        // Validasi: Pastikan ada data untuk disimpan
        if ($rows === []) {
            return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', 'Tidak ada data siswa yang valid untuk disimpan.');
        }

        // Simpan data absensi dalam transaction untuk menjaga konsistensi data
        // Jika ada error, semua data akan di-rollback
        try {
            DB::transaction(function () use ($rows): void {
                Absensi::insert($rows); // Batch insert untuk performa optimal
            });
        } catch (QueryException $exception) {
            // Handle race condition: duplicate entry (jika ada proses lain yang insert bersamaan)
            // Error code 23000 = integrity constraint violation
            if (($exception->errorInfo[0] ?? null) !== '23000') {
                throw $exception; // Jika error lain, lempar exception
            }

            // Jika duplicate, beri tahu user untuk reload halaman
            return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', 'Absensi pada tanggal tersebut sudah disimpan oleh proses lain. Muat ulang halaman.');
        }

        // Antrekan notifikasi WhatsApp untuk siswa yang sakit/izin/alpa
        // Notifikasi akan dikirim via queue/background job
        $this->queueAbsensiNotificationsFor($notifSiswaIds, $tanggal);

        // Redirect kembali ke halaman create dengan pesan sukses
        return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
            ->with('success', 'Data absensi baru berhasil disimpan.');
    }

    /**
     * Tampilkan halaman edit absensi untuk guru
     * Guru bisa mengubah data absensi yang sudah diinput sebelumnya
     */
    public function edit(Request $request): View|RedirectResponse
    {
        // Validasi input tanggal dari query string
        $filters = $request->validate([
            'tanggal' => ['nullable', 'date', 'before_or_equal:today'],
        ]);

        // Ambil data user yang sedang login
        $user = $request->user();

        // Validasi: Hanya guru yang boleh akses halaman edit absensi
        abort_unless($user->role === 'guru', 403, 'Akses absensi hanya tersedia untuk guru.');

        // Ambil kelas yang diampu guru
        $userKelas = $user->kelas;

        // Cek apakah guru sudah ditugaskan ke kelas
        // Jika belum, tampilkan halaman kosong dengan pesan warning
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

        // Ambil ID kelas yang diampu guru
        $kelasId = $userKelas->id;
        $kelas = collect([$userKelas]);

        // Parse tanggal input atau gunakan hari ini sebagai default
        $tanggalInput = $filters['tanggal'] ?? today()->format('d/m/Y');
        $tanggal = $this->parseDate($tanggalInput)->format('Y-m-d');

        // Cari periode akademik aktif untuk tanggal yang dipilih
        $activePeriode = Periode::query()
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->first();

        // Inisialisasi variabel untuk warning dan daftar tanggal aktif
        $periodeWarning = null;
        $activeDates = [];

        // Validasi periode akademik
        if (! $activePeriode) {
            // Jika tidak ada periode, tampilkan pesan error
            $periodeWarning = 'Periode akademik belum dikonfigurasi. Silakan hubungi operator untuk menambahkan periode terlebih dahulu sebelum dapat melakukan edit absensi.';
        } else {
            // Ambil daftar tanggal aktif untuk dropdown calendar
            $activeDates = $this->getActiveDatesForPeriode($activePeriode);

            $today = today()->toDateString();

            // Tidak boleh memilih tanggal di masa depan
            if ($tanggal > $today) {
                return redirect()->route('absensi.edit', array_merge(
                    $request->except('tanggal'),
                    ['tanggal' => $today]
                ))->with('warning', 'Tidak bisa memilih tanggal di masa depan. Otomatis diarahkan ke hari ini: ' . Carbon::parse($today)->format('d/m/Y'));
            }
        }

        // Inisialisasi data untuk view
        $siswas = [];
        $absensiSiswa = [];
        $isLocked = false;
        $holidayMessage = null;
        $stats = ['total' => 0, 'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];

        // Load data siswa jika kelas dan periode valid
        if ($kelasId && $activePeriode) {
            // Pastikan guru punya akses ke kelas ini
            $this->ensureKelasAccessibleTo($user, (int) $kelasId);

            // Cek apakah tanggal berada di luar range periode
            $holidayMessage = $this->attendanceDateError($activePeriode, $tanggal);

            // Cek apakah hari libur
            $holiday = $holidayMessage === null
                ? $this->findHariLibur($activePeriode->id, $tanggal)
                : null;

            if ($holiday) {
                $holidayMessage = $this->formatHariLiburMessage($holiday, $tanggal);
            }

            // Jika bukan hari libur, load data siswa dan absensi existing
            if (!$holidayMessage) {
                // Ambil data siswa dari kelas
                $siswas = Siswa::query()
                    ->select(['id', 'nama_siswa', 'nisn'])
                    ->where('kelas_id', $kelasId)
                    ->orderBy('nama_siswa')
                    ->get();
                $stats['total'] = $siswas->count();

                // Ambil data absensi yang sudah diinput untuk tanggal ini
                // Hasil: array [siswa_id => status]
                $absensiSiswa = Absensi::where('tanggal', $tanggal)
                    ->whereIn('siswa_id', $siswas->pluck('id'))
                    ->pluck('status', 'siswa_id')
                    ->toArray();

                // Hitung statistik kehadiran berdasarkan data existing
                foreach ($siswas as $s) {
                    $status = strtolower($absensiSiswa[$s->id] ?? 'hadir');
                    $stats[$status]++;
                }
            }
        }

        // Return view dengan semua data
        return view('absensi.edit', compact('kelas', 'siswas', 'absensiSiswa', 'kelasId', 'tanggal', 'stats', 'isLocked', 'holidayMessage', 'periodeWarning', 'activeDates'));
    }

    /**
     * Update data absensi yang sudah ada
     * Mendeteksi perubahan dan mengirim notifikasi WA untuk siswa yang berubah statusnya
     */
    public function update(Request $request): RedirectResponse
    {
        // Ambil data user yang sedang login
        $user = $request->user();

        // Validasi: Hanya guru yang boleh update absensi
        abort_unless($user->role === 'guru', 403, 'Akses absensi hanya tersedia untuk guru.');

        // Ambil kelas yang diampu guru
        $userKelas = $user->kelas;
        if (! $userKelas) {
            return redirect()->route('absensi.edit')
                ->with('error', 'Anda belum ditugaskan ke kelas manapun.');
        }

        $kelasId = $userKelas->id;

        // Validasi input dari form
        $data = $request->validate([
            'tanggal' => ['required', 'date', 'before_or_equal:today'],
            'absensi' => ['required', 'array'],
            'absensi.*' => ['required', 'in:hadir,izin,sakit,alpa'],
        ]);

        // Parse tanggal
        $tanggal = $this->parseDate($data['tanggal'])->format('Y-m-d');

        $userId = $user->getKey();

        // Cari periode aktif untuk tanggal yang dipilih
        $activePeriode = $this->activePeriodeOrFail($tanggal);

        // Pastikan guru punya akses ke kelas ini
        $this->ensureKelasAccessibleTo($user, $kelasId);

        // Validasi: tanggal harus dalam range periode
        if ($dateError = $this->attendanceDateError($activePeriode, $tanggal)) {
            return redirect()->route('absensi.edit', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', $dateError);
        }

        // Validasi: tanggal tidak boleh jatuh pada hari libur
        $holiday = $this->findHariLibur($activePeriode->id, $tanggal);
        if ($holiday) {
            return redirect()->route('absensi.edit', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', $this->formatHariLiburMessage($holiday, $tanggal));
        }

        // Ambil semua ID siswa aktif di kelas
        $siswaIds = Siswa::query()
            ->where('kelas_id', $kelasId)
            ->orderBy('id')
            ->pluck('id')
            ->all();

        // Validasi: Pastikan payload lengkap untuk semua siswa
        $this->ensureCompleteAttendancePayload($data['absensi'], $siswaIds);

        // Ambil data absensi existing untuk perbandingan (detect changes)
        // Gunakan keyBy untuk mapping siswa_id => Absensi model
        $existingAbsensis = Absensi::query()
            ->where('kelas_id', $kelasId)
            ->where('tanggal', $tanggal)
            ->whereIn('siswa_id', $siswaIds)
            ->get()
            ->keyBy('siswa_id');

        $now = now();
        $upsertRows = []; // Data untuk upsert (insert or update)
        $changedSiswaIds = []; // ID siswa yang statusnya berubah (untuk notifikasi WA)
        $hasChanges = false; // Flag apakah ada perubahan data

        // Bandingkan data lama vs baru, siapkan untuk upsert
        foreach ($siswaIds as $siswaId) {
            $status = $data['absensi'][$siswaId]; // Status baru dari form
            $existing = $existingAbsensis->get($siswaId); // Data lama dari database
            $oldStatus = $existing?->status; // Status lama (null jika belum ada data)

            // Deteksi perubahan: status, user_id, atau kelas_id berbeda
            $hasChanges = $hasChanges
                || ! $existing // Belum ada data sebelumnya
                || $oldStatus !== $status // Status berubah
                || (int) $existing->user_id !== (int) $userId // User yang input berbeda
                || (int) $existing->kelas_id !== $kelasId; // Kelas berbeda

            // Siapkan row untuk upsert
            $upsertRows[] = [
                'siswa_id' => $siswaId,
                'kelas_id' => $kelasId,
                'user_id' => $userId,
                'periode_id' => $activePeriode->id,
                'tanggal' => $tanggal,
                'status' => $status,
                'created_at' => $existing?->created_at ?? $now, // Pertahankan created_at lama jika ada
                'updated_at' => $now,
            ];

            // Kirim notifikasi WA untuk SEMUA siswa yang STATUS-nya berubah
            // Tidak peduli dari status apa ke status apa (hadir/sakit/izin/alpa)
            if ($existing && $oldStatus !== $status) {
                $changedSiswaIds[] = $siswaId;
            }
        }

        // Jika tidak ada perubahan, tidak perlu update database
        if (! $hasChanges) {
            return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('success', 'Tidak ada perubahan data absensi.');
        }

        // Simpan perubahan menggunakan upsert (insert or update)
        // Upsert lebih efisien daripada cek exist -> update/insert
        DB::transaction(function () use ($upsertRows): void {
            Absensi::upsert(
                $upsertRows,
                ['siswa_id', 'tanggal'], // Unique key untuk menentukan update atau insert
                ['kelas_id', 'user_id', 'periode_id', 'status', 'updated_at'] // Kolom yang diupdate
            );
        });

        // Antrekan notifikasi WhatsApp untuk semua siswa yang statusnya berubah
        // Notifikasi dikirim via queue/background job
        $this->queueEditAbsensiNotificationsFor($changedSiswaIds, $tanggal);

        // Redirect kembali dengan pesan sukses
        return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
            ->with('success', 'Data riwayat absensi berhasil diperbarui.');
    }

    /**
     * Validasi kelengkapan payload absensi
     * Memastikan semua siswa aktif tercakup dalam data yang dikirim
     * Mencegah:
     * - Ada siswa yang terlewat
     * - Ada ID siswa yang tidak valid/tidak terdaftar
     * - Payload kosong
     * 
     * @param  array<int|string, string>  $attendance  Data absensi dari form request
     * @param  array<int, int>  $expectedStudentIds  Daftar ID siswa yang seharusnya ada
     * @throws ValidationException  Jika ada ketidaksesuaian data
     */
    private function ensureCompleteAttendancePayload(array $attendance, array $expectedStudentIds): void
    {
        $submittedStudentIds = [];

        // Loop semua ID siswa yang dikirim dari form
        foreach (array_keys($attendance) as $studentId) {
            // Validasi: ID harus berupa angka positif
            if (! ctype_digit((string) $studentId) || (int) $studentId < 1) {
                throw ValidationException::withMessages([
                    'absensi' => 'Payload absensi mengandung ID siswa yang tidak valid.',
                ]);
            }

            $submittedStudentIds[] = (int) $studentId;
        }

        // Sort kedua array untuk perbandingan yang akurat
        sort($submittedStudentIds);
        sort($expectedStudentIds);

        // Validasi: Kelas harus punya siswa aktif
        if ($expectedStudentIds === []) {
            throw ValidationException::withMessages([
                'absensi' => 'Kelas ini belum memiliki siswa aktif.',
            ]);
        }

        // Validasi: Data yang dikirim harus sama persis dengan data siswa aktif
        // Tidak boleh kurang (ada yang terlewat) atau lebih (ada ID yang salah)
        if ($submittedStudentIds !== $expectedStudentIds) {
            throw ValidationException::withMessages([
                'absensi' => 'Absensi harus memuat seluruh siswa aktif dari kelas yang dipilih.',
            ]);
        }
    }

    /**
     * Memastikan user (guru) memiliki akses ke kelas yang diminta
     * Guru hanya boleh mengakses kelas yang diampunya
     * 
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException  403 Forbidden jika tidak punya akses
     */
    private function ensureKelasAccessibleTo(User $user, int $kelasId): void
    {
        // Cek apakah kelas_id yang diminta sama dengan kelas yang diampu guru
        if ($user->kelas?->id !== $kelasId) {
            abort(403, 'Anda tidak memiliki akses ke kelas ini.');
        }
    }

    /**
     * Cari periode akademik aktif untuk tanggal tertentu
     * Return error 404 jika tidak ada periode yang cocok
     * 
     * @throws \Symfony\Component\HttpKernel\Exception\HttpException  404 Not Found jika periode tidak ditemukan
     */
    private function activePeriodeOrFail(string $tanggal): Periode
    {
        // Cari periode yang mencakup tanggal yang diminta
        // (tanggal berada antara tanggal_mulai dan tanggal_selesai)
        $periode = Periode::query()
            ->whereDate('tanggal_mulai', '<=', $tanggal)
            ->whereDate('tanggal_selesai', '>=', $tanggal)
            ->first();

        // Jika tidak ada periode, return 404
        if (! $periode) {
            abort(404, 'Periode akademik tidak ditemukan untuk tanggal tersebut.');
        }

        return $periode;
    }

    /**
     * Validasi apakah tanggal berada dalam range periode akademik
     * 
     * @return string|null  Error message jika di luar range, null jika valid
     */
    private function attendanceDateError(Periode $periode, string $tanggal): ?string
    {
        // Konversi tanggal periode ke string Y-m-d
        $tanggalMulai = $periode->tanggal_mulai->toDateString();
        $tanggalSelesai = $periode->tanggal_selesai->toDateString();

        // Cek apakah tanggal di luar range periode
        if ($tanggal < $tanggalMulai || $tanggal > $tanggalSelesai) {
            return "Tanggal absensi harus berada dalam periode {$tanggalMulai} sampai {$tanggalSelesai}.";
        }

        return null; // Valid, tidak ada error
    }

    /**
     * Cari data hari libur untuk tanggal tertentu
     * Mencakup 2 tipe libur:
     * 1. Libur nasional: tanggal spesifik (misal: 17 Agustus, 25 Desember)
     * 2. Libur mingguan: hari dalam seminggu (misal: Sabtu, Minggu)
     * 
     * @param  int|null  $periodeId  ID periode akademik
     * @param  string  $tanggal  Tanggal yang dicek (format Y-m-d)
     * @return HariLibur|null  Data hari libur jika ditemukan, null jika bukan libur
     */
    private function findHariLibur(?int $periodeId, string $tanggal): ?HariLibur
    {
        // Konversi angka hari (0-6) ke nama hari Indonesia
        $namaHari = $this->namaHariIndonesia(Carbon::parse($tanggal)->dayOfWeek);

        // Query hari libur dengan 2 kondisi:
        return HariLibur::where(function ($query) use ($periodeId, $tanggal, $namaHari) {
            // Kondisi 1: Libur nasional dengan tanggal spesifik
            $query->where(function ($query) use ($tanggal) {
                $query->where('tipe', 'nasional')
                    ->whereDate('tanggal', $tanggal);
            })
                // Kondisi 2: Libur mingguan (misal: setiap Sabtu/Minggu)
                ->orWhere(function ($query) use ($periodeId, $namaHari) {
                    $query->where('periode_id', $periodeId)
                        ->where('tipe', 'mingguan')
                        ->where('hari', $namaHari);
                });
        })
            ->first();
    }

    /**
     * Format pesan error yang user-friendly untuk hari libur
     */
    private function formatHariLiburMessage(HariLibur $hariLibur, string $tanggal): string
    {
        // Format tanggal: 02-09-2026
        $tanggalFormatted = Carbon::parse($tanggal)->format('d-m-Y');

        // Ambil nama/keterangan libur (prioritas: nama_libur > keterangan > default)
        $keterangan = $hariLibur->nama_libur ?: $hariLibur->keterangan ?: 'Hari libur';

        return "Tanggal {$tanggalFormatted} termasuk {$keterangan}. Guru tidak dapat melakukan input absensi pada hari libur.";
    }

    /**
     * Konversi angka hari ke nama hari dalam Bahasa Indonesia
     * 
     * @param  int  $dayOfWeek  Angka hari (0=Minggu, 1=Senin, ..., 6=Sabtu)
     * @return string  Nama hari dalam Bahasa Indonesia
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
     * Ambil daftar tanggal aktif (hari sekolah) dalam satu periode
     * Mengecualikan:
     * - Hari libur nasional (tanggal spesifik)
     * - Hari libur mingguan (misal: Sabtu/Minggu)
     * 
     * Digunakan untuk dropdown calendar di frontend
     * 
     * @param  Periode  $periode  Periode akademik
     * @return array<string>  Array tanggal format Y-m-d
     */
    private function getActiveDatesForPeriode(Periode $periode): array
    {
        // Parse tanggal mulai dan selesai periode
        $mulai = Carbon::parse($periode->tanggal_mulai);
        $akhir = Carbon::parse($periode->tanggal_selesai);

        // Ambil semua hari libur nasional dalam periode ini
        // Hasil: Collection tanggal-tanggal libur
        $hariLiburNasional = HariLibur::where('periode_id', $periode->id)
            ->where('tipe', 'nasional')
            ->whereBetween('tanggal', [$mulai->toDateString(), $akhir->toDateString()])
            ->pluck('tanggal')
            ->map(fn($t) => Carbon::parse($t)->toDateString()) // Normalize ke Y-m-d
            ->unique()
            ->values();

        // Ambil nama-nama hari libur mingguan (misal: ["Sabtu", "Minggu"])
        $hariLiburMingguan = HariLibur::where('periode_id', $periode->id)
            ->where('tipe', 'mingguan')
            ->pluck('hari')
            ->unique()
            ->values();

        // Mapping angka hari ke nama hari Indonesia
        $namaHari = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];

        $activeDates = [];
        $hari = $mulai->copy(); // Copy untuk iterasi tanpa mengubah $mulai

        // Loop setiap hari dalam range periode
        while ($hari->lte($akhir)) { // lte = less than or equal
            $tanggalStr = $hari->toDateString(); // Format Y-m-d
            $namaHariIni = $namaHari[$hari->dayOfWeek]; // Nama hari (Senin, Selasa, dst)

            // Cek apakah hari ini libur (nasional ATAU mingguan)
            $isHariLibur = $hariLiburNasional->contains($tanggalStr) // Libur nasional
                || $hariLiburMingguan->contains($namaHariIni); // Libur mingguan

            // Jika bukan libur, masukkan ke daftar tanggal aktif
            if (! $isHariLibur) {
                $activeDates[] = $tanggalStr;
            }

            // Pindah ke hari berikutnya
            $hari->addDay();
        }

        return $activeDates;
    }

    /**
     * Antrekan notifikasi WhatsApp untuk siswa yang sakit/izin/alpa (input absensi baru)
     * Flow:
     * 1. Ambil data absensi siswa yang statusnya sakit/izin/alpa
     * 2. Buat notifikasi WA untuk masing-masing siswa
     * 3. Kirim ke queue/background job untuk diproses async
     * 
     * @param  array<int, int>  $siswaIds  Daftar ID siswa yang perlu notifikasi
     * @param  string  $tanggal  Tanggal absensi (format Y-m-d)
     */
    private function queueAbsensiNotificationsFor(array $siswaIds, string $tanggal): void
    {
        // Jika tidak ada siswa, skip
        if ($siswaIds === []) {
            return;
        }

        // Query data absensi dengan relasi siswa dan kelas (eager loading)
        // Filter hanya siswa dengan status sakit/izin/alpa
        $notificationIds = Absensi::query()
            ->with(['siswa', 'kelas']) // Eager load untuk hindari N+1 query
            ->where('tanggal', $tanggal)
            ->whereIn('siswa_id', array_unique($siswaIds)) // Pastikan ID unik
            ->whereIn('status', ['alpa', 'sakit', 'izin']) // Hanya status tidak hadir
            ->get()
            ->flatMap(fn(Absensi $absensi) => $this->upsertAbsensiWhatsappNotifications($absensi)) // Buat notifikasi untuk setiap absensi
            ->filter(fn(?int $id) => $id !== null) // Buang yang null (gagal buat notifikasi)
            ->values()
            ->all();

        // Jika ada notifikasi yang berhasil dibuat, kirim ke queue
        if ($notificationIds !== []) {
            SendWhatsappBatchJob::dispatch($notificationIds);
        }
    }

    /**
     * Antrekan notifikasi WhatsApp untuk siswa yang statusnya berubah (via edit absensi)
     * Notifikasi dikirim untuk perubahan status APAPUN (hadir/sakit/izin/alpa)
     * Flow sama seperti queueAbsensiNotificationsFor, tapi untuk semua status
     * 
     * @param  array<int, int>  $siswaIds  Daftar ID siswa yang statusnya berubah
     * @param  string  $tanggal  Tanggal absensi (format Y-m-d)
     */
    private function queueEditAbsensiNotificationsFor(array $siswaIds, string $tanggal): void
    {
        // Jika tidak ada siswa, skip
        if ($siswaIds === []) {
            return;
        }

        // Query data absensi (tidak filter status, ambil semua)
        $notificationIds = Absensi::query()
            ->with(['siswa', 'kelas'])
            ->where('tanggal', $tanggal)
            ->whereIn('siswa_id', array_unique($siswaIds))
            ->get()
            ->flatMap(fn(Absensi $absensi) => $this->createEditAbsensiNotification($absensi)) // Buat notifikasi edit
            ->filter(fn(?int $id) => $id !== null)
            ->values()
            ->all();

        // Kirim ke queue jika ada notifikasi
        if ($notificationIds !== []) {
            SendWhatsappBatchJob::dispatch($notificationIds);
        }
    }

    /**
     * Buat/update notifikasi WhatsApp untuk satu data absensi (input baru)
     * Digunakan saat input absensi pertama kali untuk siswa sakit/izin/alpa
     * 
     * Flow:
     * 1. Cek apakah siswa punya nomor WA orang tua
     * 2. Ambil kontak orang tua (Ibu prioritas, lalu Ayah)
     * 3. Cek apakah notifikasi sudah pernah dikirim (prevent duplicate)
     * 4. Buat pesan WA dan simpan ke database dengan status 'pending'
     * 
     * @return array<int, int>  Array berisi ID notifikasi yang dibuat/updated
     */
    private function upsertAbsensiWhatsappNotifications(Absensi $absensi): array
    {
        // Ambil data siswa
        $siswa = $absensi->siswa;

        // Jika siswa tidak ada (seharusnya tidak mungkin), skip
        if (! $siswa) {
            return [];
        }

        // Ambil kontak orang tua (prioritas: Ibu dulu, lalu Ayah)
        // Hasil: array of [nama, nomor_wa] atau empty array jika tidak ada
        $contacts = $this->resolveParentContacts($siswa);

        // Jika tidak ada nomor WA orang tua, simpan sebagai notifikasi gagal
        if ($contacts === []) {
            $this->unsentParentNotification($absensi);
            return [];
        }

        // Ambil kontak primary (pertama dalam list)
        $primary = $contacts[0];
        // Ambil kontak fallback (kedua dalam list, jika ada)
        $fallback = $contacts[1] ?? null;

        // Cek apakah notifikasi untuk absensi & nomor ini sudah ada
        // firstOrNew: return existing jika ada, atau buat instance baru (belum save)
        $notification = WhatsappNotification::query()
            ->firstOrNew([
                'absensi_id' => $absensi->id,
                'provider' => 'fonnte',
                'parent_phone' => $primary[1], // Nomor WA primary
            ]);

        // Jika notifikasi sudah terkirim, jangan buat ulang
        if ($notification->status === 'sent') {
            return [(int) $notification->id];
        }

        // Bangun pesan WhatsApp sesuai status absensi
        $message = $this->buildAbsensiWhatsappMessage($absensi, $primary[0]);

        // Jika ada nomor fallback (Ayah/Ibu kedua), tambahkan ke pesan
        if ($fallback) {
            $message .= "\n\n[Fallback: {$fallback[0]} - {$fallback[1]}]";
        }

        // Isi data notifikasi dan simpan ke database
        $notification->fill([
            'siswa_id' => $absensi->siswa_id,
            'parent_name' => $primary[0], // Nama orang tua
            'parent_phone' => $primary[1], // Nomor WA
            'message' => $message,
            'status' => 'pending', // Status awal: pending (belum dikirim)
            'last_error' => null,
            'sent_at' => null,
        ])->save();

        return [(int) $notification->id];
    }

    /**
     * Buat notifikasi WhatsApp BARU untuk perubahan status absensi (via edit)
     * Digunakan saat guru mengedit absensi dan mengubah status siswa
     * 
     * Perbedaan dengan upsertAbsensiWhatsappNotifications:
     * - Selalu buat record BARU (tidak update yang lama)
     * - Support semua status (hadir, sakit, izin, alpa)
     * - Pesan berbeda untuk status 'hadir' vs status lain
     * 
     * @return array<int, int>  Array berisi ID notifikasi baru
     */
    private function createEditAbsensiNotification(Absensi $absensi): array
    {
        // Ambil data siswa
        $siswa = $absensi->siswa;

        if (! $siswa) {
            return [];
        }

        // Ambil kontak orang tua
        $contacts = $this->resolveParentContacts($siswa);

        // Jika tidak ada nomor WA, skip (tidak simpan sebagai gagal untuk edit)
        if ($contacts === []) {
            return [];
        }

        $primary = $contacts[0];
        $fallback = $contacts[1] ?? null;

        // Pilih template pesan berdasarkan status
        // Match expression: lebih clean daripada if-else bertingkat
        $message = match (strtolower($absensi->status)) {
            'hadir' => $this->buildHadirWhatsappMessage($absensi, $primary[0]), // Pesan khusus untuk hadir
            default => $this->buildAbsensiWhatsappMessage($absensi, $primary[0]), // Pesan untuk sakit/izin/alpa
        };

        // Tambahkan fallback contact jika ada
        if ($fallback) {
            $message .= "\n\n[Fallback: {$fallback[0]} - {$fallback[1]}]";
        }

        // Buat notifikasi BARU (bukan update!)
        // Riwayat notifikasi lama tetap tersimpan di database
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
     * Simpan notifikasi GAGAL karena nomor WA orang tua tidak tersedia
     * Digunakan untuk tracking siswa yang tidak bisa dikirimi notifikasi
     */
    private function unsentParentNotification(Absensi $absensi): void
    {
        $siswa = $absensi->siswa;

        // Cek apakah notifikasi gagal untuk absensi ini sudah ada
        $notification = WhatsappNotification::query()
            ->firstOrNew([
                'absensi_id' => $absensi->id,
                'provider' => 'fonnte',
                'parent_phone' => null, // NULL = tidak ada nomor WA
            ]);

        // Jika sudah di-mark sebagai sent (tidak mungkin sih), skip
        if ($notification->status === 'sent') {
            return;
        }

        // Simpan sebagai notifikasi gagal
        $notification->fill([
            'siswa_id' => $absensi->siswa_id,
            'parent_name' => null,
            'parent_phone' => null,
            'message' => $this->buildAbsensiWhatsappMessage($absensi, null), // Pesan tetap dibuat untuk log
            'status' => 'failed', // Status: failed
            'last_error' => 'Nomor WhatsApp orang tua/wali tidak tersedia.',
            'sent_at' => null,
        ])->save();
    }

    /**
     * Ambil daftar kontak orang tua siswa yang valid dan unik
     * 
     * Prioritas kontak:
     * 1. Ibu (nama_ibu + no_whatsapp_ibu)
     * 2. Ayah (nama_ayah + no_whatsapp_ayah)
     * 
     * Duplikasi nomor diabaikan (jika nomor Ibu sama dengan Ayah, hanya ambil 1)
     * Nomor kosong/invalid diabaikan
     * 
     * @param  Siswa  $siswa  Data siswa
     * @return array<int, array{0: ?string, 1: string}>  Array of [nama, nomor_wa]
     *         Contoh: [["Siti Aminah", "628123456789"], ["Budi Santoso", "628987654321"]]
     */
    private function resolveParentContacts(Siswa $siswa): array
    {
        $contacts = [];
        $seen = []; // Tracking nomor yang sudah diambil (prevent duplicate)

        // Loop kontak orang tua: [Ibu, Ayah]
        foreach (
            [
                [$siswa->nama_ibu, $siswa->no_whatsapp_ibu],
                [$siswa->nama_ayah, $siswa->no_whatsapp_ayah],
            ] as [$name, $phone]
        ) {
            // Normalisasi nomor WA ke format internasional (62xxx)
            $normalized = $this->normalizeWhatsappNumber($phone);

            // Skip jika nomor kosong atau sudah ada dalam list
            if (blank($normalized) || isset($seen[$normalized])) {
                continue;
            }

            // Tandai nomor ini sudah dipakai
            $seen[$normalized] = true;

            // Tambahkan ke daftar kontak
            $contacts[] = [$name, $normalized];
        }

        return $contacts;
    }

    /**
     * Normalisasi nomor WhatsApp ke format internasional
     * 
     * Konversi:
     * - 0812xxx → 62812xxx  (hapus 0 di depan, tambah 62)
     * - 812xxx → 62812xxx   (tambah 62 di depan)
     * - 62812xxx → 62812xxx (sudah benar, tidak diubah)
     * 
     * @param  string|null  $phone  Nomor telepon input
     * @return string|null  Nomor dalam format 62xxx, atau null jika invalid
     */
    private function normalizeWhatsappNumber(?string $phone): ?string
    {
        // Jika nomor kosong, return null
        if (blank($phone)) {
            return null;
        }

        // Hapus semua karakter non-digit (spasi, tanda hubung, kurung, dll)
        // Contoh: "(0812) 345-6789" → "08123456789"
        $number = (string) preg_replace('/\D+/', '', $phone);

        // Jika nomor diawali '0', hapus '0' dan tambah '62'
        // Contoh: 08123456789 → 628123456789
        if (str_starts_with($number, '0')) {
            return '62' . substr($number, 1);
        }

        // Jika nomor diawali '8', tambah '62' di depan
        // Contoh: 8123456789 → 628123456789
        if (str_starts_with($number, '8')) {
            return '62' . $number;
        }

        // Jika sudah format 62xxx atau format lain, return as-is
        // Return null jika hasil kosong
        return $number ?: null;
    }

    /**
     * Bangun pesan WhatsApp untuk notifikasi absensi (sakit/izin/alpa)
     * Format: Rapi, formal, informatif
     * 
     * Struktur pesan:
     * 1. Header: Judul + Nama sekolah
     * 2. Sapaan: "Yth. Bapak/Ibu [Nama]"
     * 3. Konten: Informasi siswa (Nama, Kelas, Hari, Status)
     * 4. Keterangan: Penjelasan status
     * 5. Footer: Contact info wali kelas
     * 
     * @param  Absensi  $absensi  Data absensi siswa
     * @param  string|null  $parentName  Nama orang tua (untuk sapaan)
     * @return string  Pesan WhatsApp siap kirim
     */
    private function buildAbsensiWhatsappMessage(Absensi $absensi, ?string $parentName): string
    {
        $siswa = $absensi->siswa;

        // Format tanggal: 02/09/2026
        $tanggal = Carbon::parse($absensi->tanggal)->format('d/m/Y');

        // Nama hari: Rabu
        $hari = $this->namaHariIndonesia(Carbon::parse($absensi->tanggal)->dayOfWeek);

        // Sapaan personal jika ada nama orang tua, generic jika tidak ada
        $sapaan = $parentName ? "Yth. Bapak/Ibu {$parentName}" : 'Yth. Bapak/Ibu Orang Tua/Wali';

        // Nama kelas: 1-A, 2-B, dst
        $kelas = $absensi->kelas?->nama_kelas ?? '-';

        // Status dalam huruf kapital: SAKIT, IZIN, ALPA
        $status = strtoupper($absensi->status);

        $namaSekolah = 'SDN CIBITUNGKULON 02';

        // Keterangan status (penjelasan user-friendly)
        $keterangan = match (strtolower($absensi->status)) {
            'sakit' => 'Siswa tidak hadir karena sakit.',
            'izin' => 'Siswa tidak hadir karena izin.',
            'alpa' => 'Siswa tidak hadir tanpa keterangan.',
            default => 'Informasi kehadiran siswa.',
        };

        // Build pesan line by line
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
        $pesan .= "Terima kasih.\n\n";
        $pesan .= $this->formatWaliKelasFooter($absensi); // Footer dengan contact wali kelas

        return $pesan;
    }

    /**
     * Bangun pesan WhatsApp untuk notifikasi PERUBAHAN status ke HADIR
     * Digunakan saat guru mengedit absensi dari sakit/izin/alpa → hadir
     * 
     * Format berbeda dari buildAbsensiWhatsappMessage:
     * - Header: "PEMBARUAN KEHADIRAN SISWA"
     * - Status: "HADIR (Diperbarui)"
     * - Keterangan: Menjelaskan bahwa data telah dikoreksi
     * 
     * @param  Absensi  $absensi  Data absensi siswa
     * @param  string|null  $parentName  Nama orang tua
     * @return string  Pesan WhatsApp siap kirim
     */
    private function buildHadirWhatsappMessage(Absensi $absensi, ?string $parentName): string
    {
        $siswa = $absensi->siswa;

        // Format tanggal dan hari
        $tanggal = Carbon::parse($absensi->tanggal)->format('d/m/Y');
        $hari = $this->namaHariIndonesia(Carbon::parse($absensi->tanggal)->dayOfWeek);

        // Sapaan
        $sapaan = $parentName ? "Yth. Bapak/Ibu {$parentName}" : 'Yth. Bapak/Ibu Orang Tua/Wali';

        // Nama kelas
        $kelas = $absensi->kelas?->nama_kelas ?? '-';

        $namaSekolah = 'SDN CIBITUNGKULON 02';

        // Build pesan khusus untuk perubahan ke hadir
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
        $pesan .= "Terima kasih.\n\n";
        $pesan .= $this->formatWaliKelasFooter($absensi);

        return $pesan;
    }

    /**
     * Format footer pesan dengan info kontak wali kelas
     * 
     * @param  Absensi  $absensi  Data absensi (untuk ambil relasi kelas -> guru)
     * @return string  Footer format: [Walikelas: Nama - Nomor]
     */
    private function formatWaliKelasFooter(Absensi $absensi): string
    {
        // Ambil data guru (wali kelas) dari relasi kelas
        $waliKelas = $absensi->kelas?->guru;

        // Jika tidak ada data guru, return placeholder
        if (! $waliKelas) {
            return '[Walikelas: - -]';
        }

        // Format: [Walikelas: Budi Santoso - 08123456789]
        return sprintf(
            '[Walikelas: %s - %s]',
            $waliKelas->nama ?: '-', // Nama guru, atau '-' jika kosong
            $waliKelas->no_telepon ?: '-' // Nomor telepon, atau '-' jika kosong
        );
    }

    /**
     * Parse string tanggal dengan support multiple format
     * 
     * Format yang didukung:
     * 1. d/m/Y (02/09/2026) - format Indonesia
     * 2. Y-m-d (2026-09-02) - format ISO/database
     * 3. Format lain yang valid untuk Carbon::parse()
     * 
     * Error handling: Jika parsing gagal, return today() sebagai fallback
     * 
     * @param  string  $date  String tanggal input
     * @return Carbon  Object Carbon hasil parsing
     */
    private function parseDate(string $date): Carbon
    {
        // Cek apakah format d/m/Y (dengan regex untuk memastikan format exact)
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
            try {
                // Parse dengan format strict
                return Carbon::createFromFormat('d/m/Y', $date);
            } catch (\Exception $e) {
                // Jika gagal (misal: tanggal invalid seperti 32/13/2026), fallback ke Carbon::parse()
            }
        }

        // Coba parse format lain (Y-m-d, atau format Carbon-compatible lainnya)
        try {
            return Carbon::parse($date);
        } catch (\Exception $e) {
            // Jika semua parsing gagal, return hari ini sebagai safety fallback
            // Ini mencegah crash app jika ada input tanggal yang corrupt
            return today();
        }
    }
}
