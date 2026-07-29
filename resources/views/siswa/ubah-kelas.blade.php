<x-app-layout>
    <x-form-card :title="__('Ubah Kelas')" :backUrl="route('siswa.index')">

        @if(session('success'))
            <div class="mb-6 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 rounded shadow-sm">
                {{ session('success') }}
            </div>
        @endif

        @if(session('error'))
            <div class="mb-6 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded shadow-sm">
                {{ session('error') }}
            </div>
        @endif

        <form method="POST" action="{{ route('siswa.ubah-kelas') }}" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <x-input-label for="kelas_asal_id" :value="__('Kelas Asal')" />
                    <select id="kelas_asal_id" name="kelas_asal_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" required>
                        <option value="" disabled>-- Pilih Kelas Asal --</option>
                        @foreach($kelas as $k)
                            <option value="{{ $k->id }}">{{ $k->nama_kelas }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('kelas_asal_id')" />
                </div>

                <div>
                    <x-input-label for="kelas_tujuan_id" :value="__('Kelas Tujuan')" />
                    <select id="kelas_tujuan_id" name="kelas_tujuan_id" class="mt-1 block w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm text-sm" required>
                        <option value="" disabled>-- Pilih Kelas Tujuan --</option>
                        @foreach($kelas as $k)
                            <option value="{{ $k->id }}">{{ $k->nama_kelas }}</option>
                        @endforeach
                    </select>
                    <x-input-error class="mt-2" :messages="$errors->get('kelas_tujuan_id')" />
                </div>
            </div>

            <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-100">
                <x-primary-button>
                    {{ __('Pindahkan Siswa') }}
                </x-primary-button>
            </div>
        </form>
    </x-form-card>
</x-app-layout>