<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Periode;
use App\Models\User;
use Illuminate\Http\Request;

class KelasController extends Controller
{
    public function index(Request $request)
    {
        $query = Kelas::query();

        if ($request->has('search') && $request->search != '') {
            $query->where('nama_kelas', 'like', '%' . $request->search . '%');
        }

        $kelas = $query->get();

        return view('kelas.index', compact('kelas'));
    }

    public function create()
    {
        $gurus = User::where('role', 'guru')->get();
        $periodeAktif = Periode::latest()->first();

        return view('kelas.create', compact('gurus', 'periodeAktif'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_kelas' => 'required|string|max:50|unique:kelas,nama_kelas',
            'guru_id'    => 'required|exists:users,id',
        ]);

        $periodeAktif = Periode::latest()->first();

        if (!$periodeAktif) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal membuat kelas. Pastikan data Periode Akademik sudah dibuat.');
        }

        Kelas::create([
            'nama_kelas' => $request->nama_kelas,
            'guru_id'    => $request->guru_id,
            'periode_id' => $periodeAktif->id,
            'status'     => 'aktif', // Mengisi kolom status bawaan migrasi kelas Anda
        ]);

        return redirect()->route('kelas.index')->with('success', 'Data Kelas berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $kelas = Kelas::findOrFail($id);
        $gurus = User::where('role', 'guru')->get();
        $periodeAktif = Periode::latest()->first();

        return view('kelas.edit', compact('kelas', 'gurus', 'periodeAktif'));
    }

    public function update(Request $request, $id)
    {
        $kelas = Kelas::findOrFail($id);

        $request->validate([
            'nama_kelas' => 'required|string|max:50|unique:kelas,nama_kelas,' . $kelas->id,
            'guru_id'    => 'required|exists:users,id',
        ]);

        $kelas->update([
            'nama_kelas' => $request->nama_kelas,
            'guru_id'    => $request->guru_id,
        ]);

        return redirect()->route('kelas.index')->with('success', 'Data Kelas berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $kelas = Kelas::findOrFail($id);

        if ($kelas->siswa()->exists()) {
            return redirect()->route('kelas.index')->with('error', 'Kelas tidak bisa dihapus karena masih memiliki data siswa.');
        }

        $kelas->delete();

        return redirect()->route('kelas.index')->with('success', 'Data Kelas berhasil dihapus.');
    }
}
