<?php

namespace App\Http\Controllers;

use App\Exports\RekapAbsensiExport;
use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Kelas;
use App\Models\Periode;
use App\Models\Siswa;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RekapController extends Controller
{
    public function index(Request $request): View
    {
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
     * @return array{kelas: Collection<int, Kelas>, rekapSiswa: array<int, array{nama_siswa: string, nama_kelas: string, hadir: int, sakit: int, izin: int, alpa: int, total_hari_masuk: int}>, totalHariAktif: int, kelasId: int|string|null, tanggalMulai: string, tanggalBerakhir: string, stats: array{rata_hadir: float|int, total_sakit: int, total_izin: int, total_alpa: int}, namaKelas: string|null, preset: string|null}
     */
    private function rekapData(Request $request): array
    {
        $filters = $request->validate([
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'preset' => ['nullable', 'string', 'in:today,this_week,this_month,semester_1,semester_2,custom'],
            'tanggal_mulai' => ['nullable', 'date_format:Y-m-d'],
            'tanggal_berakhir' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:tanggal_mulai'],
        ]);

        $kelas = Kelas::query()
            ->accessibleBy($request->user())
            ->with('gurus', function ($query): void {
                $query->select(['users.id', 'users.nama']);
            })
            ->select(['id', 'nama_kelas'])
            ->orderBy('nama_kelas')
            ->get();

        $kelasId = $filters['kelas_id'] ?? null;
        $preset = $filters['preset'] ?? 'this_month';
        
        // Jika preset custom, gunakan tanggal manual
        if ($preset === 'custom') {
            $tanggalMulai = $filters['tanggal_mulai'] ?? today()->startOfMonth()->toDateString();
            $tanggalBerakhir = $filters['tanggal_berakhir'] ?? today()->toDateString();
        } else {
            // Gunakan preset
            [$tanggalMulai, $tanggalBerakhir] = $this->getPresetDateRange($preset);
        }

        $rekapSiswa = [];
        $namaKelas = null;
        $totalHariAktif = 0;
        $totalHariAbsensi = 0;
        $stats = [
            'rata_hadir' => 0,
            'total_sakit' => 0,
            'total_izin' => 0,
            'total_alpa' => 0,
        ];

        if ($kelasId) {
            $selectedKelas = $kelas->firstWhere('id', (int) $kelasId);

            abort_if($selectedKelas === null, 404);

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
                ->where('kelas_id', $kelasId)
                ->whereBetween('tanggal', [$tanggalMulai, $tanggalBerakhir])
                ->whereIn('siswa_id', $siswas->pluck('id'))
                ->groupBy('siswa_id')
                ->get()
                ->keyBy('siswa_id');

            $totalHariAktif = $this->hitungHariAktif($tanggalMulai, $tanggalBerakhir);

            $totalHariAbsensi = Absensi::query()
                ->where('kelas_id', $kelasId)
                ->whereBetween('tanggal', [$tanggalMulai, $tanggalBerakhir])
                ->distinct()
                ->count('tanggal');

            $totalPersentaseSemuaSiswa = 0;
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
            }

            // Hitung rata-rata kehadiran kelas keseluruhan
            if ($siswas->count() > 0) {
                $stats['rata_hadir'] = round($totalPersentaseSemuaSiswa / $siswas->count(), 1);
            }
        }

        return compact('kelas', 'rekapSiswa', 'totalHariAktif', 'totalHariAbsensi', 'kelasId', 'tanggalMulai', 'tanggalBerakhir', 'stats', 'namaKelas', 'preset');
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

        $periode = Periode::query()
            ->whereDate('tanggal_mulai', '<=', $mulai->toDateString())
            ->whereDate('tanggal_selesai', '>=', $mulai->toDateString())
            ->first();

        $hariLiburNasional = collect();
        $hariLiburMingguan = collect();

        if ($periode) {
            $hariLiburNasional = HariLibur::query()
                ->where('periode_id', $periode->id)
                ->where('tipe', 'nasional')
                ->whereBetween('tanggal', [$mulai->toDateString(), $akhir->toDateString()])
                ->pluck('tanggal')
                ->map(fn ($t) => $t instanceof Carbon ? $t->toDateString() : Carbon::parse($t)->toDateString())
                ->values();

            $hariLiburMingguan = HariLibur::query()
                ->where('periode_id', $periode->id)
                ->where('tipe', 'mingguan')
                ->pluck('hari')
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
}
