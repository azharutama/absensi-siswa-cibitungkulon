<?php

namespace App\Http\Controllers;

use App\Exports\RekapAbsensiExport;
use App\Http\Controllers\Concerns\AutoSelectsSingleKelas;
use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Kelas;
use App\Models\Periode;
use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RekapController extends Controller
{
    use AutoSelectsSingleKelas;

    /**
     * Tampilkan halaman rekap absensi dengan filter
     * 
     * Fitur:
     * - Filter berdasarkan kelas
     * - Filter berdasarkan preset tanggal (hari ini, minggu ini, bulan ini, semester)
     * - Filter custom range tanggal
     * - Auto-redirect jika user hanya punya akses 1 kelas
     * 
     * @param  Request  $request  Request dengan filter kelas_id, preset, tanggal_mulai, tanggal_berakhir
     * @return View|RedirectResponse  View rekap atau redirect auto-select
     */
    public function index(Request $request): View|RedirectResponse
    {
        // Validasi filter dari query string sebelum dipakai untuk query kelas dan absensi.
        // Tanggal akhir juga dipastikan tidak mendahului tanggal awal.
        $filters = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'preset' => ['nullable', 'string', 'in:today,this_week,this_month,semester_1,semester_2,custom'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_berakhir' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        // Ambil hanya kelas yang boleh diakses user berdasarkan role dan relasi aksesnya.
        $kelas = $this->accessibleKelas($request);

        // Jika hanya satu kelas yang tersedia, pilih otomatis agar dropdown tidak perlu diisi.
        if ($redirect = $this->autoRedirectForSingleKelas($request, $kelas, 'rekap.index', ['preset' => $filters['preset'] ?? 'this_month'])) {
            return $redirect;
        }

        // Hitung data rekap, statistik, serta state tampilan sebelum mengirim view.
        return view('rekap.index', $this->rekapData($request, $filters, $kelas));
    }

    /**
     * Download rekap absensi dalam format Excel
     * 
     * File Excel berisi:
     * - Header dengan nama kelas dan range tanggal
     * - Tabel rekap per siswa (nama, hadir, sakit, izin, alpa, persentase)
     * - Total dan statistik keseluruhan
     * 
     * @param  Request  $request  Request dengan filter yang sama seperti index()
     * @return BinaryFileResponse  File Excel untuk download
     */
    public function export(Request $request): BinaryFileResponse
    {
        // Gunakan pipeline data yang sama dengan halaman rekap agar hasil export konsisten.
        $data = $this->rekapData($request);

        // File tidak dapat dibuat jika pengguna belum memilih kelas.
        abort_unless($data['kelasId'], 422, 'Pilih kelas terlebih dahulu sebelum mengunduh rekap.');

        // Bentuk nama file yang aman menggunakan slug kelas dan rentang tanggal laporan.
        $filename = sprintf(
            'rekap-absensi-%s-%s-sampai-%s.xlsx',
            Str::slug($data['namaKelas']), // Slug untuk safe filename (spasi jadi -)
            $data['tanggalMulai'], // Format Y-m-d
            $data['tanggalBerakhir'],
        );

        // Delegasikan pembuatan workbook ke class export khusus.
        return Excel::download(
            new RekapAbsensiExport(
                $data['rekapSiswa'], // Data per siswa
                $data['namaKelas'], // Nama kelas untuk header
                $data['tanggalMulai'], // Range tanggal untuk header
                $data['tanggalBerakhir'],
                count($this->getActiveDatesForRange($data['tanggalMulai'], $data['tanggalBerakhir'])), // Total hari aktif
            ),
            $filename,
        );
    }

    /**
     * Proses dan hitung data rekap absensi untuk ditampilkan
     * 
     * Method ini adalah core logic untuk:
     * 1. Menentukan range tanggal (dari preset atau custom)
     * 2. Mengambil data siswa dan absensi mereka
     * 3. Menghitung statistik per siswa (hadir, sakit, izin, alpa, persentase)
     * 4. Menghitung statistik keseluruhan kelas
     * 5. Menentukan apakah tabel ditampilkan atau disembunyikan
     * 
     * @param  Request  $request  Request object
     * @param  array|null  $filters  Filter yang sudah divalidasi (optional)
     * @param  Collection|null  $kelas  Daftar kelas accessible (optional)
     * @return array{
     *   kelas: Collection<int, Kelas>,
     *   rekapSiswa: array<int, array{nama_siswa: string, nama_kelas: string, hadir: int, sakit: int, izin: int, alpa: int, total_tidak_masuk: int, persentase: float}>,
     *   totalHariAktif: int,
     *   totalHariAbsensi: int,
     *   kelasId: int|string|null,
     *   tanggalMulai: string,
     *   tanggalBerakhir: string,
     *   tanggalMulaiDisplay: string,
     *   tanggalBerakhirDisplay: string,
     *   stats: array{rata_hadir: float|int, total_hadir: int, total_sakit: int, total_izin: int, total_alpa: int, total_tidak_masuk: int, persentase_hadir: float|int, persentase_sakit: float|int, persentase_izin: float|int, persentase_alpa: float|int},
     *   namaKelas: string|null,
     *   preset: string|null,
     *   hideRekapTabel: bool
     * }
     */
    private function rekapData(Request $request, ?array $filters = null, ?Collection $kelas = null): array
    {
        // Export dapat memanggil method ini tanpa membawa hasil validasi dari index(),
        // sehingga request tetap divalidasi di sini sebagai pengaman.
        $filters ??= $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'preset' => ['nullable', 'string', 'in:today,this_week,this_month,semester_1,semester_2,custom'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_berakhir' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        // Gunakan daftar kelas yang sudah tersedia atau ambil ulang berdasarkan akses user.
        $kelas ??= $this->accessibleKelas($request);

        // Gunakan bulan berjalan sebagai preset default jika pengguna belum memilih preset.
        $preset = $filters['preset'] ?? 'this_month';

        // Trait memilih kelas otomatis ketika user hanya memiliki satu kelas yang accessible.
        $kelasId = $this->getKelasIdWithAutoSelect($filters['kelas_id'] ?? null, $kelas);

        // Tentukan rentang laporan dari tanggal custom atau preset bawaan aplikasi.
        if ($preset === 'custom') {
            // Preset custom memakai tanggal manual, dengan fallback ke bulan berjalan.
            $tanggalMulaiInput = $filters['tanggal_mulai'] ?? today()->startOfMonth()->format('d/m/Y');
            $tanggalBerakhirInput = $filters['tanggal_berakhir'] ?? today()->format('d/m/Y');

            // Normalisasi tanggal ke format database Y-m-d sebelum query.
            $tanggalMulai = $this->parseDate($tanggalMulaiInput)->format('Y-m-d');
            $tanggalBerakhir = $this->parseDate($tanggalBerakhirInput)->format('Y-m-d');
        } else {
            // Preset otomatis menentukan tanggal berdasarkan hari, minggu, bulan, atau semester.
            [$tanggalMulai, $tanggalBerakhir] = $this->getPresetDateRange($preset);
        }

        // Siapkan wadah hasil rekap dan state tampilan sebelum kelas diproses.
        $rekapSiswa = []; // Array rekap per siswa
        $namaKelas = null; // Nama kelas yang dipilih
        $totalHariAktif = 0; // Total hari aktif dalam 1 tahun ajaran (semester 1 + 2)
        $totalHariAbsensi = 0; // Total hari yang ada data absensi (dalam range filter)
        $hideRekapTabel = false; // Flag apakah tabel disembunyikan (jika tidak ada data)

        // Statistik dimulai dari nol agar view tetap memiliki struktur data yang konsisten.
        $stats = [
            'rata_hadir' => 0, // Rata-rata persentase kehadiran kelas
            'total_sakit' => 0,
            'total_izin' => 0,
            'total_alpa' => 0,
        ];

        if ($kelasId) {
            // Pastikan kelas yang diminta memang termasuk daftar kelas yang boleh diakses user.
            $selectedKelas = $kelas->firstWhere('id', (int) $kelasId);

            abort_if($selectedKelas === null, 404);

            // Ambil tanggal sekolah aktif setelah mengeluarkan hari libur nasional dan mingguan.
            $activeDates = $this->getActiveDatesForRange($tanggalMulai, $tanggalBerakhir);

            // Ambil siswa kelas, termasuk siswa yang memiliki riwayat absensi di rentang laporan.
            $siswas = Siswa::query()
                ->select(['id', 'nama_siswa'])
                ->where(function ($query) use ($kelasId, $tanggalBerakhir, $tanggalMulai): void {
                    $query->where('kelas_id', $kelasId)
                        ->orWhereHas('absensis', function ($query) use ($kelasId, $tanggalBerakhir, $tanggalMulai): void {
                            $query->where('kelas_id', $kelasId)
                                ->whereBetween('tanggal', [$tanggalMulai, $tanggalBerakhir]);
                        });
                })
                ->orderBy('nama_siswa')
                ->get();

            // Hitung jumlah setiap status langsung di database agar tidak memproses seluruh
            // baris absensi satu per satu di PHP.
            $absensiTotals = Absensi::query()
                ->select('siswa_id')
                ->selectRaw("SUM(CASE WHEN status = 'hadir' THEN 1 ELSE 0 END) AS hadir")
                ->selectRaw("SUM(CASE WHEN status = 'sakit' THEN 1 ELSE 0 END) AS sakit")
                ->selectRaw("SUM(CASE WHEN status = 'izin' THEN 1 ELSE 0 END) AS izin")
                ->selectRaw("SUM(CASE WHEN status = 'alpa' THEN 1 ELSE 0 END) AS alpa")
                ->whereBetween('tanggal', [$tanggalMulai, $tanggalBerakhir])
                ->whereIn('tanggal', $activeDates)
                ->whereIn('siswa_id', $siswas->pluck('id'))
                ->groupBy('siswa_id')
                ->get()
                ->keyBy('siswa_id');

            // Jumlah hari aktif pada filter menjadi penyebut persentase setiap siswa.
            $totalHariAktifFilter = count($activeDates);

            // Hitung juga total hari aktif seluruh periode untuk kebutuhan ringkasan laporan.
            $totalHariAktif = $this->hitungHariAktifPeriode();

            $totalHariAbsensi = Absensi::query()
                ->where('kelas_id', $kelasId)
                ->whereBetween('tanggal', [$tanggalMulai, $tanggalBerakhir])
                ->whereIn('tanggal', $activeDates)
                ->distinct() //Mencegah tanggal yang sama dihitung berkali-kali.
                ->count('tanggal');

            // Siapkan akumulator statistik seluruh siswa dalam kelas.
            $totalPersentaseSemuaSiswa = 0;
            $totalHadir = 0;
            $totalSakit = 0;
            $totalIzin = 0;
            $totalAlpa = 0;
            $totalTidakMasuk = 0;
            $namaKelas = $selectedKelas->nama_kelas;
            $jumlahSiswa = $siswas->count();

            foreach ($siswas as $siswa) {
                // Siswa tanpa absensi tetap dimasukkan ke laporan dengan nilai status nol.
                $totals = $absensiTotals->get($siswa->id);
                $hadir = $totals->hadir ?? 0; //Jika tidak ditemukan data, nilainya:0
                $sakit = $totals->sakit ?? 0; //?? adalah null coalescing operator.Artinya:kalau nilai kiri tidak ada/null→ gunakan nilai kanan
                $izin = $totals->izin ?? 0;
                $alpa = $totals->alpa ?? 0;

                $tidakMasuk = $sakit + $izin + $alpa; //Menghitung tidak masuk
                $persentase = $totalHariAktifFilter > 0 ? round(($hadir / $totalHariAktifFilter) * 100, 1) : 0; //Menghitung persentase siswa
                $persentase = min($persentase, 100);

                // Bentuk satu baris rekap yang dipakai oleh view dan export Excel.
                $rekapSiswa[] = [
                    'nama_siswa' => $siswa->nama_siswa,
                    'nama_kelas' => $namaKelas,
                    'hadir' => $hadir,
                    'sakit' => $sakit,
                    'izin' => $izin,
                    'alpa' => $alpa,
                    'total_tidak_masuk' => $tidakMasuk,
                    'persentase' => $persentase,
                ];

                $totalPersentaseSemuaSiswa += $persentase;

                // Tambahkan hasil siswa ke statistik keseluruhan kelas.
                $totalHadir += $hadir; //$totalHadir = $totalHadir + $hadir;
                $totalSakit += $sakit;
                $totalIzin += $izin;
                $totalAlpa += $alpa;
                $totalTidakMasuk += $tidakMasuk;
            }

            // Total kesempatan hadir kelas = jumlah hari aktif dikali jumlah siswa.
            $totalHariKerjaKelas = $totalHariAktifFilter * $jumlahSiswa;

            // Rata-rata kelas dihitung dari persentase masing-masing siswa.
            if ($jumlahSiswa > 0) {
                $stats['rata_hadir'] = round($totalPersentaseSemuaSiswa / $jumlahSiswa, 1);
            }

            // Persentase keseluruhan memakai total kesempatan hadir seluruh kelas sebagai penyebut.
            $stats['total_hadir'] = $totalHadir;
            $stats['total_sakit'] = $totalSakit;
            $stats['total_izin'] = $totalIzin;
            $stats['total_alpa'] = $totalAlpa;
            $stats['total_tidak_masuk'] = $totalTidakMasuk;
            $stats['persentase_hadir'] = $totalHariKerjaKelas > 0 ? min(round(($totalHadir / $totalHariKerjaKelas) * 100, 1), 100) : 0;
            $stats['persentase_sakit'] = $totalHariKerjaKelas > 0 ? min(round(($totalSakit / $totalHariKerjaKelas) * 100, 1), 100) : 0;
            $stats['persentase_izin'] = $totalHariKerjaKelas > 0 ? min(round(($totalIzin / $totalHariKerjaKelas) * 100, 1), 100) : 0;
            $stats['persentase_alpa'] = $totalHariKerjaKelas > 0 ? min(round(($totalAlpa / $totalHariKerjaKelas) * 100, 1), 100) : 0;
            $stats['persentase_tidak_masuk'] = $totalHariKerjaKelas > 0
                ? min(round(($totalTidakMasuk / $totalHariKerjaKelas) * 100, 1), 100)
                : 0;
        }

        // Sembunyikan tabel jika kelas dipilih tetapi belum ada absensi dalam rentang tersebut.
        if ($kelasId && $totalHariAbsensi === 0) {
            $hideRekapTabel = true;
        }

        // Pertahankan format ISO untuk query/export dan format Indonesia untuk tampilan.
        $tanggalMulaiDisplay = Carbon::parse($tanggalMulai)->format('d/m/Y');
        $tanggalBerakhirDisplay = Carbon::parse($tanggalBerakhir)->format('d/m/Y');


        // Kembalikan seluruh data yang dibutuhkan view rekap dan proses export.
        return compact('kelas', 'rekapSiswa', 'totalHariAktif', 'totalHariAbsensi', 'kelasId', 'tanggalMulai', 'tanggalBerakhir', 'tanggalMulaiDisplay', 'tanggalBerakhirDisplay', 'stats', 'namaKelas', 'preset', 'hideRekapTabel');
    }

    /**
     * Ambil kelas yang dapat diakses oleh user yang sedang login.
     *
     * Aturan akses tetap dipusatkan pada scope model agar controller tidak
     * menggandakan logika berbeda untuk operator dan guru.
     *
     * @return Collection<int, Kelas>
     */
    private function accessibleKelas(Request $request): Collection
    {
        // Scope accessibleBy membatasi kelas sesuai role dan relasi user.
        // Hanya kolom yang dibutuhkan dropdown yang diambil dari database.
        return Kelas::query()
            ->accessibleBy($request->user())
            ->select(['id', 'nama_kelas'])
            ->orderBy('nama_kelas')
            ->get();
    }

    /**
     * Ambil rentang tanggal berdasarkan preset filter
     * 
     * @return array{0: string, 1: string} Format Y-m-d
     */
    private function getPresetDateRange(string $preset): array
    {

        // Gunakan tanggal hari ini sebagai titik acuan seluruh preset kalender.
        $today = today();

        return match ($preset) {
            'today' => [
                $today->toDateString(),
                $today->toDateString()
            ],
            'this_week' => [
                $today->copy()->startOfWeek()->toDateString(),
                $today->copy()->endOfWeek()->toDateString()
            ],
            'this_month' => [
                $today->copy()->startOfMonth()->toDateString(),
                $today->copy()->endOfMonth()->toDateString()
            ],
            'semester_1' => $this->getSemesterDateRange(1),
            'semester_2' => $this->getSemesterDateRange(2),
            default => [
                $today->copy()->startOfMonth()->toDateString(),
                $today->toDateString()
            ],
        };
    }

    /**
     * Ambil rentang tanggal semester dari periode aktif
     * 
     * @return array{0: string, 1: string} Format Y-m-d
     */
    private function getSemesterDateRange(int $semester): array
    {

        // Ambil konfigurasi semester terbaru sebagai rentang preset laporan.
        $periode = Periode::query()
            ->where('semester', $semester)
            ->latest('id')
            ->first();

        if (! $periode) {
            // Jika semester belum dikonfigurasi, kembalikan rentang aman agar format hasil valid.
            return [today()->toDateString(), today()->toDateString()];
        }

        return [
            $periode->tanggal_mulai->toDateString(),
            $periode->tanggal_selesai->toDateString(),
        ];
    }

    /**
     * Hitung jumlah hari aktif dari seluruh tahun ajaran (semester 1 + semester 2)
     * (tidak terpengaruh filter tanggal)
     */
    private function hitungHariAktifPeriode(): int //menghitung jumlah hari aktif berdasarkan periode semester.
    {
        // Ambil maksimal dua semester terbaru dalam satu query untuk menghindari query berulang.
        $periodes = Periode::query()
            ->orderBy('tahun_ajaran', 'desc')
            ->orderBy('semester', 'asc')
            ->limit(2)
            ->get();

        if ($periodes->isEmpty()) {
            // Tanpa periode akademik, tidak ada hari aktif yang dapat dihitung.
            return 0;
        }

        $periodeSemester1 = $periodes->firstWhere('semester', 1);
        $periodeSemester2 = $periodes->firstWhere('semester', 2);

        // Perlindungan tambahan jika hasil query tidak memuat semester yang diharapkan.
        if (!$periodeSemester1 && !$periodeSemester2) {
            return 0;
        }

        // Jika hanya semester 1 tersedia, hitung dari awal sampai akhir semester 1.
        if ($periodeSemester1 && !$periodeSemester2) {
            return $this->hitungHariAktif(
                $periodeSemester1->tanggal_mulai->toDateString(),
                $periodeSemester1->tanggal_selesai->toDateString()
            );
        }

        // Jika hanya semester 2 tersedia, gunakan rentang semester 2.
        if (!$periodeSemester1 && $periodeSemester2) {
            return $this->hitungHariAktif(
                $periodeSemester2->tanggal_mulai->toDateString(),
                $periodeSemester2->tanggal_selesai->toDateString()
            );
        }

        // Jika kedua semester tersedia, hitung seluruh tahun ajaran dari awal semester 1
        // sampai akhir semester 2. Hari libur dikeluarkan oleh hitungHariAktif().
        $tanggalMulai = $periodeSemester1->tanggal_mulai->toDateString();
        $tanggalBerakhir = $periodeSemester2->tanggal_selesai->toDateString();

        return $this->hitungHariAktif($tanggalMulai, $tanggalBerakhir);
    }

    /**
     * Hitung jumlah hari aktif (hari di mana guru dapat mengisi absensi)
     * dengan mengeluarkan hari libur mingguan dan nasional dalam rentang tanggal.
     */
    private function hitungHariAktif(string $tanggalMulai, string $tanggalBerakhir): int
    {
        // Normalisasi batas tanggal menjadi object Carbon untuk perbandingan dan iterasi.
        $mulai = Carbon::parse($tanggalMulai);
        $akhir = Carbon::parse($tanggalBerakhir);

        // Rentang terbalik tidak memiliki hari aktif.
        if ($akhir->lt($mulai)) {
            return 0;
        }

        // Ambil semua periode yang beririsan dengan rentang laporan agar konfigurasi
        // hari libur dari semester terkait ikut dipertimbangkan.
        $periodes = Periode::query()
            ->where(function ($query) use ($mulai, $akhir) { //Query ini mencari periode yang beririsan dengan tanggal yang sedang dihitung.
                $query->whereBetween('tanggal_mulai', [$mulai->toDateString(), $akhir->toDateString()]) //tanggal mulai periode berada dalam rentang.
                    ->orWhereBetween('tanggal_selesai', [$mulai->toDateString(), $akhir->toDateString()]) //tanggal selesai periode berada dalam rentang.
                    ->orWhere(function ($q) use ($mulai, $akhir) { //Periode mencakup seluruh rentang.
                        $q->where('tanggal_mulai', '<=', $mulai->toDateString())
                            ->where('tanggal_selesai', '>=', $akhir->toDateString());
                    });
            })
            ->get();

        $hariLiburNasional = collect();
        $hariLiburMingguan = collect();

        if ($periodes->isNotEmpty()) {
            // Gunakan ID periode yang relevan sebagai batas query hari libur.
            $periodeIds = $periodes->pluck('id');

            // Hari libur nasional berlaku untuk tanggal tertentu; normalisasi nilainya
            // agar aman dibandingkan dengan hasil iterasi Carbon.
            $hariLiburNasional = HariLibur::query()
                ->whereIn('periode_id', $periodeIds)
                ->where('tipe', 'nasional')
                ->whereBetween('tanggal', [$mulai->toDateString(), $akhir->toDateString()])
                ->pluck('tanggal')
                ->map(fn($t) => $t instanceof Carbon ? $t->toDateString() : Carbon::parse($t)->toDateString())
                ->unique()
                ->values();

            // Hari libur mingguan berulang setiap minggu, sehingga semua hari yang
            // dikonfigurasi pada periode relevan digabungkan dan dibuat unik.
            $hariLiburMingguan = HariLibur::query()
                ->whereIn('periode_id', $periodeIds)
                ->where('tipe', 'mingguan')
                ->pluck('hari')
                ->unique()
                ->values();
        }

        $namaHari = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];

        // Periksa setiap tanggal dan hitung hanya tanggal yang bukan hari libur.
        $hariAktif = 0;
        $hari = $mulai->copy();

        while ($hari->lte($akhir)) { //lte=less than or equal/selama hari <= tanggal akhir
            // Siapkan tanggal dan nama hari untuk pengecekan hari libur.
            $tanggalStr = $hari->toDateString();
            $namaHariIni = $namaHari[$hari->dayOfWeek];

            // Tanggal tidak aktif jika terkena libur nasional atau libur mingguan.
            $isHariLibur = $hariLiburNasional->contains($tanggalStr)
                || $hariLiburMingguan->contains($namaHariIni);

            if (! $isHariLibur) {
                $hariAktif++;
            }

            $hari->addDay();
        }

        return $hariAktif;
    }

    /**
     * Parse string tanggal support format d/m/Y dan Y-m-d
     */
    private function parseDate(string $date): Carbon
    {
        // Coba format Indonesia secara eksplisit sebelum parser umum Carbon.
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
            return Carbon::createFromFormat('d/m/Y', $date);
        }
        // Format ISO/database dan format Carbon lain diproses oleh parser umum.
        return Carbon::parse($date);
    }

    /**
     * Ambil tanggal aktif (bukan libur/akhir pekan) untuk rentang tanggal
     * di seluruh periode yang relevan
     * 
     * @return array<string> Format Y-m-d
     */

    //Ini mirip dengan hitungHariAktif(), tetapi ada perbedaan penting.Jadi bukan hanya jumlahnya, tetapi daftar tanggal aktif.
    private function getActiveDatesForRange(string $tanggalMulai, string $tanggalBerakhir): array
    {
        // Ubah batas rentang ke Carbon agar dapat dibandingkan dan diiterasikan.
        $mulai = Carbon::parse($tanggalMulai);
        $akhir = Carbon::parse($tanggalBerakhir);

        // Jangan masukkan tanggal masa depan karena absensi belum mungkin tersedia.
        $today = today();
        if ($akhir->gt($today)) { // gt = greater than
            $akhir = $today->copy(); //Membatasi tanggal akhir sampai hari ini
        }
        // Jika seluruh rentang berada di masa depan, daftar hari aktif dikosongkan.
        if ($mulai->gt($today)) { //Jika tanggal mulai juga masa depan
            return []; //hasilnya daftar tanggal aktif kosong.
        }

        // Ambil periode yang beririsan dengan rentang agar hari libur semester relevan.
        $periodes = Periode::query()
            ->where(function ($query) use ($mulai, $akhir) {
                $query->whereBetween('tanggal_mulai', [$mulai->toDateString(), $akhir->toDateString()])
                    ->orWhereBetween('tanggal_selesai', [$mulai->toDateString(), $akhir->toDateString()])
                    ->orWhere(function ($q) use ($mulai, $akhir) {
                        $q->where('tanggal_mulai', '<=', $mulai->toDateString())
                            ->where('tanggal_selesai', '>=', $akhir->toDateString());
                    });
            })
            ->get();

        // Ambil tanggal libur nasional dari periode yang beririsan dan normalisasi ke Y-m-d.
        $periodeIds = $periodes->pluck('id');
        $hariLiburNasional = HariLibur::whereIn('periode_id', $periodeIds)
            ->where('tipe', 'nasional')
            ->whereBetween('tanggal', [$mulai->toDateString(), $akhir->toDateString()])
            ->pluck('tanggal')
            ->map(fn($t) => Carbon::parse($t)->toDateString())
            ->unique()
            ->values();

        // Ambil semua hari mingguan yang dikonfigurasi pada periode terkait.
        $hariLiburMingguan = HariLibur::whereIn('periode_id', $periodeIds)
            ->where('tipe', 'mingguan')
            ->pluck('hari')
            ->unique()
            ->values();

        $namaHari = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];

        // Siapkan array hasil yang hanya berisi tanggal sekolah aktif.
        $activeDates = [];
        $hari = $mulai->copy();

        // Periksa satu per satu tanggal dalam rentang yang sudah dibatasi.
        while ($hari->lte($akhir)) {

            // Ambil tanggal dan nama hari untuk dibandingkan dengan daftar hari libur.
            $tanggalStr = $hari->toDateString();
            $namaHariIni = $namaHari[$hari->dayOfWeek];

            $isHariLibur = $hariLiburNasional->contains($tanggalStr)
                || $hariLiburMingguan->contains($namaHariIni);

            if (! $isHariLibur) {
                // Masukkan hanya tanggal yang bukan libur nasional maupun mingguan.
                $activeDates[] = $tanggalStr;
            }

            $hari->addDay(); //maka tanggal dimasukkan.
        }

        return $activeDates;
    }
}
