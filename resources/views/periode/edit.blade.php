<x-app-layout>
    <x-form-card :title="__('Edit Data Periode Akademik')" :backUrl="route('periode.index')" maxWidth="max-w-2xl">
        
        <form method="POST" action="{{ route('periode.update', $periode->id) }}" class="space-y-6">
            @csrf
            @method('PUT')

            <div>
                <x-input-label for="nama_periode" :value="__('Nama Periode Akademik')" />
                <x-text-input id="nama_periode" name="nama_periode" type="text" class="mt-1 block w-full" :value="old('nama_periode', $periode->nama_periode)" required autofocus />
                <x-input-error class="mt-2" :messages="$errors->get('nama_periode')" />
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                <div>
                    <x-input-label for="tanggal_mulai" :value="__('Tanggal Mulai')" />
                    <x-text-input id="tanggal_mulai" name="tanggal_mulai" type="date" class="mt-1 block w-full" :value="old('tanggal_mulai', $periode->tanggal_mulai)" required />
                    <x-input-error class="mt-2" :messages="$errors->get('tanggal_mulai')" />
                </div>

                <div>
                    <x-input-label for="tanggal_selesai" :value="__('Tanggal Selesai')" />
                    <x-text-input id="tanggal_selesai" name="tanggal_selesai" type="date" class="mt-1 block w-full" :value="old('tanggal_selesai', $periode->tanggal_selesai)" required />
                    <x-input-error class="mt-2" :messages="$errors->get('tanggal_selesai')" />
                </div>
            </div>

            <div>
                <x-input-label for="status_aktif" :value="__('Status Keaktifan')" />
                <select id="status_aktif" name="status_aktif" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" required>
                    <option value="0" {{ old('status_aktif', $periode->status_aktif) == 0 ? 'selected' : '' }}>Tidak Aktif</option>
                    <option value="1" {{ old('status_aktif', $periode->status_aktif) == 1 ? 'selected' : '' }}>Aktif</option>
                </select>
                <x-input-error class="mt-2" :messages="$errors->get('status_aktif')" />
            </div>

            <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-100">
                <x-primary-button class="bg-amber-600 hover:bg-amber-700">
                    {{ __('Perbarui Periode') }}
                </x-primary-button>
            </div>
        </form>

    </x-form-card>
</x-app-layout>