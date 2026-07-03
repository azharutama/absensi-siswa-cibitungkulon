<x-app-layout>
    <x-form-card :title="__('Tambah Data Kelas Baru')" :backUrl="route('kelas.index')" maxWidth="max-w-2xl">
        
        <div class="mb-6 p-4 {{ $periodeAktif ? 'bg-indigo-50 border-indigo-100 text-indigo-700' : 'bg-red-50 border-red-100 text-red-700' }} border rounded-md text-sm flex flex-col sm:flex-row justify-between gap-3 sm:items-center">
            <span class="font-medium">Periode Akademik Aktif:</span>
            <span class="font-semibold {{ $periodeAktif ? 'bg-indigo-200' : 'bg-red-100' }} px-3 py-1 rounded text-xs uppercase">
                {{ $periodeAktif->nama_periode ?? 'Belum Ada Periode Aktif' }}
            </span>
        </div>

        @if(! $periodeAktif)
            <div class="mb-6 p-4 bg-red-50 border border-red-100 rounded-md text-sm text-red-700">
                Buat atau aktifkan periode akademik terlebih dahulu sebelum menambah kelas.
                <a href="{{ route('periode.create') }}" class="font-semibold underline">Tambah Periode</a>
            </div>
        @endif

        <form method="POST" action="{{ route('kelas.store') }}" class="space-y-6">
            @csrf

            <div>
                <x-input-label for="nama_kelas" :value="__('Nama Kelas')" />
                <x-text-input id="nama_kelas" name="nama_kelas" type="text" class="mt-1 block w-full" :value="old('nama_kelas')" placeholder="Contoh: Kelas 1-A, XII RPL 2" required autofocus />
                <x-input-error class="mt-2" :messages="$errors->get('nama_kelas')" />
            </div>

            <div>
                <x-input-label for="guru_id" :value="__('Wali Kelas (Opsional)')" />
                <select id="guru_id" name="guru_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                    <option value="" selected>-- Belum ditentukan --</option>
                    @foreach($gurus as $guru)
                        <option value="{{ $guru->id }}" {{ old('guru_id') == $guru->id ? 'selected' : '' }}>
                            {{ $guru->nama }} (NIP: {{ $guru->nip ?? '-' }})
                        </option>
                    @endforeach
                </select>
                @if($gurus->isEmpty())
                    <p class="mt-2 text-xs text-gray-500">
                        Belum ada guru yang tersedia sebagai wali kelas. Kelas tetap bisa dibuat, lalu wali kelas dapat diatur setelah data guru tersedia.
                    </p>
                @endif
                <x-input-error class="mt-2" :messages="$errors->get('guru_id')" />
            </div>

            <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-100">
                <x-primary-button class="bg-blue-600 hover:bg-blue-700 disabled:opacity-50 disabled:cursor-not-allowed" :disabled="! $periodeAktif">
                    {{ __('Simpan Kelas') }}
                </x-primary-button>
            </div>
        </form>

    </x-form-card>
</x-app-layout>
