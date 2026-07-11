<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\Periode;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KelasController extends Controller
{
    public function index(Request $request)
    {
        $query = Kelas::query()
            ->select(['id', 'nama_kelas', 'periode_id'])
            ->with([
                'periode:id,nama_periode,status_aktif',
                'gurus' => fn($query) => $query
                    ->select(['users.id', 'users.nama', 'users.nip'])
                    ->wherePivot('is_wali_kelas', true),
            ]);

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
        $periodeAktif = $this->activePeriodeQuery()->first();

        return view('kelas.create', compact('gurus', 'periodeAktif'));
    }

    public function store(Request $request)
    {
        $periodeAktif = $this->activePeriodeQuery()->first();

        if (!$periodeAktif) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Gagal membuat kelas. Pastikan ada satu Periode Akademik yang berstatus aktif.');
        }

        $request->validate([
            'nama_kelas' => [
                'required',
                'string',
                'max:50',
                Rule::unique('kelas', 'nama_kelas')->where(fn($query) => $query->where('periode_id', $periodeAktif->id)),
            ],
            'guru_id'    => 'nullable|exists:users,id',
        ]);

        if ($request->filled('guru_id') && ! $this->availableWaliKelasQuery()->where('id', $request->guru_id)->exists()) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['guru_id' => 'Guru yang dipilih tidak tersedia sebagai wali kelas.']);
        }

        DB::transaction(function () use ($periodeAktif, $request): void {
            $activePeriode = Periode::query()
                ->whereKey($periodeAktif->id)
                ->where('status_aktif', true)
                ->lockForUpdate()
                ->first();

            if (! $activePeriode) {
                throw ValidationException::withMessages([
                    'nama_kelas' => 'Periode aktif berubah. Muat ulang halaman sebelum membuat kelas.',
                ]);
            }

            if ($request->filled('guru_id')) {
                User::query()->whereKey($request->integer('guru_id'))->lockForUpdate()->firstOrFail();

                if (! $this->availableWaliKelasQuery()->whereKey($request->integer('guru_id'))->exists()) {
                    throw ValidationException::withMessages([
                        'guru_id' => 'Guru yang dipilih sudah menjadi wali kelas aktif.',
                    ]);
                }
            }

            $kelas = Kelas::create([
                'nama_kelas' => $request->string('nama_kelas')->trim()->toString(),
                'periode_id' => $activePeriode->id,
                'status' => 'aktif',
            ]);

            if ($request->filled('guru_id')) {
                $kelas->gurus()->syncWithoutDetaching([
                    $request->integer('guru_id') => ['is_wali_kelas' => true],
                ]);
            }
        });

        return redirect()->route('kelas.index')->with('success', 'Data Kelas berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $kelas = Kelas::query()
            ->select(['id', 'nama_kelas', 'periode_id'])
            ->with('periode:id,nama_periode')
            ->findOrFail($id);

        $currentWaliId = $kelas->gurus()
            ->wherePivot('is_wali_kelas', true)
            ->value('users.id');

        $gurus = $this->waliKelasOptionsQuery($currentWaliId)->get();
        $periodeAktif = $this->activePeriodeQuery()->first();

        return view('kelas.edit', compact('kelas', 'gurus', 'periodeAktif', 'currentWaliId'));
    }

    public function update(Request $request, $id)
    {
        $kelas = Kelas::findOrFail($id);

        $request->validate([
            'nama_kelas' => [
                'required',
                'string',
                'max:50',
                Rule::unique('kelas', 'nama_kelas')
                    ->where(fn($query) => $query->where('periode_id', $kelas->periode_id))
                    ->ignore($kelas->id),
            ],
            'guru_id'    => 'nullable|exists:users,id',
        ]);

        $currentWaliId = $kelas->gurus()
            ->wherePivot('is_wali_kelas', true)
            ->value('users.id');

        if (
            $request->filled('guru_id')
            && ! $this->waliKelasOptionsQuery($currentWaliId)->where('id', $request->guru_id)->exists()
        ) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['guru_id' => 'Guru yang dipilih tidak tersedia sebagai wali kelas.']);
        }

        DB::transaction(function () use ($currentWaliId, $kelas, $request): void {
            $lockedKelas = Kelas::query()->whereKey($kelas->id)->lockForUpdate()->firstOrFail();

            if ($request->filled('guru_id')) {
                User::query()->whereKey($request->integer('guru_id'))->lockForUpdate()->firstOrFail();

                if (! $this->waliKelasOptionsQuery($currentWaliId)->whereKey($request->integer('guru_id'))->exists()) {
                    throw ValidationException::withMessages([
                        'guru_id' => 'Guru yang dipilih sudah menjadi wali kelas aktif.',
                    ]);
                }
            }

            $lockedKelas->update([
                'nama_kelas' => $request->string('nama_kelas')->trim()->toString(),
            ]);

            foreach ($lockedKelas->gurus()->pluck('users.id') as $guruId) {
                $lockedKelas->gurus()->updateExistingPivot($guruId, ['is_wali_kelas' => false]);
            }

            if ($request->filled('guru_id')) {
                $lockedKelas->gurus()->syncWithoutDetaching([
                    $request->integer('guru_id') => ['is_wali_kelas' => true],
                ]);
            }
        });

        return redirect()->route('kelas.index')->with('success', 'Data Kelas berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $kelas = Kelas::findOrFail($id);

        if ($kelas->siswas()->exists() || Absensi::query()->where('kelas_id', $kelas->id)->exists()) {
            return redirect()->route('kelas.index')->with(
                'error',
                'Kelas tidak bisa dihapus karena masih memiliki data siswa atau riwayat absensi.'
            );
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
                $query->where('kelas_user.is_wali_kelas', true)
                    ->whereHas('periode', fn($query) => $query->where('status_aktif', true));
            })
            ->orderBy('nama');
    }

    private function waliKelasOptionsQuery(?int $currentWaliId)
    {
        return User::query()
            ->select(['id', 'nama', 'nip'])
            ->where('role', 'guru')
            ->where(function ($query) use ($currentWaliId) {
                $query->whereDoesntHave('kelas', function ($query) {
                    $query->where('kelas_user.is_wali_kelas', true)
                        ->whereHas('periode', fn($query) => $query->where('status_aktif', true));
                });

                if ($currentWaliId) {
                    $query->orWhere('id', $currentWaliId);
                }
            })
            ->orderBy('nama');
    }

    private function activePeriodeQuery()
    {
        return Periode::query()
            ->select(['id', 'nama_periode'])
            ->where('status_aktif', true)
            ->latest('tanggal_mulai');
    }
}
