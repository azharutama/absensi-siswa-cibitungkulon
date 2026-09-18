<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsActivity;
use App\Models\Absensi;
use App\Models\Periode;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Closure;
use Carbon\Carbon;

class PeriodeController extends Controller
{
    use LogsActivity;

    /**
     * Tampilkan konfigurasi tahun ajaran, semester, dan hari libur.
     *
     * Data semester dimuat bersama hari liburnya agar form dapat menampilkan
     * nilai lama dalam format yang sesuai untuk input tanggal HTML.
     */
    public function index(Request $request)
    {
        // Ambil semester beserta relasi hari libur dalam satu query.
        $periodes = Periode::query()
            ->with('hariLiburs')
            ->get();

        // Pisahkan data semester agar masing-masing tanggal dapat ditampilkan di form.
        $semester1 = $periodes->firstWhere('semester', 1);
        $semester2 = $periodes->firstWhere('semester', 2);


        // Siapkan nilai ISO untuk input HTML date dan pertahankan nilai lama saat validasi gagal.
        $periodeData = [
            'tahun_ajaran' => $semester1?->tahun_ajaran ?? $semester2?->tahun_ajaran ?? old('tahun_ajaran', ''), //?-> adalah null safe operator, jika $semester1 null maka akan mengecek $semester2, jika keduanya null maka akan menggunakan old('tahun_ajaran', '').
            'semester_1_tanggal_mulai' => $semester1?->tanggal_mulai?->format('Y-m-d') ?? old('semester_1_tanggal_mulai', ''),
            'semester_1_tanggal_selesai' => $semester1?->tanggal_selesai?->format('Y-m-d') ?? old('semester_1_tanggal_selesai', ''),
            'semester_2_tanggal_mulai' => $semester2?->tanggal_mulai?->format('Y-m-d') ?? old('semester_2_tanggal_mulai', ''),
            'semester_2_tanggal_selesai' => $semester2?->tanggal_selesai?->format('Y-m-d') ?? old('semester_2_tanggal_selesai', ''),
        ];

        // Siapkan format tanggal Indonesia untuk teks tampilan yang mudah dibaca pengguna.
        $periodeDataDisplay = [
            'semester_1_tanggal_mulai' => $semester1?->tanggal_mulai?->format('d/m/Y'),
            'semester_1_tanggal_selesai' => $semester1?->tanggal_selesai?->format('d/m/Y'),
            'semester_2_tanggal_mulai' => $semester2?->tanggal_mulai?->format('d/m/Y'),
            'semester_2_tanggal_selesai' => $semester2?->tanggal_selesai?->format('d/m/Y'),
        ];

        $liburMingguan = collect();
        $liburNasional = collect();

        // Gunakan semester 1 sebagai sumber tampilan awal; jika belum ada, gunakan semester 2.
        $periode = $semester1 ?? $semester2; //Gunakan Semester 1 jika tersedia. Kalau tidak, gunakan Semester 2.

        if ($periode) {
            // Pisahkan hari libur mingguan dan nasional agar sesuai dengan dua bagian form.
            $liburMingguan = $periode->hariLiburs //Mengambil libur mingguan
                ->where('tipe', 'mingguan')
                ->map(fn($item) => [
                    'hari' => $item->hari,
                    'keterangan' => $item->keterangan,
                ]);

            $liburNasional = $periode->hariLiburs //Mengambil libur nasional
                ->where('tipe', 'nasional')
                ->map(fn($item) => [
                    'tanggal' => $item->tanggal?->format('Y-m-d') ?? '', // Format Y-m-d untuk input HTML5 date
                    'nama_libur' => $item->nama_libur ?: $item->keterangan,
                    'keterangan' => $item->nama_libur ? $item->keterangan : '',
                ]);
        }

        // Kirim konfigurasi periode dan hari libur ke halaman pengaturan akademik.
        return view('periode.index', compact('periode', 'periodeData', 'periodeDataDisplay', 'liburMingguan', 'liburNasional'));
    }

