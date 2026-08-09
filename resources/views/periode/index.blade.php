@php
    $listMingguan = $liburMingguan ?? collect();
    $listNasional = $liburNasional ?? collect();
    $isEdit = $periode !== null;
@endphp

<x-app-layout>
    <x-form-card :title="__('Periode Aktif')" :backUrl="route('dashboard')" maxWidth="max-w-5xl">
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

        <form method="POST" action="{{ $isEdit ? route('periode.update', $periode->id) : route('periode.store') }}" class="space-y-8" x-data="{ 
            editMode: false,
            listMingguan: @js($listMingguan),
            listNasional: @js($listNasional),
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
            @if($isEdit)
                @method('PUT')
            @endif

            <div class="space-y-4">
                <h3 class="text-sm font-bold text-gray-900 border-b pb-2">Data Periode</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
                    <x-input-label for="tahun_ajaran" :value="__('Tahun Ajaran *')" class="md:text-right md:pe-4" />
                    <div class="md:col-span-2">
                        <x-text-input id="tahun_ajaran" name="tahun_ajaran" type="text" class="w-full" :value="old('tahun_ajaran', $periodeData['tahun_ajaran'] ?? '')" placeholder="Contoh: 2025/2026" required x-bind:disabled="!editMode" />
                        <x-input-error class="mt-1" :messages="$errors->get('tahun_ajaran')" />
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 border rounded-lg p-4 bg-gray-50">
                    <div class="space-y-3">
                        <h4 class="font-semibold text-sm text-gray-800 border-b pb-1">Semester 1 (Ganjil)</h4>
                        <div>
                            <x-input-label for="semester_1_tanggal_mulai" :value="__('Tanggal Mulai *')" />
                            <x-text-input id="semester_1_tanggal_mulai" name="semester_1_tanggal_mulai" type="date" class="mt-1 w-full" :value="old('semester_1_tanggal_mulai', $periodeData['semester_1_tanggal_mulai'] ?? '')" required x-bind:disabled="!editMode" />
                            <x-input-error class="mt-1" :messages="$errors->get('semester_1_tanggal_mulai')" />
                        </div>
                        <div>
                            <x-input-label for="semester_1_tanggal_selesai" :value="__('Tanggal Selesai *')" />
                            <x-text-input id="semester_1_tanggal_selesai" name="semester_1_tanggal_selesai" type="date" class="mt-1 w-full" :value="old('semester_1_tanggal_selesai', $periodeData['semester_1_tanggal_selesai'] ?? '')" required x-bind:disabled="!editMode" />
                            <x-input-error class="mt-1" :messages="$errors->get('semester_1_tanggal_selesai')" />
                        </div>
                    </div>

                    <div class="space-y-3">
                        <h4 class="font-semibold text-sm text-gray-800 border-b pb-1">Semester 2 (Genap)</h4>
                        <div>
                            <x-input-label for="semester_2_tanggal_mulai" :value="__('Tanggal Mulai *')" />
                            <x-text-input id="semester_2_tanggal_mulai" name="semester_2_tanggal_mulai" type="date" class="mt-1 w-full" :value="old('semester_2_tanggal_mulai', $periodeData['semester_2_tanggal_mulai'] ?? '')" required x-bind:disabled="!editMode" />
                            <x-input-error class="mt-1" :messages="$errors->get('semester_2_tanggal_mulai')" />
                        </div>
                        <div>
                            <x-input-label for="semester_2_tanggal_selesai" :value="__('Tanggal Selesai *')" />
                            <x-text-input id="semester_2_tanggal_selesai" name="semester_2_tanggal_selesai" type="date" class="mt-1 w-full" :value="old('semester_2_tanggal_selesai', $periodeData['semester_2_tanggal_selesai'] ?? '')" required x-bind:disabled="!editMode" />
                            <x-input-error class="mt-1" :messages="$errors->get('semester_2_tanggal_selesai')" />
                        </div>
                    </div>
                </div>
            </div>

            <div class="space-y-2 pt-4 border-t border-gray-200">
                <h3 class="text-sm font-bold text-gray-900 mb-4">Hari Libur (Opsional)</h3>
                
                <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
                    
                    <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                        <div class="flex justify-between items-center mb-3">
                            <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wide">Hari Libur Mingguan</h4>
                            <button type="button" x-show="editMode" @click="addMingguan()" class="px-2.5 py-1 text-xs font-semibold border border-gray-300 rounded hover:bg-gray-50 text-gray-700 transition">
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
                                                <select x-show="editMode" :name="`libur_mingguan[${index}][hari]`" x-model="item.hari" class="w-full text-xs p-1 rounded border-gray-300 focus:ring-indigo-500">
                                                    <option value="Senin">Senin</option>
                                                    <option value="Selasa">Selasa</option>
                                                    <option value="Rabu">Rabu</option>
                                                    <option value="Kamis">Kamis</option>
                                                    <option value="Jumat">Jumat</option>
                                                    <option value="Sabtu">Sabtu</option>
                                                    <option value="Minggu">Minggu</option>
                                                </select>
                                                <span x-show="!editMode" x-text="item.hari"></span>
                                            </td>
                                            <td class="px-2 py-1">
                                                <input x-show="editMode" type="text" :name="`libur_mingguan[${index}][keterangan]`" x-model="item.keterangan" class="w-full text-xs p-1 rounded border-gray-300 focus:ring-indigo-500" required>
                                                <span x-show="!editMode" x-text="item.keterangan"></span>
                                            </td>
                                            <td x-show="editMode" class="px-3 py-2 text-center">
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
                        <p class="text-[10px] text-gray-400 mt-2 italic">Tambahkan hanya jika periode memiliki hari libur mingguan.</p>
                    </div>

                    <div class="bg-white p-4 rounded-lg border border-gray-200 shadow-sm">
                        <div class="flex justify-between items-center mb-3">
                            <h4 class="text-xs font-bold text-gray-800 uppercase tracking-wide">Hari Libur Nasional</h4>
                            <button type="button" x-show="editMode" @click="addNasional()" class="px-2.5 py-1 text-xs font-semibold border border-gray-300 rounded hover:bg-gray-50 text-gray-700 transition">
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
                                                <input x-show="editMode" type="date" :name="`libur_nasional[${index}][tanggal]`" x-model="item.tanggal" class="w-full text-xs p-1 rounded border-gray-300 focus:ring-indigo-500" required>
                                                <span x-show="!editMode" x-text="item.tanggal"></span>
                                            </td>
                                            <td class="px-2 py-1">
                                                <input x-show="editMode" type="text" :name="`libur_nasional[${index}][nama_libur]`" x-model="item.nama_libur" class="w-full text-xs p-1 rounded border-gray-300 focus:ring-indigo-500" required>
                                                <span x-show="!editMode" x-text="item.nama_libur"></span>
                                            </td>
                                            <td class="px-2 py-1">
                                                <input x-show="editMode" type="text" :name="`libur_nasional[${index}][keterangan]`" x-model="item.keterangan" placeholder="Opsional" class="w-full text-xs p-1 rounded border-gray-300 focus:ring-indigo-500">
                                                <span x-show="!editMode" x-text="item.keterangan"></span>
                                            </td>
                                            <td x-show="editMode" class="px-3 py-2 text-center">
                                                <button type="button" @click="removeNasional(index)" class="text-red-600 hover:text-red-900 font-medium">Hapus</button>
                                            </td>
                                        </tr>
                                    </template>
                                    <tr x-show="listNasional.length === 0">
                                        <td colspan="4" class="px-3 py-6 text-center text-gray-400 bg-gray-50/50">Belum ada data hari libur nasional</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                        <p class="text-[10px] text-gray-400 mt-2 italic">Tambahkan hanya jika periode memiliki hari libur nasional.</p>
                    </div>

                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <button type="button" x-show="!editMode" @click="editMode = true" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition">
                    Ubah
                </button>
                <x-secondary-button x-show="editMode" @click="editMode = false">
                    Batal
                </x-secondary-button>
                <x-primary-button class="bg-blue-600 hover:bg-blue-700 text-white" x-show="editMode">
                    {{ __('Simpan') }}
                </x-primary-button>
            </div>
        </form>

        <div class="mt-6 pt-4 border-t border-red-200" x-data="{ showConfirm: false }">
            <form method="POST" action="{{ route('periode.reset') }}" class="flex items-center justify-between">
                @csrf
                <div>
                    <h4 class="text-sm font-bold text-red-700">Reset Periode</h4>
                    <p class="text-xs text-red-500 mt-1">Hapus semua data periode, absensi, dan rekap.</p>
                </div>
                <div class="flex items-center gap-2">
                    <template x-if="!showConfirm">
                        <button type="button" @click="showConfirm = true" class="inline-flex items-center px-3 py-2 bg-red-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-700 transition">
                            Reset Periode
                        </button>
                    </template>
                    <template x-if="showConfirm">
                        <div class="flex items-center gap-2">
                            <span class="text-xs text-red-600">Yakin ingin mereset? Semua data akan terhapus.</span>
                            <button type="submit" class="inline-flex items-center px-3 py-2 bg-red-700 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-red-800 transition">
                                Ya, Reset
                            </button>
                            <button type="button" @click="showConfirm = false" class="inline-flex items-center px-3 py-2 bg-gray-300 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-400 transition">
                                Batal
                            </button>
                        </div>
                    </template>
                </div>
            </form>
        </div>
    </x-form-card>
</x-app-layout>