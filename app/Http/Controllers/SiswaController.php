<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Periode;
use App\Models\Siswa;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SiswaController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'kelas_id' => ['nullable', 'integer', 'exists:kelas,id'],
            'periode_id' => ['nullable', 'integer', 'exists:periodes,id'],
            'status' => ['nullable', 'string', 'max:50'],
        ]);

        $siswas = Siswa::query()
            ->select([
                'id',
                'nis',
                'nisn',
                'nama_siswa',
                'jenis_kelamin',
                'kelas_id',
                'periode_id',
                'status',
            ])
            ->with([
                'kelas:id,nama_kelas',
                'periode:id,nama_periode',
            ])
            ->when($filters['search'] ?? null, function ($query, string $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('nama_siswa', 'like', "%{$search}%")
                        ->orWhere('nis', 'like', "%{$search}%")
                        ->orWhere('nisn', 'like', "%{$search}%");
                });
            })
            ->when($filters['kelas_id'] ?? null, fn($query, $kelasId) => $query->where('kelas_id', $kelasId))
            ->when($filters['periode_id'] ?? null, fn($query, $periodeId) => $query->where('periode_id', $periodeId))
            ->when($filters['status'] ?? null, fn($query, $status) => $query->where('status', $status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $kelas = Kelas::query()
            ->select('id', 'nama_kelas', 'periode_id')
            ->orderBy('nama_kelas')
            ->get();

        $periodes = Periode::query()
            ->select('id', 'nama_periode')
            ->latest('tanggal_mulai')
            ->get();

        return view('siswa.index', compact('siswas', 'kelas', 'periodes'));
    }

    public function create(): View
    {
        return view('siswa.create', $this->formOptions());
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validatedData($request);

        // Cari data kelas terpilih untuk mengekstrak periode_id bawaannya
        $kelasSelected = Kelas::findOrFail($data['kelas_id']);

        // Inject otomatis data periode_id dan status default siswa baru
        $data['periode_id'] = $kelasSelected->periode_id;
        $data['status'] = 'aktif';

        Siswa::create($data);

        return to_route('siswa.index')
            ->with('success', 'Data siswa berhasil ditambahkan.');
    }

    public function show(Siswa $siswa): View
    {
        $siswa->load(['kelas:id,nama_kelas', 'periode:id,nama_periode']);

        return view('siswa.show', compact('siswa'));
    }

    public function edit(Siswa $siswa): View
    {
        return view('siswa.edit', [
            'siswa' => $siswa,
            ...$this->formOptions(),
        ]);
    }

    public function update(Request $request, Siswa $siswa): RedirectResponse
    {
        $data = $this->validatedData($request, $siswa);

        // Jika kelas diubah, update juga periode_id agar mengikuti kelas yang baru
        $kelasSelected = Kelas::findOrFail($data['kelas_id']);
        $data['periode_id'] = $kelasSelected->periode_id;

        $siswa->update($data);

        return to_route('siswa.index')
            ->with('success', 'Data siswa berhasil diperbarui.');
    }

    public function destroy(Siswa $siswa): RedirectResponse
    {
        $siswa->delete();

        return to_route('siswa.index')
            ->with('success', 'Data siswa berhasil dihapus.');
    }

    /** @return array<string, mixed> */
    private function validatedData(Request $request, ?Siswa $siswa = null): array
    {
        return $request->validate([
            'nis' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('siswas', 'nis')->ignore($siswa),
            ],
            'nisn' => [
                'nullable',
                'string',
                'max:50',
                Rule::unique('siswas', 'nisn')->ignore($siswa),
            ],
            'nama_siswa' => ['required', 'string', 'max:255'],
            'jenis_kelamin' => ['required', Rule::in(['laki-laki', 'perempuan'])],
            'nama_ayah' => ['required', 'string', 'max:255'],
            'no_whatsapp_ayah' => ['required', 'string', 'max:20'],
            'nama_ibu' => ['required', 'string', 'max:255'],
            'no_whatsapp_ibu' => ['required', 'string', 'max:20'],
            'nama_wali' => ['nullable', 'string', 'max:255'],
            'no_whatsapp_wali' => ['nullable', 'string', 'max:20'],
            'kelas_id' => ['required', 'integer', 'exists:kelas,id'],
            'alamat' => ['nullable', 'string'],
        ]);
    }

    /** @return array{kelas: Collection, periodes: Collection} */
    private function formOptions(): array
    {
        return [
            'kelas' => Kelas::query()
                ->select('id', 'nama_kelas', 'periode_id')
                ->orderBy('nama_kelas')
                ->get(),
            'periodes' => Periode::query()
                ->select('id', 'nama_periode')
                ->latest('tanggal_mulai')
                ->get(),
        ];
    }
}
