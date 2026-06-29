<x-app-layout>
    <x-form-card :title="__('Import Data Siswa')" :backUrl="route('siswa.index')">
        @if (session('error'))
            <div class="mb-6 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded shadow-sm">
                {{ session('error') }}
            </div>
        @endif

        <div class="mb-6 rounded-md border border-blue-200 bg-blue-50 p-4 text-sm text-blue-800">
            <p class="font-semibold mb-2">Format kolom yang didukung:</p>
            <p class="leading-6">
                nis, nisn, nama_siswa, jenis_kelamin, kelas, nama_ayah, no_whatsapp_ayah,
                nama_ibu, no_whatsapp_ibu, nama_wali, no_whatsapp_wali, status.
            </p>
            <p class="mt-2">
                Kolom <span class="font-semibold">kelas</span> harus sama dengan nama kelas di master data, misalnya 1-A.
                Jenis kelamin dapat diisi laki-laki/perempuan, L/P, atau LK/PR.
            </p>
        </div>

        <form method="POST" action="{{ route('siswa.import') }}" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div class="bg-gray-50 p-4 rounded-md border border-gray-200">
                <x-input-label for="file" :value="__('File Excel / CSV')" />
                <input
                    id="file"
                    name="file"
                    type="file"
                    accept=".xlsx,.csv"
                    class="mt-1 block w-full rounded-md border border-gray-300 bg-white px-3 py-2 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    required
                >
                <x-input-error class="mt-2" :messages="$errors->get('file')" />

                <div class="mt-3 text-sm text-gray-600">
                    <a href="{{ route('siswa.template-import') }}" class="font-semibold text-blue-600 hover:text-blue-800">
                        Download template import
                    </a>
                </div>
            </div>

            <div class="rounded-md border border-yellow-200 bg-yellow-50 p-4 text-sm text-yellow-800">
                Data dengan NISN atau NIS yang sudah ada akan diperbarui. Data tanpa NISN/NIS akan dibuat sebagai siswa baru.
            </div>

            <div class="flex items-center justify-end gap-4 pt-4 border-t border-gray-100">
                <a href="{{ route('siswa.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-50">
                    Batal
                </a>
                <x-primary-button class="bg-emerald-600 hover:bg-emerald-700">
                    {{ __('Import Data') }}
                </x-primary-button>
            </div>
        </form>
    </x-form-card>
</x-app-layout>
