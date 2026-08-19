<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsActivity;
use App\Models\Kelas;
use App\Models\RiwayatKelasSiswa;
use App\Models\Siswa;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class SiswaController extends Controller
{
    use LogsActivity;

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
        ]);

        $siswas = Siswa::query()
            ->select([
                'id',
                'nis',
                'nisn',
                'nama_siswa',
                'jenis_kelamin',
                'alamat',
                'nama_ayah',
                'no_whatsapp_ayah',
                'nama_ibu',
                'no_whatsapp_ibu',
                'kelas_id',
            ])
            ->with([
                'kelas:id,nama_kelas',
            ])
            ->whereHas('kelas', fn ($query) => $query->accessibleBy($request->user()))
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('nama_siswa', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%");
                });
            })
            ->when($filters['kelas_id'] ?? null, fn ($query, $kelasId) => $query->where('kelas_id', $kelasId))
            ->orderBy('nama_siswa')
            ->paginate(15)
            ->withQueryString();

        $kelas = Kelas::query()
            ->accessibleBy($request->user())
            ->select('id', 'nama_kelas')
            ->orderBy('nama_kelas')
            ->get();

        return view('siswa.index', compact('siswas', 'kelas'));
    }

    public function create(Request $request): View
    {
        return view('siswa.create', $this->formOptions($request->user()));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        $kelasSelected = $this->findKelas((int) $data['kelas_id'], $request->user());

        $data['kelas_id'] = $kelasSelected->id;

        $siswa = Siswa::create($data);

        // Log activity menggunakan trait
        $this->logCreate('Siswa', $siswa, "Menambahkan siswa baru: {$siswa->nama_siswa}", ['siswa' => $siswa->toArray()]);

        return to_route('siswa.index')
            ->with('success', 'Data siswa berhasil ditambahkan.');
    }

    public function edit(Request $request, Siswa $siswa): View
    {
        $this->authorizeSiswaAccess($siswa, $request->user());

        return view('siswa.edit', [
            'siswa' => $siswa,
            ...$this->formOptions($request->user()),
        ]);
    }

    public function update(Request $request, Siswa $siswa): RedirectResponse
    {
        $this->authorizeSiswaAccess($siswa, $request->user());

        $data = $this->validatedData($request, $siswa);

        $kelasSelected = $this->findKelas((int) $data['kelas_id'], $request->user());
        $data['kelas_id'] = $kelasSelected->id;

        $oldData = $siswa->toArray();
        $siswa->update($data);

        // Log activity menggunakan trait
        $this->logUpdate('Siswa', $siswa, ['old' => $oldData, 'new' => $siswa->fresh()->toArray()], "Memperbarui data siswa: {$siswa->nama_siswa}");

        return to_route('siswa.index')
            ->with('success', 'Data siswa berhasil diperbarui.');
    }

    public function destroy(Request $request, Siswa $siswa): RedirectResponse
    {
        $this->authorizeSiswaAccess($siswa, $request->user());

        $siswaName = $siswa->nama_siswa;
        $siswaId = $siswa->id;

        DB::transaction(function () use ($siswa): void {
            $siswa->absensis()->delete();
            $siswa->delete();
        });

        // Log activity menggunakan trait
        $this->logDelete('Siswa', $siswaId, $siswaName);

        return to_route('siswa.index')
            ->with('success', 'Data siswa berhasil dihapus.');
    }

    public function ubahKelasForm(): View
    {
        return view('siswa.ubah-kelas', [
            'kelas' => Kelas::query()
                ->select('id', 'nama_kelas')
                ->orderBy('nama_kelas')
                ->get(),
        ]);
    }

    public function ubahKelas(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'kelas_asal_id' => ['required', 'integer', 'exists:kelas,id'],
            'kelas_tujuan_id' => ['required', 'integer', 'exists:kelas,id', 'different:kelas_asal_id'],
        ]);

        $kelasAsal = Kelas::findOrFail((int) $data['kelas_asal_id']);
        $kelasTujuan = Kelas::findOrFail((int) $data['kelas_tujuan_id']);

        $count = Siswa::query()
            ->where('kelas_id', $kelasAsal->id)
            ->count();

        if ($count === 0) {
            return redirect()->back()
                ->with('error', 'Tidak ada siswa di kelas asal.');
        }

        DB::transaction(function () use ($kelasAsal, $kelasTujuan): void {
            $siswaIds = Siswa::query()
                ->where('kelas_id', $kelasAsal->id)
                ->pluck('id');

            Siswa::query()
                ->whereIn('id', $siswaIds)
                ->update(['kelas_id' => $kelasTujuan->id]);

            foreach ($siswaIds->chunk(200) as $chunk) {
                foreach ($chunk as $siswaId) {
                    RiwayatKelasSiswa::create([
                        'siswa_id' => $siswaId,
                        'kelas_asal_id' => $kelasAsal->id,
                        'kelas_tujuan_id' => $kelasTujuan->id,
                        'tanggal_kenaikan' => today(),
                        'status' => 'aktif',
                    ]);
                }
            }
        });

        return to_route('siswa.index')
            ->with('success', "{$count} siswa berhasil dipindahkan ke kelas baru.");
    }

    /** @return array<string, mixed> */
    private function validatedData(Request $request, ?Siswa $siswa = null): array
    {
        $data = $request->validate([
            'nis' => [
                'required',
                'numeric',
                'max_digits:50',
                Rule::unique('siswas', 'nis')->ignore($siswa),
            ],
            'nisn' => [
                'required',
                'numeric',
                'max_digits:50',
                Rule::unique('siswas', 'nisn')->ignore($siswa),
            ],
            'nama_siswa' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', Rule::in(['laki-laki', 'perempuan'])],
            'nama_ayah' => ['required', 'string', 'max:255'],
            'no_whatsapp_ayah' => ['nullable', 'numeric', 'max_digits:20'],
            'nama_ibu' => ['required', 'string', 'max:255'],
            'no_whatsapp_ibu' => ['nullable', 'numeric', 'max_digits:20'],
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'alamat' => ['required', 'string'],
        ]);

        if (blank($data['no_whatsapp_ayah']) && blank($data['no_whatsapp_ibu'])) {
            throw ValidationException::withMessages([
                'no_whatsapp_ayah' => 'Minimal salah satu nomor WhatsApp orang tua (ayah atau ibu) wajib diisi.',
            ]);
        }

        return $data;
    }

    /** @return array{kelas: Collection} */
    private function formOptions(User $user): array
    {
        return [
            'kelas' => Kelas::query()
                ->accessibleBy($user)
                ->select('id', 'nama_kelas')
                ->orderBy('nama_kelas')
                ->get(),
        ];
    }

    private function findKelas(int $kelasId, User $user): Kelas
    {
        $kelas = Kelas::query()
            ->accessibleBy($user)
            ->find($kelasId);

        if (! $kelas) {
            throw ValidationException::withMessages([
                'kelas_id' => 'Kelas tidak ditemukan atau tidak dapat diakses.',
            ]);
        }

        return $kelas;
    }

    /**
     * Memastikan guru/operator berhak mengelola data siswa di kelas tersebut.
     */
    private function authorizeSiswaAccess(Siswa $siswa, User $user): void
    {
        abort_unless(
            Kelas::query()
                ->accessibleBy($user)
                ->whereKey($siswa->kelas_id)
                ->exists(),
            403,
            'Akses ditolak. Siswa ini bukan bagian dari kelas yang Anda ampu.',
        );
    }
}
