<x-app-layout>
    <x-form-card :title="__('Edit Data Kelas')" :backUrl="route('kelas.index')" maxWidth="max-w-2xl">
        
        <form method="POST" action="{{ route('kelas.update', $kelas->id) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div>
                <x-input-label for="nama_kelas" :value="__('Nama Kelas *')" />
                <x-text-input id="nama_kelas" name="nama_kelas" type="text" class="mt-1 block w-full" :value="old('nama_kelas', $kelas->nama_kelas)" required autofocus />
                <x-input-error class="mt-2" :messages="$errors->get('nama_kelas')" />
            </div>

            <div>
                <x-input-label for="guru_id" :value="__('Guru (Opsional)')" />
                <select id="guru_id" name="guru_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm">
                    <option value="" {{ old('guru_id', $currentGuruId) ? '' : 'selected' }}>-- Belum ditentukan --</option>
                    @foreach($gurus as $guru)
                        <option value="{{ $guru->id }}" {{ old('guru_id', $currentGuruId) == $guru->id ? 'selected' : '' }}>
                            {{ $guru->nama }} (NIP: {{ $guru->nip ?? '-' }})
                        </option>
                    @endforeach
                </select>
                @if($gurus->isEmpty())
                    <p class="mt-2 text-xs text-gray-500">
                        Belum ada guru yang tersedia.
                    </p>
                @else
                    <p class="mt-2 text-xs text-gray-500">
                        Guru dapat dikosongkan sementara dan diatur kembali saat data guru sudah siap.
                    </p>
                @endif
                <x-input-error class="mt-2" :messages="$errors->get('guru_id')" />
            </div>

            <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-100">
                <x-primary-button>
                    {{ __('Perbarui Kelas') }}
                </x-primary-button>
            </div>
        </form>

    </x-form-card>
</x-app-layout>
