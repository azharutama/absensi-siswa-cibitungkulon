<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsActivity;
use App\Models\Kelas;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class GuruController extends Controller
{
    use LogsActivity;

    /**
     * Ambil data user beserta filter pencarian jika ada
     */
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim($filters['search'] ?? '');

        $query = User::query()
            ->select(['id', 'nip', 'username', 'nama', 'no_telepon', 'role', 'address'])
            ->with('kelas:id,guru_id,nama_kelas');

        // Cari berdasarkan nama, wa, atau nip
        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('nama', 'like', "%{$search}%")
                    ->orWhere('no_telepon', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $gurus = $query
            ->orderBy('nama')
            ->paginate(15)
            ->withQueryString();

        return view('guru.index', compact('gurus'));
    }

    /**
     * Buka form input user baru
     */
    public function create(): View
    {
        $kelas = Kelas::query()
            ->select(['id', 'nama_kelas'])
            ->whereNull('guru_id')
            ->where('status', 'aktif')
            ->orderBy('nama_kelas')
            ->get();

        return view('guru.create', compact('kelas'));
    }

    /**
     * Validasi dan simpan user baru
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'nip' => 'required|numeric|unique:users,nip',
            'username' => 'required|string|alpha_dash|max:50|unique:users,username',
            'nama' => 'required|string|max:255',
            'no_telepon' => 'required|numeric|unique:users,no_telepon',
            'alamat' => 'required|string|max:255',
            'role' => 'required|string|in:operator,guru,kepala_sekolah',
            'jenis_kelamin' => 'required|string|in:laki-laki,perempuan',
            'password' => 'required|string|min:8|confirmed',
            'kelas_id' => 'nullable|exists:kelas,id',
        ]);

        $data['address'] = $data['alamat'];
        unset($data['alamat']);

        if (filled($data['password'] ?? null)) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $kelasId = $request->filled('kelas_id') ? $request->integer('kelas_id') : null;

        DB::transaction(function () use ($data, $kelasId): void {
            $user = User::create($data);

            if ($user->role === 'guru' && $kelasId) {
                $kelas = Kelas::where('id', $kelasId)
                    ->whereNull('guru_id')
                    ->lockForUpdate()
                    ->firstOrFail();
                $kelas->update(['guru_id' => $user->id]);
            }

            // Log activity menggunakan trait
            $this->logCreate(
                'Guru',
                $user,
                "Menambahkan pengguna baru: {$user->nama} ({$user->role})",
                ['user' => $user->makeHidden('password')->toArray()]
            );
        });

        return redirect()->route('guru.index')->with('success', 'Data User berhasil ditambahkan.');
    }

    /**
     * Buka form edit user
     */
    public function edit($id): View
    {
        $guru = User::query()
            ->select(['id', 'nip', 'username', 'nama', 'no_telepon', 'address', 'role', 'jenis_kelamin'])
            ->with('kelas:id,guru_id,nama_kelas')
            ->findOrFail($id);

        $kelas = Kelas::query()
            ->select(['id', 'nama_kelas'])
            ->where(function ($query) use ($guru) {
                $query->whereNull('guru_id')
                    ->orWhere('guru_id', $guru->id);
            })
            ->where('status', 'aktif')
            ->orderBy('nama_kelas')
            ->get();

        return view('guru.edit', compact('guru', 'kelas'));
    }

    /**
     * Validasi dan simpan perubahan data user
     */
    public function update(Request $request, $id): RedirectResponse
    {
        $user = User::findOrFail($id);

        $uniqueSuffix = $user ? ",{$user->id}" : '';
        $data = $request->validate([
            'nip' => "required|numeric|unique:users,nip{$uniqueSuffix}",
            'username' => "required|string|alpha_dash|max:50|unique:users,username{$uniqueSuffix}",
            'nama' => 'required|string|max:255',
            'no_telepon' => "required|numeric|unique:users,no_telepon{$uniqueSuffix}",
            'alamat' => 'required|string|max:255',
            'role' => 'required|string|in:operator,guru,kepala_sekolah',
            'jenis_kelamin' => 'required|string|in:laki-laki,perempuan',
            'password' => $user ? 'nullable|string|min:8|confirmed' : 'required|string|min:8|confirmed',
            'kelas_id' => 'nullable|exists:kelas,id',
        ]);

        $data['address'] = $data['alamat'];
        unset($data['alamat']);

        if (filled($data['password'] ?? null)) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        DB::transaction(function () use ($request, $user, $data): void {
            $operatorIds = User::query()
                ->where('role', 'operator')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');
            $lockedUser = User::query()->whereKey($user->id)->lockForUpdate()->firstOrFail();

            if (
                $lockedUser->role === 'operator'
                && $data['role'] !== 'operator'
                && (int) $request->user()->getKey() === (int) $lockedUser->getKey()
            ) {
                throw ValidationException::withMessages([
                    'role' => 'Operator yang sedang digunakan tidak dapat mengubah role akunnya sendiri.',
                ]);
            }

            if ($lockedUser->role === 'operator' && $data['role'] !== 'operator' && $operatorIds->count() <= 1) {
                throw ValidationException::withMessages([
                    'role' => 'Minimal satu akun operator harus tetap tersedia.',
                ]);
            }

            $oldData = $lockedUser->makeHidden('password')->toArray();
            $lockedUser->update($data);

            // Handle kelas assignment for guru role
            if ($lockedUser->role === 'guru') {
                $currentKelasId = $lockedUser->kelas?->id;
                $newKelasId = $request->filled('kelas_id') ? $request->integer('kelas_id') : null;

                if ($currentKelasId && $currentKelasId !== $newKelasId) {
                    // Release old kelas
                    Kelas::where('id', $currentKelasId)->update(['guru_id' => null]);
                }

                if ($newKelasId && $currentKelasId !== $newKelasId) {
                    // Assign new kelas
                    $kelas = Kelas::where('id', $newKelasId)
                        ->where(function ($query) use ($lockedUser) {
                            $query->whereNull('guru_id')
                                ->orWhere('guru_id', $lockedUser->id);
                        })
                        ->lockForUpdate()
                        ->firstOrFail();
                    $kelas->update(['guru_id' => $lockedUser->id]);
                }
            } else {
                // Release any kelas if not guru
                Kelas::query()->where('guru_id', $lockedUser->id)->update(['guru_id' => null]);
            }

            // Log activity menggunakan trait
            $this->logUpdate(
                'Guru',
                $lockedUser,
                ['old' => $oldData, 'new' => $lockedUser->fresh()->makeHidden('password')->toArray()],
                "Memperbarui data pengguna: {$lockedUser->nama}"
            );
        });

        return redirect()->route('guru.index')->with('success', 'Data User berhasil diperbarui.');
    }

    /**
     * Hapus data user dari database
     */
    public function destroy(Request $request, $id): RedirectResponse
    {
        if ((int) $request->user()->getKey() === (int) $id) {
            return redirect()->route('guru.index')->with('error', 'Akun yang sedang digunakan tidak dapat dihapus.');
        }

        $error = DB::transaction(function () use ($id): ?string {
            $operatorIds = User::query()
                ->where('role', 'operator')
                ->orderBy('id')
                ->lockForUpdate()
                ->pluck('id');
            $user = User::query()->whereKey($id)->lockForUpdate()->firstOrFail();

            if ($user->role === 'operator' && $operatorIds->count() <= 1) {
                return 'Operator terakhir tidak dapat dihapus.';
            }

            if ($user->absensis()->exists() || $user->rekaps()->exists()) {
                return 'Pengguna tidak dapat dihapus karena memiliki riwayat absensi atau rekap.';
            }

            $userName = $user->nama;
            $userId = $user->id;

            // Release any kelas assignment
            Kelas::query()->where('guru_id', $user->id)->update(['guru_id' => null]);

            $user->delete();

            // Log activity menggunakan trait
            $this->logDelete('Guru', $userId, $userName);

            return null;
        });

        if ($error !== null) {
            return redirect()->route('guru.index')->with(
                'error',
                $error
            );
        }

        return redirect()->route('guru.index')->with('success', 'Data User berhasil dihapus.');
    }
}
