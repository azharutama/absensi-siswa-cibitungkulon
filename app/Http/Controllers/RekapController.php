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

        $kelas = Kelas::query()
            ->accessibleBy($request->user())
            ->select(['id'])
            ->get();

        // Auto-redirect menggunakan trait
        if ($redirect = $this->autoRedirectForSingleKelas($request, $kelas, 'rekap.index', ['preset' => $filters['preset'] ?? 'this_month'])) {
            return $redirect;
        }

        return view('rekap.index', $this->rekapData($request));
    }

    /**
     * Download the filtered attendance recap as an Excel workbook.
     */
    public function export(Request $request): BinaryFileResponse
    {
        $data = $this->rekapData($request);

        abort_unless($data['kelasId'], 422, 'Pilih kelas terlebih dahulu sebelum mengunduh rekap.');

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
                $data['totalHariAktif'],
            ),
            $filename,
        );
    }

    /**
     * @return array{kelas: Collection<int, Kelas>, rekapSiswa: array<int, array{nama_siswa: string, nama_kelas: string, hadir: int, sakit: int, izin: int, alpa: int, total_hari_masuk: int}>, totalHariAktif: int, totalHariAbsensi: int, kelasId: int|string|null, tanggalMulai: string, tanggalBerakhir: string, stats: array{rata_hadir: float|int, total_sakit: int, total_izin: int, total_alpa: int}, namaKelas: string|null, preset: string|null, hideRekapTabel: bool}
     */
    private function rekapData(Request $request): array
    {
        $filters = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'preset' => ['nullable', 'string', 'in:today,this_week,this_month,semester_1,semester_2,custom'],
            'tanggal_mulai' => ['nullable', 'date'],
            'tanggal_berakhir' => ['nullable', 'date', 'after_or_equal:tanggal_mulai'],
        ]);

        $kelas = Kelas::query()
            ->accessibleBy($request->user())
            ->select(['id', 'nama_kelas'])
            ->orderBy('nama_kelas')
            ->get();

        $preset = $filters['preset'] ?? 'this_month';
        
        // Auto-select kelas menggunakan trait helper
        $kelasId = $this->getKelasIdWithAutoSelect($filters['kelas_id'] ?? null, $kelas);
        
        // Jika preset custom, gunakan tanggal manual
        if ($preset === 'custom') {
            $tanggalMulaiInput = $filters['tanggal_mulai'] ?? today()->startOfMonth()->format('d/m/Y');
            $tanggalBerakhirInput = $filters['tanggal_berakhir'] ?? today()->format('d/m/Y');
            $tanggalMulai = $this->parseDate($tanggalMulaiInput)->format('Y-m-d');
            $tanggalBerakhir = $this->parseDate($tanggalBerakhirInput)->format('Y-m-d');
        } else {
            // Gunakan preset
            [$tanggalMulai, $tanggalBerakhir] = $this->getPresetDateRange($preset);
        }

        $rekapSiswa = [];
        $namaKelas = null;
        $totalHariAktif = 0;
        $totalHariAbsensi = 0;
        $hideRekapTabel = false;
        $stats = [
            'rata_hadir' => 0,
            'total_sakit' => 0,
            'total_izin' => 0,
            'total_alpa' => 0,
        ];

        if ($kelasId) {
            $selectedKelas = $kelas->firstWhere('id', (int) $kelasId);

            abort_if($selectedKelas === null, 404);

            // Get active dates for the date range (only count active school days)
            $activeDates = $this->getActiveDatesForRange($tanggalMulai, $tanggalBerakhir);

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

            // Hitung total hari aktif dari seluruh periode aktif (tidak terpengaruh filter)
            $totalHariAktif = $this->hitungHariAktifPeriode();

            $totalHariAbsensi = Absensi::query()
                ->where('kelas_id', $kelasId)
                ->whereBetween('tanggal', [$tanggalMulai, $tanggalBerakhir])
                ->distinct()
                ->count('tanggal');

            $totalPersentaseSemuaSiswa = 0;
            $totalHadir = 0;
            $totalSakit = 0;
            $totalIzin = 0;
            $totalAlpa = 0;
            $totalHariMasuk = 0;
            $namaKelas = $selectedKelas->nama_kelas;

            foreach ($siswas as $siswa) {
                $totals = $absensiTotals->get($siswa->id);
                $hadir = $totals->hadir ?? 0;
                $sakit = $totals->sakit ?? 0;
                $izin = $totals->izin ?? 0;
                $alpa = $totals->alpa ?? 0;

                $totalHariMasuk = $hadir + $sakit + $izin + $alpa;
                $persentase = $totalHariMasuk > 0 ? round(($hadir / $totalHariMasuk) * 100, 1) : 0;

                $rekapSiswa[] = [
                    'nama_siswa' => $siswa->nama_siswa,
                    'nama_kelas' => $namaKelas,
                    'hadir' => $hadir,
                    'sakit' => $sakit,
                    'izin' => $izin,
                    'alpa' => $alpa,
                    'total_hari_masuk' => $totalHariMasuk,
                    'persentase' => $persentase,
                ];

                // Akumulasi untuk Widget Card Atas
                $stats['total_sakit'] += $sakit;
                $stats['total_izin'] += $izin;
                $stats['total_alpa'] += $alpa;
                $totalPersentaseSemuaSiswa += $persentase;

                // Akumulasi untuk Total Keseluruhan
                $totalHadir += $hadir;
                $totalSakit += $sakit;
                $totalIzin += $izin;
                $totalAlpa += $alpa;
                $totalHariMasuk += $hadir + $sakit + $izin + $alpa;
            }

            // Hitung rata-rata kehadiran kelas keseluruhan
            if ($siswas->count() > 0) {
                $stats['rata_hadir'] = round($totalPersentaseSemuaSiswa / $siswas->count(), 1);
            }

            // Hitung total dan persentase keseluruhan
            $stats['total_hadir'] = $totalHadir;
            $stats['total_sakit'] = $totalSakit;
            $stats['total_izin'] = $totalIzin;
            $stats['total_alpa'] = $totalAlpa;
            $stats['total_hari_masuk'] = $totalHariMasuk;
            $stats['persentase_hadir'] = $totalHariMasuk > 0 ? round(($totalHadir / $totalHariMasuk) * 100, 1) : 0;
            $stats['persentase_sakit'] = $totalHariMasuk > 0 ? round(($totalSakit / $totalHariMasuk) * 100, 1) : 0;
            $stats['persentase_izin'] = $totalHariMasuk > 0 ? round(($totalIzin / $totalHariMasuk) * 100, 1) : 0;
            $stats['persentase_alpa'] = $totalHariMasuk > 0 ? round(($totalAlpa / $totalHariMasuk) * 100, 1) : 0;
        }

        if ($kelasId && $totalHariAbsensi === 0) {
            $hideRekapTabel = true;
        }

        // Convert dates to d/m/Y for display in view
        $tanggalMulaiDisplay = Carbon::parse($tanggalMulai)->format('d/m/Y');
        $tanggalBerakhirDisplay = Carbon::parse($tanggalBerakhir)->format('d/m/Y');

        return compact('kelas', 'rekapSiswa', 'totalHariAktif', 'totalHariAbsensi', 'kelasId', 'tanggalMulai', 'tanggalBerakhir', 'tanggalMulaiDisplay', 'tanggalBerakhirDisplay', 'stats', 'namaKelas', 'preset', 'hideRekapTabel');
    }

    /**
     * Get date range based on preset filter
     * 
     * @return array{0: string, 1: string}
     */
    private function getPresetDateRange(string $preset): array
    {
        $today = today();
        
        return match($preset) {
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
            ]
        };
    }

    /**
     * Get semester date range from active periode
     * 
     * @return array{0: string, 1: string}
     */
