<x-app-layout>
    <x-form-card :title="__('Tambah Data Siswa Baru')" :backUrl="route('siswa.index')">
        
        <form method="POST" action="{{ route('siswa.store') }}" class="space-y-6">
            @csrf

            <div class="bg-gray-50 p-4 rounded-md border border-gray-200 mb-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wider">A. Data Pribadi Siswa</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <x-input-label for="nisn" :value="__('NISN')" />
                        <x-text-input id="nisn" name="nisn" type="text" class="mt-1 block w-full" :value="old('nisn')" required autofocus />
                        <x-input-error class="mt-2" :messages="$errors->get('nisn')" />
                    </div>

                    <div>
                        <x-input-label for="nama_siswa" :value="__('Nama Lengkap Siswa')" />
                        <x-text-input id="nama_siswa" name="nama_siswa" type="text" class="mt-1 block w-full" :value="old('nama_siswa')" required />
                        <x-input-error class="mt-2" :messages="$errors->get('nama_siswa')" />
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

                <div class="mt-4">
                    <x-input-label for="alamat" :value="__('Alamat Rumah')" />
                    <textarea id="alamat" name="alamat" rows="2" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">{{ old('alamat') }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('alamat')" />
                </div>
            </div>

            <div class="bg-gray-50 p-4 rounded-md border border-gray-200">
                <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wider">B. Data Orang Tua / Wali</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <x-input-label for="nama_ayah" :value="__('Nama Ayah')" />
                        <x-text-input id="nama_ayah" name="nama_ayah" type="text" class="mt-1 block w-full" :value="old('nama_ayah')" required />
                        <x-input-error class="mt-2" :messages="$errors->get('nama_ayah')" />
                    </div>

                    <div>
                        <x-input-label for="no_whatsapp_ayah" :value="__('No. WhatsApp Ayah')" />
                        <x-text-input id="no_whatsapp_ayah" name="no_whatsapp_ayah" type="text" class="mt-1 block w-full" :value="old('no_whatsapp_ayah')" placeholder="Contoh: 08123456789" required />
                        <x-input-error class="mt-2" :messages="$errors->get('no_whatsapp_ayah')" />
                    </div>

                    <div>
                        <x-input-label for="nama_ibu" :value="__('Nama Ibu')" />
                        <x-text-input id="nama_ibu" name="nama_ibu" type="text" class="mt-1 block w-full" :value="old('nama_ibu')" required />
                        <x-input-error class="mt-2" :messages="$errors->get('nama_ibu')" />
                    </div>

                    <div>
                        <x-input-label for="no_whatsapp_ibu" :value="__('No. WhatsApp Ibu')" />
                        <x-text-input id="no_whatsapp_ibu" name="no_whatsapp_ibu" type="text" class="mt-1 block w-full" :value="old('no_whatsapp_ibu')" placeholder="Contoh: 08123456789" required />
                        <x-input-error class="mt-2" :messages="$errors->get('no_whatsapp_ibu')" />
                    </div>

                    <div>
                        <x-input-label for="nama_wali" :value="__('Nama Wali (Opsional)')" />
                        <x-text-input id="nama_wali" name="nama_wali" type="text" class="mt-1 block w-full" :value="old('nama_wali')" />
                        <x-input-error class="mt-2" :messages="$errors->get('nama_wali')" />
                    </div>

                    <div>
                        <x-input-label for="no_whatsapp_wali" :value="__('No. WhatsApp Wali (Opsional)')" />
                        <x-text-input id="no_whatsapp_wali" name="no_whatsapp_wali" type="text" class="mt-1 block w-full" :value="old('no_whatsapp_wali')" />
                        <x-input-error class="mt-2" :messages="$errors->get('no_whatsapp_wali')" />
                    </div>
                </div>
            </div>

            <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-100">
                <x-primary-button class="bg-blue-600 hover:bg-blue-700">
                    {{ __('Simpan Data Siswa') }}
                </x-primary-button>
            </div>
        </form>

    </x-form-card>
</x-app-layout>