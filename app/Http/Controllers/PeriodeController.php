<?php

namespace App\Http\Controllers;

use App\Models\Periode;
use App\Models\HariLibur;
use Illuminate\Http\Request;

class PeriodeController extends Controller
{
    public function index(Request $request)
    {
        $query = Periode::query()
            ->select(['id', 'nama_periode', 'tanggal_mulai', 'tanggal_selesai', 'status_aktif', 'created_at']);

        if ($request->has('search') && $request->search != '') {
            $query->where('nama_periode', 'like', '%' . $request->search . '%');
        }

        $periodes = $query
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('periode.index', compact('periodes'));
    }

    public function create()
    {
        return view('periode.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_periode'    => 'required|string|max:100|unique:periodes,nama_periode',
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'status_aktif'    => 'required|boolean',

            // Validasi Array Hari Libur
            'libur_mingguan'  => 'nullable|array',
            'libur_mingguan.*.hari' => 'required|string',
            'libur_mingguan.*.keterangan' => 'required|string|max:255',

            'libur_nasional'  => 'nullable|array',
            'libur_nasional.*.tanggal' => 'required|date',
            'libur_nasional.*.nama_libur' => 'required|string|max:255',
            'libur_nasional.*.keterangan' => 'nullable|string|max:255',
        ]);

        if ($request->status_aktif == 1) {
            Periode::where('status_aktif', true)->update(['status_aktif' => false]);
        }

        // 1. Simpan Induk Periode
        $periode = Periode::create([
            'nama_periode'    => $request->nama_periode,
            'tanggal_mulai'   => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
            'status_aktif'    => $request->status_aktif,
        ]);

        // 2. Simpan Libur Mingguan jika ada
        if ($request->has('libur_mingguan')) {
            foreach ($request->libur_mingguan as $lm) {
                $periode->hariLiburs()->create([
                    'tipe' => 'mingguan',
                    'hari' => $lm['hari'],
                    'keterangan' => $lm['keterangan']
                ]);
            }
        }

        // 3. Simpan Libur Nasional jika ada
        if ($request->has('libur_nasional')) {
            foreach ($request->libur_nasional as $ln) {
                $periode->hariLiburs()->create([
                    'tipe' => 'nasional',
                    'tanggal' => $ln['tanggal'],
                    'keterangan' => $ln['nama_libur'] . ($ln['keterangan'] ? ' - ' . $ln['keterangan'] : '')
                ]);
            }
        }

        return redirect()->route('periode.index')->with('success', 'Periode akademik dan hari libur berhasil disimpan.');
    }

    public function edit($id)
    {
        // Muat periode beserta relasi hari liburnya agar otomatis ter-fetch di form edit
        $periode = Periode::with('hariLiburs')->findOrFail($id);
        return view('periode.edit', compact('periode'));
    }

    public function update(Request $request, $id)
    {
        $periode = Periode::findOrFail($id);

        $request->validate([
            'nama_periode'    => 'required|string|max:100|unique:periodes,nama_periode,' . $periode->id,
            'tanggal_mulai'   => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'status_aktif'    => 'required|boolean',

            'libur_mingguan'  => 'nullable|array',
            'libur_nasional'  => 'nullable|array',
        ]);

        if ($request->status_aktif == 1) {
            Periode::where('id', '!=', $periode->id)->update(['status_aktif' => false]);
        }

        $periode->update([
            'nama_periode'    => $request->nama_periode,
            'tanggal_mulai'   => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
            'status_aktif'    => $request->status_aktif,
        ]);

        // Hapus data libur lama terlebih dahulu sebelum menimpa dengan data baru hasil edit
        $periode->hariLiburs()->delete();

        if ($request->has('libur_mingguan')) {
            foreach ($request->libur_mingguan as $lm) {
                $periode->hariLiburs()->create([
                    'tipe' => 'mingguan',
                    'hari' => $lm['hari'],
                    'keterangan' => $lm['keterangan']
                ]);
            }
        }

        if ($request->has('libur_nasional')) {
            foreach ($request->libur_nasional as $ln) {
                $periode->hariLiburs()->create([
                    'tipe' => 'nasional',
                    'tanggal' => $ln['tanggal'],
                    'keterangan' => $ln['keterangan'] ?? $ln['nama_libur']
                ]);
            }
        }

        return redirect()->route('periode.index')->with('success', 'Periode akademik berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $periode = Periode::findOrFail($id);
        $periode->delete(); // Otomatis menghapus libur karena cascade
        return redirect()->route('periode.index')->with('success', 'Periode akademik berhasil dihapus.');
    }
}
