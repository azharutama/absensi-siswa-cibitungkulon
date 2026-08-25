<x-app-layout>
    <x-form-card :title="__('Edit Data Siswa')" :backUrl="route('siswa.index')">
        
        <form method="POST" action="{{ route('siswa.update', $siswa->id) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div class="bg-gray-50 p-4 rounded-md border border-gray-200 mb-6">
                <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wider">A. Data Pribadi Siswa</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <x-input-label for="nis" :value="__('NIS (Nomor Induk Siswa) *')" />
                        <x-text-input id="nis" name="nis" type="text" class="mt-1 block w-full" :value="old('nis', $siswa->nis)" placeholder="Contoh: 20260001" required />
                        <x-input-error class="mt-2" :messages="$errors->get('nis')" />
                    </div>

                    <div>
                        <x-input-label for="nisn" :value="__('NISN *')" />
                        <x-text-input id="nisn" name="nisn" type="text" class="mt-1 block w-full" :value="old('nisn', $siswa->nisn)" placeholder="Contoh: 0099123456" required />
                        <x-input-error class="mt-2" :messages="$errors->get('nisn')" />
                    </div>

                    <div class="md:col-span-2">
                        <x-input-label for="nama_siswa" :value="__('Nama Lengkap Siswa *')" />
                        <x-text-input id="nama_siswa" name="nama_siswa" type="text" class="mt-1 block w-full" :value="old('nama_siswa', $siswa->nama_siswa)" placeholder="Contoh: Ahmad Fauzi" required />
                        <x-input-error class="mt-2" :messages="$errors->get('nama_siswa')" />
                    </div>

                    <div>
                        <x-input-label for="kelas_id" :value="__('Kelas *')" />
                        @if($siswa->hasAbsensi())
                            <div class="mt-1">
                                <input type="hidden" name="kelas_id" value="{{ $siswa->kelas_id }}">
                                <select id="kelas_id" name="kelas_id_disabled" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm text-sm bg-gray-100 cursor-not-allowed" disabled>
                                    @foreach($kelas as $k)
                                        <option value="{{ $k->id }}" {{ $siswa->kelas_id == $k->id ? 'selected' : '' }}>
                                            {{ $k->nama_kelas }}
                                        </option>
                                    @endforeach
                                </select>
                                <p class="mt-1 text-xs text-red-600">
                                    <svg class="inline h-3 w-3 mr-1" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                                    Kelas tidak bisa diubah karena siswa sudah memiliki data absensi.
                                </p>
                            </div>
                        @else
                            <select id="kelas_id" name="kelas_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" required>
                                <option value="" disabled>-- Pilih Kelas --</option>
                                @foreach($kelas as $k)
                                    <option value="{{ $k->id }}" {{ old('kelas_id', $siswa->kelas_id) == $k->id ? 'selected' : '' }}>
                                        {{ $k->nama_kelas }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error class="mt-2" :messages="$errors->get('kelas_id')" />
                        @endif
                    </div>

                    <div>
                        <x-input-label for="jenis_kelamin" :value="__('Jenis Kelamin *')" />
                        <select id="jenis_kelamin" name="jenis_kelamin" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" required>
                            <option value="laki-laki" {{ old('jenis_kelamin', $siswa->jenis_kelamin) === 'laki-laki' ? 'selected' : '' }}>Laki-laki</option>
                            <option value="perempuan" {{ old('jenis_kelamin', $siswa->jenis_kelamin) === 'perempuan' ? 'selected' : '' }}>Perempuan</option>
                        </select>
                        <x-input-error class="mt-2" :messages="$errors->get('jenis_kelamin')" />
                    </div>
                </div>

                <div class="mt-4">
                    <x-input-label for="alamat" :value="__('Alamat Rumah *')" />
                    <textarea id="alamat" name="alamat" rows="2" placeholder="Contoh: Jl. Merdeka No. 10, Cibitung" required class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">{{ old('alamat', $siswa->alamat) }}</textarea>
                    <x-input-error class="mt-2" :messages="$errors->get('alamat')" />
                </div>
            </div>

            <div class="bg-gray-50 p-4 rounded-md border border-gray-200">
                <h3 class="text-sm font-semibold text-gray-700 mb-4 uppercase tracking-wider">B. Data Orang Tua</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <x-input-label for="nama_ayah" :value="__('Nama Ayah *')" />
                        <x-text-input id="nama_ayah" name="nama_ayah" type="text" class="mt-1 block w-full" :value="old('nama_ayah', $siswa->nama_ayah)" placeholder="Contoh: Budi Santoso" required />
                        <x-input-error class="mt-2" :messages="$errors->get('nama_ayah')" />
                    </div>

                    <div>
                        <x-input-label for="no_whatsapp_ayah" :value="__('No. WhatsApp Ayah (Opsional)')" />
                        <x-text-input id="no_whatsapp_ayah" name="no_whatsapp_ayah" type="text" class="mt-1 block w-full" :value="old('no_whatsapp_ayah', $siswa->no_whatsapp_ayah)" placeholder="Contoh: 08123456789" />
                        <x-input-error class="mt-2" :messages="$errors->get('no_whatsapp_ayah')" />
                    </div>

                    <div>
                        <x-input-label for="nama_ibu" :value="__('Nama Ibu *')" />
                        <x-text-input id="nama_ibu" name="nama_ibu" type="text" class="mt-1 block w-full" :value="old('nama_ibu', $siswa->nama_ibu)" placeholder="Contoh: Siti Aminah" required />
                        <x-input-error class="mt-2" :messages="$errors->get('nama_ibu')" />
                    </div>

                    <div>
                        <x-input-label for="no_whatsapp_ibu" :value="__('No. WhatsApp Ibu (Opsional)')" />
                        <x-text-input id="no_whatsapp_ibu" name="no_whatsapp_ibu" type="text" class="mt-1 block w-full" :value="old('no_whatsapp_ibu', $siswa->no_whatsapp_ibu)" placeholder="Contoh: 08123456789" />
                        <x-input-error class="mt-2" :messages="$errors->get('no_whatsapp_ibu')" />
                    </div>
                </div>

                <p class="mt-4 text-xs text-gray-500">Minimal salah satu nomor WhatsApp ayah atau ibu wajib diisi.</p>
            </div>

            <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-100">
                <x-primary-button>
                    {{ __('Perbarui Data Siswa') }}
                </x-primary-button>
            </div>
        </form>

    </x-form-card>
</x-app-layout>