private function getSemesterDateRange(int $semester): array
    {
        $periode = Periode::query()
            ->where('semester', $semester)
            ->latest('id')
            ->first();

        if (! $periode) {
            return [today()->toDateString(), today()->toDateString()];
        }

        return [
            $periode->tanggal_mulai->toDateString(),
            $periode->tanggal_selesai->toDateString()
        ];
    }

    /**
     * Hitung jumlah hari aktif dari seluruh tahun ajaran (semester 1 + semester 2)
     * (tidak terpengaruh filter tanggal)
     */
    private function hitungHariAktifPeriode(): int
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
        $mulai = Carbon::parse($tanggalMulai);
        $akhir = Carbon::parse($tanggalBerakhir);

        if ($akhir->lt($mulai)) {
            return 0;
        }

        // Ambil semua periode yang termasuk dalam rentang tanggal
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
                ->map(fn ($t) => $t instanceof Carbon ? $t->toDateString() : Carbon::parse($t)->toDateString())
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

        while ($hari->lte($akhir)) {
            $tanggalStr = $hari->toDateString();
            $namaHariIni = $namaHari[$hari->dayOfWeek];

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
     * Parse date string supporting both d/m/Y and Y-m-d formats
     */
    private function parseDate(string $date): Carbon
    {
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $date)) {
            return Carbon::createFromFormat('d/m/Y', $date);
        }
        return Carbon::parse($date);
    }

    /**
     * Get active dates (non-holiday weekdays) for a date range across all relevant periodes
     * @return array<string> Y-m-d format dates
     */
    private function getActiveDatesForRange(string $tanggalMulai, string $tanggalBerakhir): array
    {
        $mulai = Carbon::parse($tanggalMulai);
        $akhir = Carbon::parse($tanggalBerakhir);

        // Get all periodes that overlap with the date range
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

        // Collect all national holidays from relevant periodes
        $periodeIds = $periodes->pluck('id');
        $hariLiburNasional = HariLibur::whereIn('periode_id', $periodeIds)
            ->where('tipe', 'nasional')
            ->whereBetween('tanggal', [$mulai->toDateString(), $akhir->toDateString()])
            ->pluck('tanggal')
            ->map(fn ($t) => Carbon::parse($t)->toDateString())
            ->unique()
            ->values();

        // Collect all weekly holidays from relevant periodes
        $hariLiburMingguan = HariLibur::whereIn('periode_id', $periodeIds)
            ->where('tipe', 'mingguan')
            ->pluck('hari')
            ->unique()
            ->values();

        $namaHari = [0 => 'Minggu', 1 => 'Senin', 2 => 'Selasa', 3 => 'Rabu', 4 => 'Kamis', 5 => 'Jumat', 6 => 'Sabtu'];

        $activeDates = [];
        $hari = $mulai->copy();

        while ($hari->lte($akhir)) {
            $tanggalStr = $hari->toDateString();
            $namaHariIni = $namaHari[$hari->dayOfWeek];

            $isHariLibur = $hariLiburNasional->contains($tanggalStr)
                || $hariLiburMingguan->contains($namaHariIni);

            if (! $isHariLibur) {
                $activeDates[] = $tanggalStr;
            }

            $hari->addDay();
        }

        return $activeDates;
    }
}
