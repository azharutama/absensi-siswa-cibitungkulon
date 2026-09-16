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

    public function index(Request $request): View|RedirectResponse
    {
        $filters = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'preset' => ['nullable', 'string', 'in:today,this_week,this_month,semester_1,semester_2,custom'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_berakhir' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);
        $kelas = $this->accessibleKelas($request);

        if ($redirect = $this->autoRedirectForSingleKelas($request, $kelas, 'rekap.index', ['preset' => $filters['preset'] ?? 'this_month'])) {
            return $redirect;
        }

        return view('rekap.index', $this->rekapData($request, $filters, $kelas));
    }

    /**
     * Download rekap absensi yang sudah difilter sebagai file Excel.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $data = $this->rekapData($request);

        abort_unless($data['kelasId'], 422, 'Pilih kelas terlebih dahulu sebelum mengunduh rekap.'); //Hentikan proses jika kondisi yang diberikan tidak terpenuhi.


        $filename = sprintf(
            'rekap-absensi-%s-%s-sampai-%s.xlsx',
            Str::slug($data['namaKelas']),
            $data['tanggalMulai'],
            $data['tanggalBerakhir'],
        );

        return Excel::download(
            new RekapAbsensiExport(
                $data['rekapSiswa'],
                $data['namaKelas'],
                $data['tanggalMulai'],
                $data['tanggalBerakhir'],
                count($this->getActiveDatesForRange($data['tanggalMulai'], $data['tanggalBerakhir'])),
            ),
            $filename,
        );
    }

    /**
     * Proses data rekap absensi untuk ditampilkan di view.
     * 
     * @return array{kelas: Collection<int, Kelas>, rekapSiswa: array<int, array{nama_siswa: string, nama_kelas: string, hadir: int, sakit: int, izin: int, alpa: int, total_tidak_masuk: int, persentase: float}>, totalHariAktif: int, totalHariAbsensi: int, kelasId: int|string|null, tanggalMulai: string, tanggalBerakhir: string, stats: array{rata_hadir: float|int, total_hadir: int, total_sakit: int, total_izin: int, total_alpa: int, total_tidak_masuk: int, persentase_hadir: float|int, persentase_sakit: float|int, persentase_izin: float|int, persentase_alpa: float|int}, namaKelas: string|null, preset: string|null, hideRekapTabel: bool}
     */
    private function rekapData(Request $request, ?array $filters = null, ?Collection $kelas = null): array
    {
        $filters ??= $request->validate([ //Jika $filters masih null, isi dengan nilai di sebelah kanan.
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'preset' => ['nullable', 'string', 'in:today,this_week,this_month,semester_1,semester_2,custom'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_berakhir' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);
        $kelas ??= $this->accessibleKelas($request); //Kalau $kelas belum ada ambil accessibleKelas()

        $preset = $filters['preset'] ?? 'this_month'; //Jika user tidak memilih preset: this_month digunakan sebagai default.

        // Auto-select kelas menggunakan trait helper
        $kelasId = $this->getKelasIdWithAutoSelect($filters['kelas_id'] ?? null, $kelas);

        // Jika preset custom, gunakan tanggal manual
        if ($preset === 'custom') {
            $tanggalMulaiInput = $filters['tanggal_mulai'] ?? today()->startOfMonth()->format('d/m/Y');
            $tanggalBerakhirInput = $filters['tanggal_berakhir'] ?? today()->format('d/m/Y');
            $tanggalMulai = $this->parseDate($tanggalMulaiInput)->format('Y-m-d');
            $tanggalBerakhir = $this->parseDate($tanggalBerakhirInput)->format('Y-m-d');
        } else {
            // Gunakan preset tanggal
            [$tanggalMulai, $tanggalBerakhir] = $this->getPresetDateRange($preset);
        }

        //Menyiapkan variabel awal Tempat menyimpan hasil rekap setiap siswa.
        $rekapSiswa = [];
        $namaKelas = null;
        $totalHariAktif = 0;
        $totalHariAbsensi = 0;
        $hideRekapTabel = false;
        $stats = [ //Statistik awal
            'rata_hadir' => 0,
            'total_sakit' => 0,
            'total_izin' => 0,
            'total_alpa' => 0,
        ];

        if ($kelasId) { //Jika kelas tersedia
            $selectedKelas = $kelas->firstWhere('id', (int) $kelasId); //Mencari kelas yang dipilih

            abort_if($selectedKelas === null, 404);

            // Ambil tanggal aktif (hari sekolah) untuk rentang filter
            $activeDates = $this->getActiveDatesForRange($tanggalMulai, $tanggalBerakhir); //Fungsi getActiveDatesForRange menentukan tanggal mana yang dianggap sebagai hari aktif sekolah.

            //Mengambil siswa
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

            // Agregasi status absensi per siswa
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

            // Total hari aktif dalam rentang filter (untuk hitung persentase kehadiran)
            $totalHariAktifFilter = count($activeDates);

            // Total hari aktif dari seluruh periode (tidak terpengaruh filter)
            $totalHariAktif = $this->hitungHariAktifPeriode();

            $totalHariAbsensi = Absensi::query()
                ->where('kelas_id', $kelasId)
                ->whereBetween('tanggal', [$tanggalMulai, $tanggalBerakhir])
                ->whereIn('tanggal', $activeDates)
                ->distinct() //Mencegah tanggal yang sama dihitung berkali-kali.
                ->count('tanggal');

            //Variabel akumulasi
            $totalPersentaseSemuaSiswa = 0;
            $totalHadir = 0;
            $totalSakit = 0;
            $totalIzin = 0;
            $totalAlpa = 0;
            $totalTidakMasuk = 0;
            $namaKelas = $selectedKelas->nama_kelas;
            $jumlahSiswa = $siswas->count();

            foreach ($siswas as $siswa) { //Artinya proses dilakukan satu per satu.
                $totals = $absensiTotals->get($siswa->id); //Mengambil total absensi siswa
                $hadir = $totals->hadir ?? 0; //Jika tidak ditemukan data, nilainya:0
                $sakit = $totals->sakit ?? 0; //?? adalah null coalescing operator.Artinya:kalau nilai kiri tidak ada/null→ gunakan nilai kanan
                $izin = $totals->izin ?? 0;
                $alpa = $totals->alpa ?? 0;

                $tidakMasuk = $sakit + $izin + $alpa; //Menghitung tidak masuk
                $persentase = $totalHariAktifFilter > 0 ? round(($hadir / $totalHariAktifFilter) * 100, 1) : 0; //Menghitung persentase siswa
                $persentase = min($persentase, 100);

                //Membentuk data rekap siswa
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

                // Akumulasi untuk Total Keseluruhan
                $totalHadir += $hadir; //$totalHadir = $totalHadir + $hadir;
                $totalSakit += $sakit;
                $totalIzin += $izin;
                $totalAlpa += $alpa;
                $totalTidakMasuk += $tidakMasuk;
            }

            // Total hari kerja untuk seluruh kelas = hari aktif * jumlah siswa
            $totalHariKerjaKelas = $totalHariAktifFilter * $jumlahSiswa;

            // Hitung rata-rata kehadiran kelas keseluruhan
            if ($jumlahSiswa > 0) {
                $stats['rata_hadir'] = round($totalPersentaseSemuaSiswa / $jumlahSiswa, 1);
            }

            // Hitung total dan persentase keseluruhan (penyebut = total hari kerja kelas)
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

        //Menentukan apakah tabel disembunyikan
        if ($kelasId && $totalHariAbsensi === 0) {
            $hideRekapTabel = true;
        }

        // Format tanggal untuk tampilan (d/m/Y)
        $tanggalMulaiDisplay = Carbon::parse($tanggalMulai)->format('d/m/Y');
        $tanggalBerakhirDisplay = Carbon::parse($tanggalBerakhir)->format('d/m/Y');


        //Mengirim semua data ke View
        return compact('kelas', 'rekapSiswa', 'totalHariAktif', 'totalHariAbsensi', 'kelasId', 'tanggalMulai', 'tanggalBerakhir', 'tanggalMulaiDisplay', 'tanggalBerakhirDisplay', 'stats', 'namaKelas', 'preset', 'hideRekapTabel');
    }

    /** @return Collection<int, Kelas> */
    private function accessibleKelas(Request $request): Collection
    {
        //Mengambil daftar kelas yang boleh diakses oleh user yang sedang login.
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

        //Tujuannya menentukan tanggal berdasarkan preset.
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

        //Mengambil tanggal mulai dan tanggal selesai berdasarkan semester pada tabel periode.
        $periode = Periode::query()
            ->where('semester', $semester)
            ->latest('id')
            ->first();

        if (! $periode) {
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
        // Optimasi: Ambil kedua semester sekaligus dalam 1 query
        $periodes = Periode::query()
            ->orderBy('tahun_ajaran', 'desc')
            ->orderBy('semester', 'asc')
            ->limit(2)
            ->get();

        if ($periodes->isEmpty()) {
            return 0;
        }

        $periodeSemester1 = $periodes->firstWhere('semester', 1);
        $periodeSemester2 = $periodes->firstWhere('semester', 2);

        // Jika tidak ada periode sama sekali, return 0
        if (!$periodeSemester1 && !$periodeSemester2) {
            return 0;
        }

        // Jika hanya ada semester 1
        if ($periodeSemester1 && !$periodeSemester2) {
            return $this->hitungHariAktif(
                $periodeSemester1->tanggal_mulai->toDateString(),
                $periodeSemester1->tanggal_selesai->toDateString()
            );
        }

        // Jika hanya ada semester 2
        if (!$periodeSemester1 && $periodeSemester2) {
            return $this->hitungHariAktif(
                $periodeSemester2->tanggal_mulai->toDateString(),
                $periodeSemester2->tanggal_selesai->toDateString()
            );
        }

        // Jika ada kedua semester, hitung dari semester 1 mulai sampai semester 2 selesai
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
        $mulai = Carbon::parse($tanggalMulai); //String tanggal diubah menjadi object Carbon.
        $akhir = Carbon::parse($tanggalBerakhir);

        if ($akhir->lt($mulai)) { //lt = less than
            return 0;
        }

        // Ambil semua periode yang termasuk dalam rentang tanggal
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
            // Ambil semua hari libur nasional dari semua periode yang relevan
            $periodeIds = $periodes->pluck('id');

            $hariLiburNasional = HariLibur::query()
                ->whereIn('periode_id', $periodeIds)
                ->where('tipe', 'nasional')
                ->whereBetween('tanggal', [$mulai->toDateString(), $akhir->toDateString()])
                ->pluck('tanggal')
                ->map(fn($t) => $t instanceof Carbon ? $t->toDateString() : Carbon::parse($t)->toDateString())
                ->unique()
                ->values();

            // Ambil semua hari libur mingguan dari semua periode yang relevan (gabungkan)
            $hariLiburMingguan = HariLibur::query()
                ->whereIn('periode_id', $periodeIds)
                ->where('tipe', 'mingguan')
                ->pluck('hari')
                ->unique()
                ->values();
        }

        $namaHari = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];

        $hariAktif = 0;
        $hari = $mulai->copy();

        while ($hari->lte($akhir)) { //lte=less than or equal/selama hari <= tanggal akhir
            //ambil tanggal dan hari
            $tanggalStr = $hari->toDateString();
            $namaHariIni = $namaHari[$hari->dayOfWeek];

            //Mengecek hari libur
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
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
            return Carbon::createFromFormat('d/m/Y', $date);
        }
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
        $mulai = Carbon::parse($tanggalMulai);
        $akhir = Carbon::parse($tanggalBerakhir);

        // Batasi akhir di hari ini jika di masa depan
        $today = today();
        if ($akhir->gt($today)) { // gt = greater than
            $akhir = $today->copy(); //Membatasi tanggal akhir sampai hari ini
        }
        // Jika mulai juga di masa depan, return kosong
        if ($mulai->gt($today)) { //Jika tanggal mulai juga masa depan
            return []; //hasilnya daftar tanggal aktif kosong.
        }

        // Ambil semua periode yang overlap dengan rentang tanggal
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

        // Kumpulkan hari libur nasional dari periode relevan
        $periodeIds = $periodes->pluck('id');
        $hariLiburNasional = HariLibur::whereIn('periode_id', $periodeIds)
            ->where('tipe', 'nasional')
            ->whereBetween('tanggal', [$mulai->toDateString(), $akhir->toDateString()])
            ->pluck('tanggal')
            ->map(fn($t) => Carbon::parse($t)->toDateString())
            ->unique()
            ->values();

        // Kumpulkan hari libur mingguan dari periode relevan
        $hariLiburMingguan = HariLibur::whereIn('periode_id', $periodeIds)
            ->where('tipe', 'mingguan')
            ->pluck('hari')
            ->unique()
            ->values();

        $namaHari = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];

        //Membentuk array tanggal aktif
        $activeDates = [];
        $hari = $mulai->copy();

        //Kemudian loop:
        while ($hari->lte($akhir)) {

            //Setiap tanggal dicek.
            $tanggalStr = $hari->toDateString();
            $namaHariIni = $namaHari[$hari->dayOfWeek];

            $isHariLibur = $hariLiburNasional->contains($tanggalStr)
                || $hariLiburMingguan->contains($namaHariIni);

            if (! $isHariLibur) { //Jika bukan libur:
                $activeDates[] = $tanggalStr;
            }

            $hari->addDay(); //maka tanggal dimasukkan.
        }

        return $activeDates;
    }
}
