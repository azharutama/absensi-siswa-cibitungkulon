<x-app-layout>
    <x-form-card :title="__('Edit Data Pengguna / Guru')" :backUrl="route('guru.index')">
        
        <form method="POST" action="{{ route('guru.update', $guru->id) }}" class="space-y-6">
            @csrf
            @method('PUT') <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <x-input-label for="nip" :value="__('NIP (Nomor Induk Pegawai)')" />
                    <x-text-input id="nip" name="nip" type="text" class="mt-1 block w-full" :value="old('nip', $guru->nip)" required autofocus />
                    <x-input-error class="mt-2" :messages="$errors->get('nip')" />
                </div>

                <div>
                    <x-input-label for="nama" :value="__('Nama Lengkap')" />
                    <x-text-input id="nama" name="nama" type="text" class="mt-1 block w-full" :value="old('nama', $guru->nama)" required />
                    <x-input-error class="mt-2" :messages="$errors->get('nama')" />
                </div>

                <div>
                    <x-input-label for="email" :value="__('Alamat Email')" />
                    <x-text-input id="email" name="email" type="email" class="mt-1 block w-full" :value="old('email', $guru->email)" required />
                    <x-input-error class="mt-2" :messages="$errors->get('email')" />
                </div>

                <div>
                    <x-input-label for="no_telepon" :value="__('No. Telepon / WhatsApp')" />
                    <x-text-input id="no_telepon" name="no_telepon" type="text" class="mt-1 block w-full" :value="old('no_telepon', $guru->no_telepon)" required />
                    <x-input-error class="mt-2" :messages="$errors->get('no_telepon')" />
                </div>

                <div>
                    <x-input-label for="jenis_kelamin" :value="__('Jenis Kelamin')" />
                    <select id="jenis_kelamin" name="jenis_kelamin" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" required>
                        <option value="" disabled>-- Pilih Jenis Kelamin --</option>
                        <option value="laki-laki" {{ old('jenis_kelamin', $guru->jenis_kelamin) === 'laki-laki' ? 'selected' : '' }}>Laki-laki</option>
                        <option value="perempuan" {{ old('jenis_kelamin', $guru->jenis_kelamin) === 'perempuan' ? 'selected' : '' }}>Perempuan</option>
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('jenis_kelamin')" />
                </div>

                <div>
                    <x-input-label for="role" :value="__('Hak Akses (Role)')" />
                    <select id="role" name="role" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" required onchange="toggleKelasSection()">
                        <option value="" disabled>-- Pilih Role Akses --</option>
                        <option value="operator" {{ old('role', $guru->role) === 'operator' ? 'selected' : '' }}>Operator</option>
                        <option value="guru" {{ old('role', $guru->role) === 'guru' ? 'selected' : '' }}>Guru</option>
                        <option value="kepala_sekolah" {{ old('role', $guru->role) === 'kepala_sekolah' ? 'selected' : '' }}>Kepala Sekolah</option>
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('role')" />
                </div>
            </div>

            <div>
                <x-input-label for="alamat" :value="__('Alamat Tempat Tinggal')" />
                <textarea id="alamat" name="alamat" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">{{ old('alamat', $guru->alamat) }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('alamat')" />
            </div>

            <div id="kelas-section" class="hidden bg-gray-50 p-4 rounded-md border border-gray-200">
                <h3 class="text-sm font-medium text-gray-700 mb-2 font-semibold">Pilih Kelas Yang Diampu:</h3>
                <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 gap-4">
                    @foreach($kelas as $k)
                        <label class="inline-flex items-center text-sm text-gray-600 cursor-pointer">
                            <input type="checkbox" name="kelas[]" value="{{ $k->id }}" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" 
                                {{ (is_array(old('kelas')) && in_array($k->id, old('kelas'))) || (!is_array(old('kelas')) && $guru->kelas->contains($k->id)) ? 'checked' : '' }}>
                            <span class="ms-2">{{ $k->nama_kelas }}</span>
                        </label>
                    @endforeach
                </div>
                <x-input-error class="mt-2" :messages="$errors->get('kelas')" />
            </div>

            <div class="bg-amber-50 p-4 rounded-md border border-amber-200 text-sm text-amber-700">
                <p class="font-medium">💡 Informasi Password</p>
                <p class="text-xs mt-1">Kosongkan kolom password di bawah ini jika tidak ingin mengganti password lama pengguna.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-gray-100">
                <div>
                    <x-input-label for="password" :value="__('Password Baru (Opsional)')" />
                    <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" autocomplete="new-password" />
                    <x-input-error class="mt-2" :messages="$errors->get('password')" />
                </div>

                <div>
                    <x-input-label for="password_confirmation" :value="__('Konfirmasi Password Baru')" />
                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" />
                </div>
            </div>

            <div class="flex items-center justify-end gap-4 pt-4">
                <x-primary-button class="bg-blue-600 hover:bg-blue-700">
                    {{ __('Perbarui Data User') }}
                </x-primary-button>
            </div>
        </form>

    </x-form-card>

    @vite(['resources/js/form-guru.js'])
</x-app-layout>