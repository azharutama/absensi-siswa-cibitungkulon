<?php

namespace App\Http\Controllers;

use App\Models\Periode;
use Illuminate\Http\Request;

class PeriodeController extends Controller
{
    public function index(Request $request)
    {
        $query = Periode::query();

        if ($request->has('search') && $request->search != '') {
            $query->where('nama_periode', 'like', '%' . $request->search . '%');
        }

        $periodes = $query->latest()->get();

        return view('periode.index', compact('periodes'));
    }

    public function create()
    {
        return view('periode.create');
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_periode'   => 'required|string|max:100|unique:periodes,nama_periode',
            'tanggal_mulai'  => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'status_aktif'   => 'required|boolean',
        ]);

        // Jika periode baru ini diset aktif, nonaktifkan periode lain terlebih dahulu
        if ($request->status_aktif == 1) {
            Periode::where('status_aktif', true)->update(['status_aktif' => false]);
        }

        Periode::create([
            'nama_periode'   => $request->nama_periode,
            'tanggal_mulai'  => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
            'status_aktif'   => $request->status_aktif,
        ]);

        return redirect()->route('periode.index')->with('success', 'Periode akademik berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $periode = Periode::findOrFail($id);
        return view('periode.edit', compact('periode'));
    }

    public function update(Request $request, $id)
    {
        $periode = Periode::findOrFail($id);

        $request->validate([
            'nama_periode'   => 'required|string|max:100|unique:periodes,nama_periode,' . $periode->id,
            'tanggal_mulai'  => 'required|date',
            'tanggal_selesai' => 'required|date|after_or_equal:tanggal_mulai',
            'status_aktif'   => 'required|boolean',
        ]);

        if ($request->status_aktif == 1) {
            Periode::where('id', '!=', $periode->id)->update(['status_aktif' => false]);
        }

        $periode->update([
            'nama_periode'   => $request->nama_periode,
            'tanggal_mulai'  => $request->tanggal_mulai,
            'tanggal_selesai' => $request->tanggal_selesai,
            'status_aktif'   => $request->status_aktif,
        ]);

        return redirect()->route('periode.index')->with('success', 'Periode akademik berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $periode = Periode::findOrFail($id);

        if ($periode->kelas()->exists()) {
            return redirect()->route('periode.index')->with('error', 'Periode tidak bisa dihapus karena memiliki data keterikatan kelas.');
        }

        $periode->delete();

        return redirect()->route('periode.index')->with('success', 'Periode akademik berhasil dihapus.');
    }
}
