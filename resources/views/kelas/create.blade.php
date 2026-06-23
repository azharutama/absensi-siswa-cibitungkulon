<x-app-layout>
    <x-form-card :title="__('Tambah Data Kelas Baru')" :backUrl="route('kelas.index')" maxWidth="max-w-2xl">
        
        <div class="mb-6 p-4 bg-indigo-50 border border-indigo-100 rounded-md text-sm text-indigo-700 flex justify-between items-center">
            <span class="font-medium">Periode Akademik Aktif:</span>
            <span class="font-semibold bg-indigo-200 px-3 py-1 rounded text-xs uppercase">
                {{ $periodeAktif->nama_periode ?? 'Belum Ada Periode Aktif' }}
            </span>
        </div>

        <form method="POST" action="{{ route('kelas.store') }}" class="space-y-6">
            @csrf

            <div>
                <x-input-label for="nama_kelas" :value="__('Nama Kelas')" />
                <x-text-input id="nama_kelas" name="nama_kelas" type="text" class="mt-1 block w-full" :value="old('nama_kelas')" placeholder="Contoh: Kelas 1-A, XII RPL 2" required autofocus />
                <x-input-error class="mt-2" :messages="$errors->get('nama_kelas')" />
            </div>

            <div>
                <x-input-label for="guru_id" :value="__('Wali Kelas')" />
                <select id="guru_id" name="guru_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" required>
                    <option value="" disabled selected>-- Pilih Wali Kelas --</option>
                    @foreach($gurus as $guru)
                        <option value="{{ $guru->id }}" {{ old('guru_id') == $guru->id ? 'selected' : '' }}>
                            {{ $guru->nama }} (NIP: {{ $guru->nip ?? '-' }})
                        </option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('guru_id')" />
            </div>

            <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-100">
                <x-primary-button class="bg-blue-600 hover:bg-blue-700">
                    {{ __('Simpan Kelas') }}
                </x-primary-button>
            </div>
        </form>

    </x-form-card>
</x-app-layout>