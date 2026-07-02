<x-app-layout>
    <x-form-card :title="__('Edit Data Kelas')" :backUrl="route('kelas.index')" maxWidth="max-w-2xl">
        
        <div class="mb-6 p-4 bg-gray-50 border border-gray-200 rounded-md text-sm text-gray-600 flex justify-between items-center">
            <span class="font-medium">Periode Kelas Ini:</span>
            <span class="font-semibold bg-gray-200 text-gray-700 px-3 py-1 rounded text-xs uppercase">
                {{ $kelas->periode->nama_periode ?? $periodeAktif->nama_periode ?? '-' }}
            </span>
        </div>

        <form method="POST" action="{{ route('kelas.update', $kelas->id) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div>
                <x-input-label for="nama_kelas" :value="__('Nama Kelas')" />
                <x-text-input id="nama_kelas" name="nama_kelas" type="text" class="mt-1 block w-full" :value="old('nama_kelas', $kelas->nama_kelas)" required autofocus />
                <x-input-error class="mt-2" :messages="$errors->get('nama_kelas')" />
            </div>

            <div>
                <x-input-label for="guru_id" :value="__('Wali Kelas')" />
                <select id="guru_id" name="guru_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" required>
                    <option value="" disabled>-- Pilih Wali Kelas --</option>
                    @foreach($gurus as $guru)
                        <option value="{{ $guru->id }}" {{ old('guru_id', $currentWaliId) == $guru->id ? 'selected' : '' }}>
                            {{ $guru->nama }} (NIP: {{ $guru->nip ?? '-' }})
                        </option>
                    @endforeach
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('guru_id')" />
            </div>

            <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-100">
                <x-primary-button class="bg-amber-600 hover:bg-amber-700">
                    {{ __('Perbarui Kelas') }}
                </x-primary-button>
            </div>
        </form>

    </x-form-card>
</x-app-layout>
