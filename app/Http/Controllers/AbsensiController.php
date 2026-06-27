<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\HariLibur;
use App\Models\Kelas;
use App\Models\Siswa;
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
            Absensi::create([
                'siswa_id' => $siswaId,
                'user_id' => Auth::id(), // <-- Sudah menggunakan Auth::id()
                'periode_id' => $kelas->periode_id,
                'tanggal' => $tanggal,
                'status' => $status,
            ]);
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
            Absensi::updateOrCreate(
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
}
