<x-app-layout>
    <x-form-card :title="__('Edit Data Pengguna / Guru')" :backUrl="route('guru.index')">
        
        <form method="POST" action="{{ route('guru.update', $guru->id) }}" data-guru-form class="space-y-6">
            @csrf
            @method('PUT')             <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <x-input-label for="nip" :value="__('NIP (Nomor Induk Pegawai) *')" />
                    <x-text-input id="nip" name="nip" type="text" class="mt-1 block w-full" :value="old('nip', $guru->nip)" placeholder="Contoh: 197801012005011001" required autofocus />
                    <x-input-error class="mt-2" :messages="$errors->get('nip')" />
                </div>

                <div>
                    <x-input-label for="username" :value="__('Username *')" />
                    <x-text-input id="username" name="username" type="text" class="mt-1 block w-full" :value="old('username', $guru->username)" placeholder="Contoh: ahmadfauzi" required />
                    <x-input-error class="mt-2" :messages="$errors->get('username')" />
                </div>

                <div>
                    <x-input-label for="nama" :value="__('Nama Lengkap *')" />
                    <x-text-input id="nama" name="nama" type="text" class="mt-1 block w-full" :value="old('nama', $guru->nama)" placeholder="Contoh: Ahmad Fauzi" required />
                    <x-input-error class="mt-2" :messages="$errors->get('nama')" />
                </div>

                <div>
                    <x-input-label for="no_telepon" :value="__('No. Telepon / WhatsApp *')" />
                    <x-text-input id="no_telepon" name="no_telepon" type="text" class="mt-1 block w-full" :value="old('no_telepon', $guru->no_telepon)" placeholder="Contoh: 081234567890" required />
                    <x-input-error class="mt-2" :messages="$errors->get('no_telepon')" />
                </div>

                <div>
                    <x-input-label for="jenis_kelamin" :value="__('Jenis Kelamin *')" />
                    <select id="jenis_kelamin" name="jenis_kelamin" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" required>
                        <option value="" disabled>-- Pilih Jenis Kelamin --</option>
                        <option value="laki-laki" {{ old('jenis_kelamin', $guru->jenis_kelamin) === 'laki-laki' ? 'selected' : '' }}>Laki-laki</option>
                        <option value="perempuan" {{ old('jenis_kelamin', $guru->jenis_kelamin) === 'perempuan' ? 'selected' : '' }}>Perempuan</option>
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('jenis_kelamin')" />
                </div>

                <div>
                    <x-input-label for="role" :value="__('Hak Akses (Role) *')" />
                    <select id="role" name="role" data-role-select class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" required>
                        <option value="" disabled>-- Pilih Role Akses --</option>
                        <option value="operator" {{ old('role', $guru->role) === 'operator' ? 'selected' : '' }}>Operator</option>
                        <option value="guru" {{ old('role', $guru->role) === 'guru' ? 'selected' : '' }}>Guru</option>
                        <option value="kepala_sekolah" {{ old('role', $guru->role) === 'kepala_sekolah' ? 'selected' : '' }}>Kepala Sekolah</option>
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('role')" />
                </div>
            </div>

            <div>
                <x-input-label for="alamat" :value="__('Alamat Tempat Tinggal *')" />
                <textarea id="alamat" name="alamat" rows="3" placeholder="Contoh: Jl. Merdeka No. 10, Cibitung" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">{{ old('alamat', $guru->alamat) }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('alamat')" />
            </div>

            <div id="kelas-section" data-kelas-section class="bg-gray-50 p-4 rounded-md border border-gray-200">
                <x-input-label for="kelas_id" :value="__('Kelas yang Diampu (Opsional)')" />
                <select id="kelas_id" name="kelas_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                    <option value="" {{ !old('kelas_id', $guru->kelas?->id) ? 'selected' : '' }}>-- Belum ditentukan --</option>
                    @foreach($kelas as $k)
                        <option value="{{ $k->id }}" {{ (string)old('kelas_id', $guru->kelas?->id) === (string)$k->id ? 'selected' : '' }}>
                            {{ $k->nama_kelas }}
                        </option>
                    @endforeach
                </select>
                @if($kelas->isEmpty())
                    <p class="mt-2 text-xs text-gray-500">
                        Belum ada kelas yang tersedia. Kelas dapat diatur setelah data guru dibuat.
                    </p>
                @endif
                <x-input-error class="mt-2" :messages="$errors->get('kelas_id')" />
            </div>

            <div class="bg-amber-50 p-4 rounded-md border border-amber-200 text-sm text-amber-700">
                <p class="font-medium">💡 Informasi Password</p>
                <p class="text-xs mt-1">Kosongkan kolom password di bawah ini jika tidak ingin mengganti password lama pengguna.</p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 pt-4 border-t border-gray-100">
                <div>
                    <x-input-label for="password" :value="__('Password Baru (Opsional)')" />
                    <x-text-input id="password" name="password" type="password" class="mt-1 block w-full" placeholder="Minimal 8 karakter" autocomplete="new-password" />
                    <x-input-error class="mt-2" :messages="$errors->get('password')" />
                </div>

                <div>
                    <x-input-label for="password_confirmation" :value="__('Konfirmasi Password Baru (Opsional)')" />
                    <x-text-input id="password_confirmation" name="password_confirmation" type="password" class="mt-1 block w-full" placeholder="Ulangi password baru" />
                </div>
            </div>

            <div class="flex items-center justify-end gap-4 pt-4">
                <x-primary-button class="bg-blue-600 hover:bg-blue-700">
                    {{ __('Perbarui Data User') }}
                </x-primary-button>
            </div>
        </form>

    </x-form-card>

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const roleSelect = document.querySelector('[data-role-select]');
            const kelasSection = document.querySelector('[data-kelas-section]');
            
            function toggleKelasSection() {
                if (roleSelect.value === 'guru') {
                    kelasSection.classList.remove('hidden');
                    kelasSection.setAttribute('aria-hidden', 'false');
                } else {
                    kelasSection.classList.add('hidden');
                    kelasSection.setAttribute('aria-hidden', 'true');
                }
            }
            
            if (roleSelect && kelasSection) {
                toggleKelasSection();
                roleSelect.addEventListener('change', toggleKelasSection);
            }
        });
    </script>
@endpush
</x-app-layout>
