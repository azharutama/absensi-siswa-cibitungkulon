<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Siswa;
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
        $stats = ['total' => 0, 'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];

        if ($kelasId) {
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

        return view('absensi.create', compact('kelas', 'siswas', 'absensiSiswa', 'kelasId', 'tanggal', 'stats', 'isLocked'));
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

        // Double Security di sisi server sebelum proses insert data
        $sudahAbsen = Absensi::where('tanggal', $tanggal)
            ->whereHas('siswa', function ($query) use ($kelasId) {
                $query->where('kelas_id', $kelasId);
            })->exists();

        if ($sudahAbsen) {
            return redirect()->route('absensi.create', ['kelas_id' => $kelasId, 'tanggal' => $tanggal])
                ->with('error', 'Data absensi kelas ini pada tanggal tersebut sudah terisi. Gunakan menu Edit Absensi untuk melakukan perubahan.');
        }

        $kelas = Kelas::findOrFail($kelasId);

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
        $stats = ['total' => 0, 'hadir' => 0, 'izin' => 0, 'sakit' => 0, 'alpa' => 0];

        if ($kelasId) {
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

        return view('absensi.edit', compact('kelas', 'siswas', 'absensiSiswa', 'kelasId', 'tanggal', 'stats', 'isLocked'));
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
}
