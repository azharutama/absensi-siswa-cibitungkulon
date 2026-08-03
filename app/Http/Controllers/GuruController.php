<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\LogsActivity;
use App\Models\Kelas;
use App\Models\User;
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
    public function index(Request $request)
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
        ]);
        $search = trim($filters['search'] ?? '');

        $query = User::query()
            ->select(['id', 'nip', 'nama', 'no_telepon', 'role']);

        // Cari berdasarkan nama, wa, atau nip
        if ($search !== '') {
            $query->where(function ($query) use ($search): void {
                $query->where('nama', 'like', "%{$search}%")
                    ->orWhere('no_telepon', 'like', "%{$search}%")
                    ->orWhere('nip', 'like', "%{$search}%");
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
    public function create()
    {
        // Tetap kirim data kelas untuk opsi checkbox di view
        $kelas = Kelas::query()
            ->select(['id', 'nama_kelas'])
            ->orderBy('nama_kelas')
            ->get();

        return view('guru.create', compact('kelas'));
    }

    /**
     * Validasi dan simpan user baru
     */
    public function store(Request $request)
    {
        $request->validate([
            'nip' => 'required|string|unique:users,nip',
            'nama' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email',
            'no_telepon' => 'required|string|unique:users,no_telepon',
            'alamat' => 'nullable|string|max:255',
            'role' => 'required|string|in:operator,guru,kepala_sekolah',
            'jenis_kelamin' => 'required|string|in:laki-laki,perempuan',
            'password' => 'required|string|min:8|confirmed',

            // Relasi kelas boleh dilengkapi setelah data guru dan kelas tersedia.
            'kelas' => 'nullable|array',
            'kelas.*' => 'exists:kelas,id',
        ]);

        DB::transaction(function () use ($request): void {
            $user = User::create([
                'nip' => $request->nip,
                'nama' => $request->nama,
                'email' => $request->email,
                'no_telepon' => $request->no_telepon,
                'address' => $request->alamat,
                'role' => $request->role,
                'jenis_kelamin' => $request->jenis_kelamin,
                'password' => Hash::make($request->password),
            ]);

            if ($user->role === 'guru' && $request->filled('kelas')) {
                $this->syncKelasDiampu($user, $request->input('kelas', []));
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
    public function edit($id)
    {
        $guru = User::query()
            ->select(['id', 'nip', 'nama', 'email', 'no_telepon', 'address', 'role', 'jenis_kelamin'])
            ->with('kelas:id,nama_kelas')
            ->findOrFail($id);

        $kelas = Kelas::query()
            ->select(['id', 'nama_kelas'])
            ->orderBy('nama_kelas')
            ->get();

        return view('guru.edit', compact('guru', 'kelas'));
    }

    /**
     * Validasi dan simpan perubahan data user
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'nip' => 'nullable|string|unique:users,nip,'.$user->id,
            'nama' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,'.$user->id,
            'no_telepon' => 'required|string|unique:users,no_telepon,'.$user->id,
            'alamat' => 'nullable|string|max:255',
            'role' => 'required|string|in:operator,guru,kepala_sekolah',
            'jenis_kelamin' => 'required|string|in:laki-laki,perempuan',
            'password' => 'nullable|string|min:8|confirmed',

            'kelas' => 'nullable|array',
            'kelas.*' => 'exists:kelas,id',
        ]);

        $data = [
            'nip' => $request->nip,
            'nama' => $request->nama,
            'email' => $request->email,
            'no_telepon' => $request->no_telepon,
            'address' => $request->alamat,
            'role' => $request->role,
            'jenis_kelamin' => $request->jenis_kelamin,
        ];

        // Update password hanya kalau kolomnya diisi di form
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
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

            if ($lockedUser->role === 'guru') {
                $this->syncKelasDiampu($lockedUser, $request->input('kelas', []));
            } else {
                $lockedUser->kelas()->detach();
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
    public function destroy(Request $request, $id)
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

            $userData = $user->makeHidden('password')->toArray();
            $userName = $user->nama;
            $userId = $user->id;
            
            $user->kelas()->detach();
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

    private function syncKelasDiampu(User $user, array $kelasIds): void
    {
        $waliKelasIds = $user->kelas()
            ->wherePivot('is_wali_kelas', true)
            ->pluck('kelas.id')
            ->all();

        $syncData = [];
        foreach (array_unique($kelasIds) as $kelasId) {
            $syncData[$kelasId] = [
                'is_wali_kelas' => in_array((int) $kelasId, array_map('intval', $waliKelasIds), true),
            ];
        }

        $user->kelas()->sync($syncData);
    }
}
