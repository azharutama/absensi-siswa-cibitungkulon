<x-app-layout>
    <x-form-card :title="__('Tambah Data Siswa Baru')" :backUrl="route('siswa.index')">
        
        <form method="POST" action="{{ route('siswa.store') }}" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <x-input-label for="nisn" :value="__('NISN (Nomor Induk Siswa Nasional)')" />
                    <x-text-input id="nisn" name="nisn" type="text" class="mt-1 block w-full" :value="old('nisn')" required autofocus />
                    <x-input-error class="mt-2" :messages="$errors->get('nisn')" />
                </div>

                <div>
                    <x-input-label for="nama" :value="__('Nama Lengkap Siswa')" />
                    <x-text-input id="nama" name="nama" type="text" class="mt-1 block w-full" :value="old('nama')" required />
                    <x-input-error class="mt-2" :messages="$errors->get('nama')" />
                </div>

                <div>
                    <x-input-label for="kelas_id" :value="__('Kelas')" />
                    <select id="kelas_id" name="kelas_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" required>
                        <option value="" disabled selected>-- Pilih Kelas --</option>
                        @foreach($kelas as $k)
                            <option value="{{ $k->id }}" {{ old('kelas_id') == $k->id ? 'selected' : '' }}>
                                {{ $k->nama_kelas }}
                            </option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('kelas_id')" />
                </div>

                <div>
                    <x-input-label for="jenis_kelamin" :value="__('Jenis Kelamin')" />
                    <select id="jenis_kelamin" name="jenis_kelamin" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" required>
                        <option value="" disabled selected>-- Pilih Jenis Kelamin --</option>
                        <option value="laki-laki" {{ old('jenis_kelamin') === 'laki-laki' ? 'selected' : '' }}>Laki-laki</option>
                        <option value="perempuan" {{ old('jenis_kelamin') === 'perempuan' ? 'selected' : '' }}>Perempuan</option>
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('jenis_kelamin')" />
                </div>
            </div>

            <div>
                <x-input-label for="alamat" :value="__('Alamat Rumah')" />
                <textarea id="alamat" name="alamat" rows="3" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">{{ old('alamat') }}</textarea>
                <x-input-error class="mt-2" :messages="$errors->get('alamat')" />
            </div>

            <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-100">
                <x-primary-button class="bg-blue-600 hover:bg-blue-700">
                    {{ __('Simpan Data Siswa') }}
                </x-primary-button>
            </div>
        </form>

    </x-form-card>
</x-app-layout>