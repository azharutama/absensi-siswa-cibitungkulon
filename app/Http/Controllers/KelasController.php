<?php

namespace App\Http\Controllers;

use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KelasController extends Controller
{
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim($filters['search'] ?? '');

        $query = Kelas::query()
            ->select(['id', 'nama_kelas', 'status', 'guru_id'])
            ->with('guru:id,nama,nip');

        if ($search !== '') {
            $query->where('nama_kelas', 'like', "%{$search}%");
        }

        $kelas = $query
            ->orderBy('nama_kelas')
            ->paginate(15)
            ->withQueryString();

        return view('kelas.index', compact('kelas'));
    }

    public function create()
    {
        $gurus = $this->availableGuruQuery()->get();

        return view('kelas.create', compact('gurus'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'nama_kelas' => [
                'required',
                'string',
                'max:50',
                Rule::unique('kelas', 'nama_kelas'),
            ],
            'guru_id' => 'nullable|exists:users,id',
        ]);

        if ($request->filled('guru_id') && ! $this->availableGuruQuery()->where('id', $request->guru_id)->exists()) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['guru_id' => 'Guru yang dipilih tidak tersedia (sudah memiliki kelas).']);
        }

        DB::transaction(function () use ($request): void {
            if ($request->filled('guru_id')) {
                User::query()->whereKey($request->integer('guru_id'))->lockForUpdate()->firstOrFail();

                if (! $this->availableGuruQuery()->whereKey($request->integer('guru_id'))->exists()) {
                    throw ValidationException::withMessages([
                        'guru_id' => 'Guru yang dipilih sudah memiliki kelas.',
                    ]);
                }
            }

            $kelas = Kelas::create([
                'nama_kelas' => $request->string('nama_kelas')->trim()->toString(),
                'status' => 'aktif',
                'guru_id' => $request->filled('guru_id') ? $request->integer('guru_id') : null,
            ]);
        });

        return redirect()->route('kelas.index')->with('success', 'Data Kelas berhasil ditambahkan.');
    }

    public function edit($id)
    {
        $kelas = Kelas::query()
            ->select(['id', 'nama_kelas', 'guru_id'])
            ->with('guru:id,nama,nip')
            ->findOrFail($id);

        $currentGuruId = $kelas->guru_id;

        $gurus = $this->guruOptionsQuery($currentGuruId)->get();

        return view('kelas.edit', compact('kelas', 'gurus', 'currentGuruId'));
    }

    public function update(Request $request, $id)
    {
        $kelas = Kelas::findOrFail($id);

        $request->validate([
            'nama_kelas' => [
                'required',
                'string',
                'max:50',
                Rule::unique('kelas', 'nama_kelas')->ignore($kelas->id),
            ],
            'guru_id' => 'nullable|exists:users,id',
        ]);

        $currentGuruId = $kelas->guru_id;

        if (
            $request->filled('guru_id')
            && ! $this->guruOptionsQuery($currentGuruId)->where('id', $request->guru_id)->exists()
        ) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['guru_id' => 'Guru yang dipilih tidak tersedia (sudah memiliki kelas).']);
        }

        DB::transaction(function () use ($currentGuruId, $kelas, $request): void {
            $lockedKelas = Kelas::query()->whereKey($kelas->id)->lockForUpdate()->firstOrFail();

            if ($request->filled('guru_id')) {
                User::query()->whereKey($request->integer('guru_id'))->lockForUpdate()->firstOrFail();

                if (! $this->guruOptionsQuery($currentGuruId)->whereKey($request->integer('guru_id'))->exists()) {
                    throw ValidationException::withMessages([
                        'guru_id' => 'Guru yang dipilih sudah memiliki kelas.',
                    ]);
                }
            }

            $lockedKelas->update([
                'nama_kelas' => $request->string('nama_kelas')->trim()->toString(),
                'guru_id' => $request->filled('guru_id') ? $request->integer('guru_id') : null,
            ]);
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

    private function availableGuruQuery()
    {
        return User::query()
            ->select(['id', 'nama', 'nip'])
            ->where('role', 'guru')
            ->whereDoesntHave('kelas')
            ->orderBy('nama');
    }

    private function guruOptionsQuery(?int $currentGuruId)
    {
        return User::query()
            ->select(['id', 'nama', 'nip'])
            ->where('role', 'guru')
            ->where(function ($query) use ($currentGuruId) {
                $query->whereDoesntHave('kelas');

                if ($currentGuruId) {
                    $query->orWhere('id', $currentGuruId);
                }
            })
            ->orderBy('nama');
    }
}