    /**
     * Buat konfigurasi baru untuk Semester 1 dan Semester 2.
     *
     * Tanggal hari libur nasional divalidasi agar hanya berada di salah satu
     * rentang semester, lalu semua data disimpan atomik dalam satu transaksi.
     */
    public function store(Request $request)
    {
        // Validasi tahun ajaran, rentang semester, dan daftar hari libur dari form.
        $validated = $request->validate([
            // Validasi untuk periode akademik
            'tahun_ajaran' => [
                'required',
                'string',
                'max:20',
            ],
            // Validasi untuk tanggal mulai dan selesai semester
            'semester_1_tanggal_mulai' => ['required', 'date'],
            'semester_1_tanggal_selesai' => ['required', 'date', 'after_or_equal:semester_1_tanggal_mulai'],
            'semester_2_tanggal_mulai' => ['required', 'date', 'after_or_equal:semester_1_tanggal_selesai'],
            'semester_2_tanggal_selesai' => ['required', 'date', 'after_or_equal:semester_2_tanggal_mulai'],
            // Validasi untuk libur mingguan 
            'libur_mingguan' => ['nullable', 'array'],
            'libur_mingguan.*' => ['array'], //Tanda * berarti setiap elemen di dalam libur_mingguan.
            'libur_mingguan.*.hari' => [
                'required',
                'string',
                Rule::in(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']),
                'distinct', //distinct misalnya mencegah duplikasi
            ],
            'libur_mingguan.*.keterangan' => ['required', 'string', 'max:255'],
            // Validasi untuk libur nasional
            'libur_nasional' => ['nullable', 'array'],
            'libur_nasional.*' => ['array'],
            'libur_nasional.*.tanggal' => [
                'required',
                'date',
                $this->nationalHolidayDateRule($request),
                'distinct',
            ],
            'libur_nasional.*.nama_libur' => ['required', 'string', 'max:255'],
            'libur_nasional.*.keterangan' => ['nullable', 'string', 'max:255'],
        ], [
            'tahun_ajaran.required' => 'Tahun ajaran wajib diisi.',
            'tahun_ajaran.max' => 'Tahun ajaran maksimal 20 karakter.',
            'tahun_ajaran.unique' => 'Tahun ajaran ini sudah terdaftar.',
            'semester_1_tanggal_mulai.required' => 'Tanggal mulai Semester 1 wajib diisi.',
            'semester_1_tanggal_mulai.date_format' => 'Tanggal mulai Semester 1 harus berupa tanggal yang valid (format: dd/mm/yyyy).',
            'semester_1_tanggal_selesai.required' => 'Tanggal selesai Semester 1 wajib diisi.',
            'semester_1_tanggal_selesai.date_format' => 'Tanggal selesai Semester 1 harus berupa tanggal yang valid (format: dd/mm/yyyy).',
            'semester_1_tanggal_selesai.after_or_equal' => 'Tanggal selesai Semester 1 harus setelah atau sama dengan tanggal mulai Semester 1.',
            'semester_2_tanggal_mulai.required' => 'Tanggal mulai Semester 2 wajib diisi.',
            'semester_2_tanggal_mulai.date_format' => 'Tanggal mulai Semester 2 harus berupa tanggal yang valid (format: dd/mm/yyyy).',
            'semester_2_tanggal_mulai.after_or_equal' => 'Tanggal mulai Semester 2 harus setelah atau sama dengan tanggal selesai Semester 1.',
            'semester_2_tanggal_selesai.required' => 'Tanggal selesai Semester 2 wajib diisi.',
            'semester_2_tanggal_selesai.date_format' => 'Tanggal selesai Semester 2 harus berupa tanggal yang valid (format: dd/mm/yyyy).',
            'semester_2_tanggal_selesai.after_or_equal' => 'Tanggal selesai Semester 2 harus setelah atau sama dengan tanggal mulai Semester 2.',
            'libur_mingguan.*.hari.required' => 'Hari libur mingguan wajib dipilih.',
            'libur_mingguan.*.hari.in' => 'Hari yang dipilih tidak valid.',
            'libur_mingguan.*.keterangan.required' => 'Keterangan hari libur mingguan wajib diisi.',
            'libur_mingguan.*.keterangan.max' => 'Keterangan hari libur mingguan maksimal 255 karakter.',
            'libur_nasional.*.tanggal.required' => 'Tanggal libur nasional wajib diisi.',
            'libur_nasional.*.tanggal.date' => 'Tanggal libur nasional harus berupa tanggal yang valid.',
            'libur_nasional.*.tanggal.date_format' => 'Tanggal libur nasional harus berupa tanggal yang valid (format: dd/mm/yyyy).',
            'libur_nasional.*.nama_libur.required' => 'Nama hari libur nasional wajib diisi.',
            'libur_nasional.*.nama_libur.max' => 'Nama hari libur nasional maksimal 255 karakter.',
            'libur_nasional.*.tanggal.after_or_equal' => 'Tanggal libur nasional harus berada dalam rentang periode.',
            'libur_nasional.*.tanggal.before_or_equal' => 'Tanggal libur nasional harus berada dalam rentang periode.',
        ]);

        // Normalisasi seluruh batas semester ke format database Y-m-d.
        $validated['semester_1_tanggal_mulai'] = $this->parseDate($validated['semester_1_tanggal_mulai'])->format('Y-m-d');
        $validated['semester_1_tanggal_selesai'] = $this->parseDate($validated['semester_1_tanggal_selesai'])->format('Y-m-d');
        $validated['semester_2_tanggal_mulai'] = $this->parseDate($validated['semester_2_tanggal_mulai'])->format('Y-m-d');
        $validated['semester_2_tanggal_selesai'] = $this->parseDate($validated['semester_2_tanggal_selesai'])->format('Y-m-d');

        // Normalisasi tanggal nasional sebelum data diteruskan ke transaksi penyimpanan.
        foreach ($validated['libur_nasional'] ?? [] as &$libur) {
            $libur['tanggal'] = $this->parseDate($libur['tanggal'])->format('Y-m-d');
        }

        // Buat dua semester dan hari liburnya sebagai satu kesatuan atomik.
        DB::transaction(function () use ($validated): void {
            // Bersihkan spasi pada tahun ajaran agar nama dan pencarian periode konsisten.
            $tahunAjaran = trim($validated['tahun_ajaran']);

            $semester1Start = $validated['semester_1_tanggal_mulai'];
            $semester1End = $validated['semester_1_tanggal_selesai'];
            $semester2Start = $validated['semester_2_tanggal_mulai'];
            $semester2End = $validated['semester_2_tanggal_selesai'];

            // Simpan Semester 1 menggunakan rentang tanggal yang telah divalidasi.
            Periode::create([
                'tahun_ajaran' => $tahunAjaran,
                'semester' => 1,
                'tipe_periode' => 'semester',
                'nama_periode' => "Semester Ganjil {$tahunAjaran}",
                'tanggal_mulai' => $semester1Start,
                'tanggal_selesai' => $semester1End,
            ]);

            // Simpan Semester 2 menggunakan rentang tanggal yang telah divalidasi.
            Periode::create([
                'tahun_ajaran' => $tahunAjaran,
                'semester' => 2,
                'tipe_periode' => 'semester',
                'nama_periode' => "Semester Genap {$tahunAjaran}",
                'tanggal_mulai' => $semester2Start,
                'tanggal_selesai' => $semester2End,
            ]);

            // Ambil kembali kedua record untuk mendapatkan ID parent hari libur.
            $periode1 = Periode::query()->where('tahun_ajaran', $tahunAjaran)->where('semester', 1)->first();
            $periode2 = Periode::query()->where('tahun_ajaran', $tahunAjaran)->where('semester', 2)->first();


            // Simpan hari libur hanya ke semester yang rentangnya memuat tanggal libur.
            if ($periode1) {
                $this->storeHariLiburs($periode1, $validated);
            }
            if ($periode2) {
                $this->storeHariLiburs($periode2, $validated);
            }
        });

        return redirect()->route('periode.index')->with('success', 'Periode akademik Semester 1 dan Semester 2 berhasil disimpan.');
    }

    /**
     * Perbarui konfigurasi tahun ajaran, semester, hari libur, dan data terkait.
     *
     * Semua baris periode dikunci selama transaksi agar pembaruan dua semester
     * tidak saling bertabrakan ketika ada request bersamaan.
     */
    public function update(Request $request, $id)
    {
        // Pastikan periode yang menjadi target memang ada sebelum memproses form.
        $periode = Periode::findOrFail($id);
        // Validasi ulang seluruh konfigurasi, termasuk celah antarsemester untuk hari nasional.
        $validated = $request->validate([
            'tahun_ajaran' => [
                'required',
                'string',
                'max:20',
            ],
            'semester_1_tanggal_mulai' => ['required', 'date'],
            'semester_1_tanggal_selesai' => ['required', 'date', 'after_or_equal:semester_1_tanggal_mulai'],
            'semester_2_tanggal_mulai' => ['required', 'date', 'after_or_equal:semester_1_tanggal_selesai'],
            'semester_2_tanggal_selesai' => ['required', 'date', 'after_or_equal:semester_2_tanggal_mulai'],
            'libur_mingguan' => ['nullable', 'array'],
            'libur_mingguan.*' => ['array'],
            'libur_mingguan.*.hari' => [
                'required',
                'string',
                Rule::in(['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu']),
                'distinct',
            ],
            'libur_mingguan.*.keterangan' => ['required', 'string', 'max:255'],
            'libur_nasional' => ['nullable', 'array'],
            'libur_nasional.*' => ['array'],
            'libur_nasional.*.tanggal' => [
                'required',
                'date',
                $this->nationalHolidayDateRule($request),
                'distinct',
            ],
            'libur_nasional.*.nama_libur' => ['required', 'string', 'max:255'],
            'libur_nasional.*.keterangan' => ['nullable', 'string', 'max:255'],
        ], [
            'tahun_ajaran.required' => 'Tahun ajaran wajib diisi.',
            'tahun_ajaran.max' => 'Tahun ajaran maksimal 20 karakter.',
            'tahun_ajaran.unique' => 'Tahun ajaran ini sudah terdaftar.',
            'semester_1_tanggal_mulai.required' => 'Tanggal mulai Semester 1 wajib diisi.',
            'semester_1_tanggal_mulai.date_format' => 'Tanggal mulai Semester 1 harus berupa tanggal yang valid (format: dd/mm/yyyy).',
            'semester_1_tanggal_selesai.required' => 'Tanggal selesai Semester 1 wajib diisi.',
            'semester_1_tanggal_selesai.date_format' => 'Tanggal selesai Semester 1 harus berupa tanggal yang valid (format: dd/mm/yyyy).',
            'semester_1_tanggal_selesai.after_or_equal' => 'Tanggal selesai Semester 1 harus setelah atau sama dengan tanggal mulai Semester 1.',
            'semester_2_tanggal_mulai.required' => 'Tanggal mulai Semester 2 wajib diisi.',
            'semester_2_tanggal_mulai.date_format' => 'Tanggal mulai Semester 2 harus berupa tanggal yang valid (format: dd/mm/yyyy).',
            'semester_2_tanggal_mulai.after_or_equal' => 'Tanggal mulai Semester 2 harus setelah atau sama dengan tanggal selesai Semester 1.',
            'semester_2_tanggal_selesai.required' => 'Tanggal selesai Semester 2 wajib diisi.',
            'semester_2_tanggal_selesai.date_format' => 'Tanggal selesai Semester 2 harus berupa tanggal yang valid (format: dd/mm/yyyy).',
            'semester_2_tanggal_selesai.after_or_equal' => 'Tanggal selesai Semester 2 harus setelah atau sama dengan tanggal mulai Semester 2.',
            'libur_mingguan.*.hari.required' => 'Hari libur mingguan wajib dipilih.',
            'libur_mingguan.*.hari.in' => 'Hari yang dipilih tidak valid.',
            'libur_mingguan.*.keterangan.required' => 'Keterangan hari libur mingguan wajib diisi.',
            'libur_mingguan.*.keterangan.max' => 'Keterangan hari libur mingguan maksimal 255 karakter.',
            'libur_nasional.*.tanggal.required' => 'Tanggal libur nasional wajib diisi.',
            'libur_nasional.*.tanggal.date' => 'Tanggal libur nasional harus berupa tanggal yang valid.',
            'libur_nasional.*.tanggal.date_format' => 'Tanggal libur nasional harus berupa tanggal yang valid (format: dd/mm/yyyy).',
            'libur_nasional.*.nama_libur.required' => 'Nama hari libur nasional wajib diisi.',
            'libur_nasional.*.nama_libur.max' => 'Nama hari libur nasional maksimal 255 karakter.',
            'libur_nasional.*.tanggal.after_or_equal' => 'Tanggal libur nasional harus berada dalam rentang periode.',
            'libur_nasional.*.tanggal.before_or_equal' => 'Tanggal libur nasional harus berada dalam rentang periode.',
        ]);

        // Normalisasi batas semester ke format Y-m-d sebelum update database.
        $validated['semester_1_tanggal_mulai'] = $this->parseDate($validated['semester_1_tanggal_mulai'])->format('Y-m-d');
        $validated['semester_1_tanggal_selesai'] = $this->parseDate($validated['semester_1_tanggal_selesai'])->format('Y-m-d');
        $validated['semester_2_tanggal_mulai'] = $this->parseDate($validated['semester_2_tanggal_mulai'])->format('Y-m-d');
        $validated['semester_2_tanggal_selesai'] = $this->parseDate($validated['semester_2_tanggal_selesai'])->format('Y-m-d');

        // Normalisasi tanggal setiap hari libur nasional agar konsisten dengan kolom date.
        foreach ($validated['libur_nasional'] ?? [] as &$libur) {
            $libur['tanggal'] = $this->parseDate($libur['tanggal'])->format('Y-m-d');
        }

        // Perbarui periode, hari libur, dan pembersihan absensi secara atomik.
        DB::transaction(function () use ($id, $validated): void {
            // Kunci seluruh daftar periode untuk mencegah update semester bersamaan.
            Periode::query()->orderBy('id')->lockForUpdate()->get(['id']);
            // Ambil ulang record target setelah lock diterapkan.
            $lockedPeriode = Periode::query()->findOrFail($id);
            $tahunAjaran = trim($validated['tahun_ajaran']);
            $tahunAjaranLama = $lockedPeriode->tahun_ajaran;

            // Cari kedua semester lama berdasarkan tahun ajaran sebelum perubahan.
            $semester1 = Periode::query()
                ->where('tahun_ajaran', $tahunAjaranLama)
                ->where('semester', 1)
                ->lockForUpdate()
                ->first();
            $semester2 = Periode::query()
                ->where('tahun_ajaran', $tahunAjaranLama)
                ->where('semester', 2)
                ->lockForUpdate()
                ->first();

            if ($semester1) {
                // Simpan snapshot lama untuk activity log sebelum record diubah.
                $oldData = $semester1->load('hariLiburs')->toArray(); //Ini menyimpan kondisi sebelum diubah.load()Memuat relasi hariLiburs dari periode tersebut.
                // Perbarui metadata dan rentang Semester 1.
                $semester1->update([
                    'tahun_ajaran' => $tahunAjaran,
                    'nama_periode' => "Semester Ganjil {$tahunAjaran}",
                    'tanggal_mulai' => $validated['semester_1_tanggal_mulai'],
                    'tanggal_selesai' => $validated['semester_1_tanggal_selesai'],
                ]);
                // Hapus daftar hari libur lama agar tidak menyisakan konfigurasi usang.
                $semester1->hariLiburs()->delete();
                // Simpan ulang hari libur terbaru sesuai rentang semester.
                $this->storeHariLiburs($semester1, $validated); //Simpan hari libur dari form terbaru
                $this->logUpdate(
                    'Periode',
                    $semester1,
                    ['old' => $oldData, 'new' => $semester1->fresh('hariLiburs')->toArray()],
                    "Memperbarui periode {$semester1->namaLengkap()}"
                );
            } else {
                // Jika Semester 1 belum ada, buat record baru beserta hari liburnya.
                $semester1 = Periode::create([
                    'tahun_ajaran' => $tahunAjaran,
                    'semester' => 1,
                    'tipe_periode' => 'semester',
                    'nama_periode' => "Semester Ganjil {$tahunAjaran}",
                    'tanggal_mulai' => $validated['semester_1_tanggal_mulai'],
                    'tanggal_selesai' => $validated['semester_1_tanggal_selesai'],
                ]);

                $this->storeHariLiburs($semester1, $validated);
                $this->logCreate(
                    'Periode',
                    $semester1->fresh('hariLiburs'),
                    "Menambahkan periode {$semester1->namaLengkap()}"
                );
            }

            if ($semester2) {
                // Simpan snapshot lama Semester 2 untuk kebutuhan audit perubahan.
                $oldData = $semester2->load('hariLiburs')->toArray(); ///Ini menyimpan kondisi sebelum diubah.load()Memuat relasi hariLiburs dari periode tersebut.
                // Perbarui metadata dan rentang Semester 2.
                $semester2->update([
                    'tahun_ajaran' => $tahunAjaran,
                    'nama_periode' => "Semester Genap {$tahunAjaran}",
                    'tanggal_mulai' => $validated['semester_2_tanggal_mulai'],
                    'tanggal_selesai' => $validated['semester_2_tanggal_selesai'],
                ]);
                // Ganti konfigurasi hari libur lama dengan data terbaru dari form.
                $semester2->hariLiburs()->delete();
                $this->storeHariLiburs($semester2, $validated); ////Simpan hari libur dari form terbaru
                $this->logUpdate(
                    'Periode',
                    $semester2,
                    ['old' => $oldData, 'new' => $semester2->fresh('hariLiburs')->toArray()],
                    "Memperbarui periode {$semester2->namaLengkap()}"
                );
            } else {
                // Jika Semester 2 belum ada, buat record baru beserta hari liburnya.
                $semester2 = Periode::create([
                    'tahun_ajaran' => $tahunAjaran,
                    'semester' => 2,
                    'tipe_periode' => 'semester',
                    'nama_periode' => "Semester Genap {$tahunAjaran}",
                    'tanggal_mulai' => $validated['semester_2_tanggal_mulai'],
                    'tanggal_selesai' => $validated['semester_2_tanggal_selesai'],
                ]);

                $this->storeHariLiburs($semester2, $validated);
                $this->logCreate(
                    'Periode',
                    $semester2->fresh('hariLiburs'),
                    "Menambahkan periode {$semester2->namaLengkap()}"
                );
            }

            // Ambil ID kedua semester yang baru agar pembersihan absensi terbatas pada tahun ajaran ini.
            $periodeIds = Periode::query()
                ->where('tahun_ajaran', $tahunAjaran)
                ->whereIn('semester', [1, 2])
                ->pluck('id');

            $tanggalMulaiPeriode = $validated['semester_1_tanggal_mulai'];
            $tanggalSelesaiPeriode = $validated['semester_2_tanggal_selesai'];

            // Hapus absensi milik kedua semester yang berada di luar rentang tahun ajaran baru.
            Absensi::query()
                ->whereIn('periode_id', $periodeIds)
                ->where(function ($query) use ($tanggalMulaiPeriode, $tanggalSelesaiPeriode): void {
                    $query->whereDate('tanggal', '<', $tanggalMulaiPeriode)
                        ->orWhereDate('tanggal', '>', $tanggalSelesaiPeriode);
                })
                ->delete();
        });

        return redirect()->route('periode.index')->with('success', 'Periode akademik Semester 1 dan Semester 2 berhasil diperbarui.');
    }

    /**
     * Reset seluruh konfigurasi periode dan riwayat absensi.
     *
     * Penghapusan dilakukan dalam transaksi agar periode dan absensi tidak
     * berhenti pada kondisi setengah terhapus jika terjadi kegagalan database.
     */
    public function reset(Request $request)
    {
        // Hapus absensi lebih dahulu, kemudian periode parent beserta hari liburnya.
        DB::transaction(function (): void {
            Absensi::query()->delete();
            Periode::query()->delete();
        });

        return redirect()->route('periode.index')->with('success', 'Semua data periode dan absensi berhasil direset.');
    }

    /**
     * Simpan data hari libur (mingguan & nasional) untuk periode
     * 
     * @param  array<string, mixed> $validated
     */
    private function storeHariLiburs(Periode $periode, array $validated): void
    {
        // Simpan aturan mingguan sebagai data berulang untuk semester tersebut.
        foreach ($validated['libur_mingguan'] ?? [] as $libur) {
            $periode->hariLiburs()->create([
                'tipe' => 'mingguan',
                'hari' => $libur['hari'],
                'keterangan' => $libur['keterangan'],
            ]);
        }

        // Simpan libur nasional hanya jika tanggalnya berada di dalam periode parent.
        foreach ($validated['libur_nasional'] ?? [] as $libur) {
            $tanggal = Carbon::parse($libur['tanggal']);

            if ($tanggal->lt($periode->tanggal_mulai) || $tanggal->gt($periode->tanggal_selesai)) {
                // Lewati tanggal pada celah antarsemester agar tidak terikat ke periode yang salah.
                continue;
            }

            $periode->hariLiburs()->create([
                'tipe' => 'nasional',
                'tanggal' => $libur['tanggal'],
                'nama_libur' => $libur['nama_libur'],
                'keterangan' => $libur['keterangan'] ?? '',
            ]);
        }
    }

    /**
     * Pastikan hari libur nasional berada di salah satu rentang semester.
     */
    private function nationalHolidayDateRule(Request $request): Closure
    {
        // Closure ini dipakai oleh validator untuk membaca empat batas semester dari request.
        return function (string $attribute, mixed $value, Closure $fail) use ($request): void {
            try {
                // Parse tanggal hari libur dan seluruh batas semester untuk perbandingan inklusif.
                $tanggal = Carbon::parse((string) $value);
                $semester1Mulai = Carbon::parse($request->input('semester_1_tanggal_mulai'));
                $semester1Selesai = Carbon::parse($request->input('semester_1_tanggal_selesai'));
                $semester2Mulai = Carbon::parse($request->input('semester_2_tanggal_mulai'));
                $semester2Selesai = Carbon::parse($request->input('semester_2_tanggal_selesai'));
            } catch (\Throwable) {
                // Biarkan rule date bawaan Laravel melaporkan format tanggal yang tidak valid.
                return;
            }

            // Hari libur sah jika berada di Semester 1 atau Semester 2, bukan di celah keduanya.
            $diSemester1 = $tanggal->betweenIncluded($semester1Mulai, $semester1Selesai);
            $diSemester2 = $tanggal->betweenIncluded($semester2Mulai, $semester2Selesai);

            if (! $diSemester1 && ! $diSemester2) {
                // Tolak tanggal sebelum Semester 1, setelah Semester 2, dan di antara keduanya.
                $fail('Tanggal libur nasional harus berada di dalam rentang Semester 1 atau Semester 2.');
            }
        };
    }

    /**
     * Parse string tanggal support format d/m/Y dan Y-m-d
     */
    private function parseDate(string $date): Carbon
    {
        // Tangani format tanggal Indonesia secara eksplisit sebelum mencoba parser umum Carbon.
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
            return Carbon::createFromFormat('d/m/Y', $date);
        }
        // Format ISO/database dan format Carbon lain ditangani oleh parser umum.
        return Carbon::parse($date);
    }
}
