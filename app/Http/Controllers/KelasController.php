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
        $query = Kelas::query()
            ->select(['id', 'nama_kelas']);

        if ($request->has('search') && $request->search != '') {
            $query->where('nama_kelas', 'like', '%' . $request->search . '%');
        }

        $kelas = $query
            ->orderBy('nama_kelas')
            ->paginate(15)
            ->withQueryString();

        return view('kelas.index', compact('kelas'));
    }

    public function create()
    {
        $gurus = $this->availableWaliKelasQuery()->get();
        $periodeAktif = Periode::query()
            ->select(['id', 'nama_periode'])
            ->latest()
            ->first();

        return view('kelas.create', compact('gurus', 'periodeAktif'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_kelas' => 'required|string|max:50|unique:kelas,nama_kelas',
            'guru_id'    => 'required|exists:users,id',
        ]);

        if (! $this->availableWaliKelasQuery()->where('id', $request->guru_id)->exists()) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['guru_id' => 'Guru yang dipilih tidak tersedia sebagai wali kelas.']);
        }

        $periodeAktif = Periode::latest()->first();

        if (!$periodeAktif) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal membuat kelas. Pastikan data Periode Akademik sudah dibuat.');
        }

        $kelas = Kelas::create([
            'nama_kelas' => $request->nama_kelas,
            'periode_id' => $periodeAktif->id,
            'status'     => 'aktif', // Mengisi kolom status bawaan migrasi kelas Anda
        ]);

        $kelas->gurus()->syncWithoutDetaching([
            $request->guru_id => ['is_wali_kelas' => true],
        ]);

        return redirect()->route('kelas.index')->with('success', 'Data Kelas berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $kelas = Kelas::query()
            ->select(['id', 'nama_kelas', 'periode_id'])
            ->with('periode:id,nama_periode')
            ->findOrFail($id);

        $gurus = $kelas->gurus()
            ->select(['users.id', 'users.nama', 'users.nip'])
            ->where('role', 'guru')
            ->orderBy('nama')
            ->get();

        $periodeAktif = Periode::query()
            ->select(['id', 'nama_periode'])
            ->latest()
            ->first();

        $currentWaliId = $kelas->gurus()
            ->wherePivot('is_wali_kelas', true)
            ->value('users.id');

        return view('kelas.edit', compact('kelas', 'gurus', 'periodeAktif', 'currentWaliId'));
    }

    public function update(Request $request, $id)
    {
        $kelas = Kelas::findOrFail($id);

        $request->validate([
            'nama_kelas' => 'required|string|max:50|unique:kelas,nama_kelas,' . $kelas->id,
            'guru_id'    => 'required|exists:users,id',
        ]);

        if (! $kelas->gurus()->where('users.id', $request->guru_id)->exists()) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['guru_id' => 'Guru yang dipilih belum berelasi dengan kelas ini. Tambahkan relasi melalui menu Guru terlebih dahulu.']);
        }

        $kelas->update([
            'nama_kelas' => $request->nama_kelas,
        ]);

        foreach ($kelas->gurus()->pluck('users.id') as $guruId) {
            $kelas->gurus()->updateExistingPivot($guruId, ['is_wali_kelas' => false]);
        }

        $kelas->gurus()->updateExistingPivot($request->guru_id, ['is_wali_kelas' => true]);

        return redirect()->route('kelas.index')->with('success', 'Data Kelas berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $kelas = Kelas::findOrFail($id);

        if ($kelas->siswas()->exists()) {
            return redirect()->route('kelas.index')->with('error', 'Kelas tidak bisa dihapus karena masih memiliki data siswa.');
        }

        $kelas->delete();

        return redirect()->route('kelas.index')->with('success', 'Data Kelas berhasil dihapus.');
    }

    private function availableWaliKelasQuery()
    {
        return User::query()
            ->select(['id', 'nama', 'nip'])
            ->where('role', 'guru')
            ->whereDoesntHave('kelas', function ($query) {
                $query->where('kelas_user.is_wali_kelas', true);
            })
            ->orderBy('nama');
    }
}
