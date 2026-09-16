<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsActivity;
use App\Models\Absensi;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class KelasController extends Controller
{
    use LogsActivity;

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

        // Kunci guru dan validasi ulang di dalam transaksi untuk mencegah penugasan ganda.
        $kelas = DB::transaction(function () use ($request): Kelas {
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

            return $kelas;
        });

        $this->logCreate('Kelas', $kelas, "Menambahkan kelas baru: {$kelas->nama_kelas}");

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
        $oldData = $kelas->toArray();

        if (
            $request->filled('guru_id')
            && ! $this->guruOptionsQuery($currentGuruId)->where('id', $request->guru_id)->exists() //Tujuannya memastikan guru yang dipilih memang boleh ditugaskan.
        ) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['guru_id' => 'Guru yang dipilih tidak tersedia (sudah memiliki kelas).']);
        }

        // Penguncian menjaga penugasan guru tetap konsisten saat ada request bersamaan.
        DB::transaction(function () use ($currentGuruId, $kelas, $request): void {
            $lockedKelas = Kelas::query()->whereKey($kelas->id)->lockForUpdate()->firstOrFail(); //Kelas yang sedang diedit dikunci sementara.

            if ($request->filled('guru_id')) {
                User::query()->whereKey($request->integer('guru_id'))->lockForUpdate()->firstOrFail();

                if (! $this->guruOptionsQuery($currentGuruId)->whereKey($request->integer('guru_id'))->exists()) {
                    throw ValidationException::withMessages([
                        'guru_id' => 'Guru yang dipilih sudah memiliki kelas.',
                    ]);
                }
            }

            //Update data kelas dengan data baru yang diterima dari request.
            $lockedKelas->update([
                'nama_kelas' => $request->string('nama_kelas')->trim()->toString(),
                'guru_id' => $request->filled('guru_id') ? $request->integer('guru_id') : null,
            ]);
        });

        $kelas->refresh(); //Ini meminta Laravel mengambil ulang data terbaru dari database.
        $this->logUpdate(
            'Kelas',
            $kelas,
            ['old' => $oldData, 'new' => $kelas->toArray()],
            "Memperbarui data kelas: {$kelas->nama_kelas}"
        );

        return redirect()->route('kelas.index')->with('success', 'Data Kelas berhasil diperbarui.');
    }

    public function destroy($id)
    {
        $kelas = Kelas::findOrFail($id);

        // Lindungi kelas yang masih menjadi referensi data siswa atau riwayat absensi.
        if ($kelas->siswas()->exists() || $kelas->absensis()->exists() || $kelas->hasRekapData()) {
            $messages = [];
            if ($kelas->siswas()->exists()) {
                $messages[] = 'data siswa';
            }
            if ($kelas->absensis()->exists()) {
                $messages[] = 'riwayat absensi';
            }
            if ($kelas->hasRekapData()) {
                $messages[] = 'data rekap';
            }

            return redirect()->route('kelas.index')->with(
                'error',
                'Kelas tidak bisa dihapus karena masih memiliki ' . implode(', ', $messages) . '.'
            );
        } //implode() adalah fungsi PHP untuk menggabungkan isi array menjadi satu string dengan pemisah tertentu.

        $kelas->delete();

        $this->logDelete('Kelas', $kelas->id, $kelas->nama_kelas);

        return redirect()->route('kelas.index')->with('success', 'Data Kelas berhasil dihapus.');
    }

    /**
     * Query guru yang belum memiliki kelas (tersedia untuk ditugaskan)
     */
    private function availableGuruQuery()
    {
        return User::query()
            ->select(['id', 'nama', 'nip'])
            ->where('role', 'guru')
            ->whereDoesntHave('kelas')
            ->orderBy('nama');
    }

    /**
     * Query guru untuk dropdown edit: guru yang belum punya kelas ATAU guru yang sedang mengajar kelas ini
     */
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
