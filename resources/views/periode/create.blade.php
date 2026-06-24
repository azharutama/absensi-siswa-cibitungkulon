<x-app-layout>
    <x-form-card :title="__('Tambah Periode Tahun Ajaran')" :backUrl="route('periode.index')" maxWidth="max-w-5xl">
        
        <form method="POST" action="{{ route('periode.store') }}" class="space-y-8" x-data="{ 
            listMingguan: [],
            listNasional: [],
            addMingguan() {
                this.listMingguan.push({ hari: 'Minggu', keterangan: 'Libur Rutin Mingguan' });
            },
            removeMingguan(index) {
                this.listMingguan.splice(index, 1);
            },
            addNasional() {
                this.listNasional.push({ tanggal: '', nama_libur: '', keterangan: '' });
            },
            removeNasional(index) {
                this.listNasional.splice(index, 1);
            }
        }">
            @csrf

            <div class="space-y-4">
                <h3 class="text-sm font-bold text-gray-900 border-b pb-2">Data Periode</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
                    <x-input-label for="nama_periode" :value="__('Tahun Ajaran *')" class="md:text-right md:pe-4" />
                    <div class="md:col-span-2">
                        <x-text-input id="nama_periode" name="nama_periode" type="text" class="w-full" :value="old('nama_periode')" placeholder="Contoh: 2026/2027" required autofocus />
                        <x-input-error class="mt-1" :messages="$errors->get('nama_periode')" />
                    </div>

                    <x-input-label for="tanggal_mulai" :value="__('Tanggal Mulai *')" class="md:text-right md:pe-4" />
                    <div class="md:col-span-2">
                        <x-text-input id="tanggal_mulai" name="tanggal_mulai" type="date" class="w-full" :value="old('tanggal_mulai')" required />
                        <x-input-error class="mt-1" :messages="$errors->get('tanggal_mulai')" />
                    </div>

                    <x-input-label for="tanggal_selesai" :value="__('Tanggal Selesai *')" class="md:text-right md:pe-4" />
                    <div class="md:col-span-2">
                        <x-text-input id="tanggal_selesai" name="tanggal_selesai" type="date" class="w-full" :value="old('tanggal_selesai')" required />
                        <x-input-error class="mt-1" :messages="$errors->get('tanggal_selesai')" />
                    </div>

                    <x-input-label :value="__('Status Periode *')" class="md:text-right md:pe-4" />
                    <div class="md:col-span-2 flex flex-col gap-2 pt-1">
                        <label class="inline-flex items-center text-sm text-gray-700 cursor-pointer">
                            <input type="radio" name="status_aktif" value="1" {{ old('status_aktif', '0') == '1' ? 'checked' : '' }} class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ms-2">Aktif</span>
                        </label>
                        <label class="inline-flex items-center text-sm text-gray-700 cursor-pointer">
                            <input type="radio" name="status_aktif" value="0" {{ old('status_aktif', '0') == '0' ? 'checked' : '' }} class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ms-2">Nonaktif</span>
                        </label>
                    </div>
                </div>
            </div>

            <div class="space-y-2 pt-4 border-t border-gray-200">
                <h3 class="text-sm font-bold text-gray-900 mb-4">Hari Libur</h3>
                
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    
                    <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                        <div class="flex justify-between items-center mb-3">
                            <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wide">Hari Libur Mingguan</h4>
                            <button type="button" @click="addMingguan()" class="px-2.5 py-1 text-xs font-semibold border border-gray-300 rounded hover:bg-gray-50 text-gray-700 transition">
                                + Tambah Mingguan
                            </button>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 border text-xs">
                                <thead class="bg-gray-50 font-semibold text-gray-700">
                                    <tr>
                                        <th class="px-3 py-2 text-left w-12">No</th>
                                        <th class="px-3 py-2 text-left w-32">Hari</th>
                                        <th class="px-3 py-2 text-left">Keterangan</th>
                                        <th class="px-3 py-2 text-center w-16">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <template x-for="(item, index) in listMingguan" :key="index">
                                        <tr>
                                            <td class="px-3 py-2 text-gray-500 text-center" x-text="index + 1"></td>
                                            <td class="px-2 py-1">
                                                <select :name="`libur_mingguan[${index}][hari]`" x-model="item.hari" class="w-full text-xs p-1 rounded border-gray-300 focus:ring-indigo-500">
                                                    <option value="Minggu">Minggu</option>
                                                    <option value="Sabtu">Sabtu</option>
                                                    <option value="Jumat">Jumat</option>
                                                </select>
                                            </td>
                                            <td class="px-2 py-1">
                                                <input type="text" :name="`libur_mingguan[${index}][keterangan]`" x-model="item.keterangan" class="w-full text-xs p-1 rounded border-gray-300 focus:ring-indigo-500" required>
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <button type="button" @click="removeMingguan(index)" class="text-red-600 hover:text-red-900 font-medium">Hapus</button>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="listMingguan.length === 0">
                                        <td colspan="4" class="px-3 py-6 text-center text-gray-400 bg-gray-50/50">Belum ada data hari libur mingguan</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-2 italic">* Minimal tambahkan 1 hari libur mingguan</p>
                    </div>

                    <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                        <div class="flex justify-between items-center mb-3">
                            <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wide">Hari Libur Nasional</h4>
                            <button type="button" @click="addNasional()" class="px-2.5 py-1 text-xs font-semibold border border-gray-300 rounded hover:bg-gray-50 text-gray-700 transition">
                                + Tambah Nasional
                            </button>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 border text-xs">
                                <thead class="bg-gray-50 font-semibold text-gray-700">
                                    <tr>
                                        <th class="px-3 py-2 text-left w-12">No</th>
                                        <th class="px-3 py-2 text-left w-28">Tanggal</th>
                                        <th class="px-3 py-2 text-left">Nama Hari Libur</th>
                                        <th class="px-3 py-2 text-left">Keterangan</th>
                                        <th class="px-3 py-2 text-center w-16">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200 bg-white">
                                    <template x-for="(item, index) in listNasional" :key="index">
                                        <tr>
                                            <td class="px-3 py-2 text-gray-500 text-center" x-text="index + 1"></td>
                                            <td class="px-2 py-1">
                                                <input type="date" :name="`libur_nasional[${index}][tanggal]`" x-model="item.tanggal" class="w-full text-xs p-1 rounded border-gray-300 focus:ring-indigo-500" required>
                                            </td>
                                            <td class="px-2 py-1">
                                                <input type="text" :name="`libur_nasional[${index}][nama_libur]`" x-model="item.nama_libur" placeholder="Misal: Idul Fitri" class="w-full text-xs p-1 rounded border-gray-300 focus:ring-indigo-500" required>
                                            </td>
                                            <td class="px-2 py-1">
                                                <input type="text" :name="`libur_nasional[${index}][keterangan]`" x-model="item.keterangan" placeholder="Opsional" class="w-full text-xs p-1 rounded border-gray-300 focus:ring-indigo-500">
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                <button type="button" @click="removeNasional(index)" class="text-red-600 hover:text-red-900 font-medium">Hapus</button>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="listNasional.length === 0">
                                        <td colspan="5" class="px-3 py-6 text-center text-gray-400 bg-gray-50/50">Belum ada data hari libur nasional</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-2 italic">* Minimal tambahkan 1 hari libur nasional</p>
                    </div>

                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('periode.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 hover:bg-gray-50 transition">
                    Batal
                </a>
                <x-primary-button class="bg-gray-900 hover:bg-gray-800 text-white">
                    {{ __('Simpan') }}
                </x-primary-button>
            </div>
        </form>

    </x-form-card>
</x-app-layout>