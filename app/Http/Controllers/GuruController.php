<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class GuruController extends Controller
{


    /**
     * Ambil data user beserta filter pencarian jika ada
     */
    public function index(Request $request)
    {
        $query = User::query()
            ->select(['id', 'nip', 'nama', 'no_telepon', 'role']);

        // Cari berdasarkan nama, wa, atau nip
        if ($request->has('search') && $request->search != '') {
            $query->where(function ($q) use ($request) {
                $q->where('nama', 'like', '%' . $request->search . '%')
                    ->orWhere('no_telepon', 'like', '%' . $request->search . '%')
                    ->orWhere('nip', 'like', '%' . $request->search . '%');
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
            'nip'           => 'required|string|unique:users,nip',
            'nama'          => 'required|string|max:255',
            'email'         => 'required|string|email|max:255|unique:users,email',
            'no_telepon'    => 'required|string|unique:users,no_telepon',
            'alamat'        => 'nullable|string|max:255',
            'role'          => 'required|string|in:operator,guru,kepala_sekolah',
            'jenis_kelamin' => 'required|string|in:laki-laki,perempuan',
            'password'      => 'required|string|min:8|confirmed',

            // Relasi kelas boleh dilengkapi setelah data guru dan kelas tersedia.
            'kelas'         => 'nullable|array',
            'kelas.*'       => 'exists:kelas,id',
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
            'nip'           => 'nullable|string|unique:users,nip,' . $user->id,
            'nama'          => 'required|string|max:255',
            'email'         => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'no_telepon'    => 'required|string|unique:users,no_telepon,' . $user->id,
            'alamat'        => 'nullable|string|max:255',
            'role'          => 'required|string|in:operator,guru,kepala_sekolah',
            'jenis_kelamin' => 'required|string|in:laki-laki,perempuan',
            'password'      => 'nullable|string|min:8|confirmed',

            'kelas'         => 'nullable|array',
            'kelas.*'       => 'exists:kelas,id',
        ]);

        $data = [
            'nip'           => $request->nip,
            'nama'          => $request->nama,
            'email'         => $request->email,
            'no_telepon'    => $request->no_telepon,
            'address'       => $request->alamat,
            'role'          => $request->role,
            'jenis_kelamin' => $request->jenis_kelamin,
        ];

        // Update password hanya kalau kolomnya diisi di form
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        DB::transaction(function () use ($request, $user, $data): void {
            $user->update($data);

            if ($user->role === 'guru') {
                $this->syncKelasDiampu($user, $request->input('kelas', []));
            } else {
                $user->kelas()->detach();
            }
        });

        return redirect()->route('guru.index')->with('success', 'Data User berhasil diperbarui.');
    }

    /**
     * Hapus data user dari database
     */
    public function destroy($id)
    {
        $user = User::findOrFail($id);

        if ((int) Auth::id() === (int) $user->id) {
            return redirect()->route('guru.index')->with('error', 'Akun yang sedang digunakan tidak dapat dihapus.');
        }

        if ($user->absensis()->exists() || $user->rekaps()->exists()) {
            return redirect()->route('guru.index')->with(
                'error',
                'Pengguna tidak dapat dihapus karena memiliki riwayat absensi atau rekap.'
            );
        }

        DB::transaction(function () use ($user): void {
            $user->kelas()->detach();
            $user->delete();
        });

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
