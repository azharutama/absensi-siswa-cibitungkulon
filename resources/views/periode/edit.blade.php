<x-app-layout>
    <x-form-card :title="__('Edit Periode Tahun Ajaran')" :backUrl="route('periode.index')" maxWidth="max-w-5xl">
        @php
            $listMingguan = collect(old(
                'libur_mingguan',
                $periode->hariLiburs
                    ->where('tipe', 'mingguan')
                    ->map(fn ($item) => [
                        'hari' => $item->hari,
                        'keterangan' => $item->keterangan,
                    ])
                    ->values()
                    ->all(),
            ))
                ->map(fn ($item) => [
                    'hari' => is_array($item) ? ($item['hari'] ?? 'Minggu') : 'Minggu',
                    'keterangan' => is_array($item) ? ($item['keterangan'] ?? '') : '',
                ])
                ->values()
                ->all();

            $listNasional = collect(old(
                'libur_nasional',
                $periode->hariLiburs
                    ->where('tipe', 'nasional')
                    ->map(fn ($item) => [
                        'tanggal' => $item->tanggal?->format('Y-m-d') ?? '',
                        'nama_libur' => $item->keterangan,
                    ])
                    ->values()
                    ->all(),
            ))
                ->map(fn ($item) => [
                    'tanggal' => is_array($item) ? ($item['tanggal'] ?? '') : '',
                    'nama_libur' => is_array($item)
                        ? ($item['nama_libur'] ?? $item['keterangan'] ?? '')
                        : '',
                ])
                ->values()
                ->all();
        @endphp
        
        <form method="POST" action="{{ route('periode.update', $periode->id) }}" class="space-y-8" x-data="{ 
            listMingguan: @js($listMingguan),
            listNasional: @js($listNasional),
            addMingguan() {
                this.listMingguan.push({ hari: 'Minggu', keterangan: 'Libur Rutin Mingguan' });
            },
            removeMingguan(index) {
                this.listMingguan.splice(index, 1);
            },
            addNasional() {
                this.listNasional.push({ tanggal: '', nama_libur: '' });
            },
            removeNasional(index) {
                this.listNasional.splice(index, 1);
            }
        }">
            @csrf
            @method('PUT')

            <div class="space-y-4">
                <h3 class="text-sm font-bold text-gray-900 border-b pb-2">Data Periode</h3>
                
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
                    <x-input-label for="tahun_ajaran" :value="__('Tahun Ajaran *')" class="md:text-right md:pe-4" />
                    <div class="md:col-span-2">
                        <x-text-input id="tahun_ajaran" name="tahun_ajaran" type="text" class="w-full" :value="old('tahun_ajaran', $periode->tahun_ajaran)" placeholder="Contoh: 2025/2026" required />
                        <x-input-error class="mt-1" :messages="$errors->get('tahun_ajaran')" />
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 border rounded-lg p-4 bg-gray-50">
                    <div class="space-y-3">
                        <h4 class="font-semibold text-sm text-gray-800 border-b pb-1">Semester 1 (Ganjil)</h4>
                        <div>
                            <x-input-label for="semester_1_tanggal_mulai" :value="__('Tanggal Mulai *')" />
                            <x-text-input id="semester_1_tanggal_mulai" name="semester_1_tanggal_mulai" type="date" class="mt-1 w-full" :value="old('semester_1_tanggal_mulai', $periodeData['semester_1_tanggal_mulai'] ?? $periode->tanggal_mulai?->format('Y-m-d'))" required />
                            <x-input-error class="mt-1" :messages="$errors->get('semester_1_tanggal_mulai')" />
                        </div>
                        <div>
                            <x-input-label for="semester_1_tanggal_selesai" :value="__('Tanggal Selesai *')" />
                            <x-text-input id="semester_1_tanggal_selesai" name="semester_1_tanggal_selesai" type="date" class="mt-1 w-full" :value="old('semester_1_tanggal_selesai', $periodeData['semester_1_tanggal_selesai'] ?? $periode->tanggal_selesai?->format('Y-m-d'))" required />
                            <x-input-error class="mt-1" :messages="$errors->get('semester_1_tanggal_selesai')" />
                        </div>
                    </div>

                    <div class="space-y-3">
                        <h4 class="font-semibold text-sm text-gray-800 border-b pb-1">Semester 2 (Genap)</h4>
                        <div>
                            <x-input-label for="semester_2_tanggal_mulai" :value="__('Tanggal Mulai *')" />
                            <x-text-input id="semester_2_tanggal_mulai" name="semester_2_tanggal_mulai" type="date" class="mt-1 w-full" :value="old('semester_2_tanggal_mulai', $periodeData['semester_2_tanggal_mulai'] ?? null)" required />
                            <x-input-error class="mt-1" :messages="$errors->get('semester_2_tanggal_mulai')" />
                        </div>
                        <div>
                            <x-input-label for="semester_2_tanggal_selesai" :value="__('Tanggal Selesai *')" />
                            <x-text-input id="semester_2_tanggal_selesai" name="semester_2_tanggal_selesai" type="date" class="mt-1 w-full" :value="old('semester_2_tanggal_selesai', $periodeData['semester_2_tanggal_selesai'] ?? null)" required />
                            <x-input-error class="mt-1" :messages="$errors->get('semester_2_tanggal_selesai')" />
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 items-center">
                    <x-input-label :value="__('Status Periode *')" class="md:text-right md:pe-4" />
                    <div class="md:col-span-2 flex flex-col gap-2 pt-1">
                        <label class="inline-flex items-center text-sm text-gray-700 cursor-pointer">
                            <input type="radio" name="status_aktif" value="1" {{ old('status_aktif', $periode->status_aktif) == 1 ? 'checked' : '' }} class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ms-2">Aktif</span>
                        </label>
                        <label class="inline-flex items-center text-sm text-gray-700 cursor-pointer">
                            <input type="radio" name="status_aktif" value="0" {{ old('status_aktif', $periode->status_aktif) == 0 ? 'checked' : '' }} class="border-gray-300 text-indigo-600 focus:ring-indigo-500">
                            <span class="ms-2">Nonaktif</span>
                        </label>
                    </div>
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
                                        <th class="px-3 py-2 text-left">Nama Hari Libur / Keterangan</th>
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
                                                <input type="text" :name="`libur_nasional[${index}][nama_libur]`" x-model="item.nama_libur" class="w-full text-xs p-1 rounded border-gray-300 focus:ring-indigo-500" required>
                                            </td>
                                            <td class="px-3 py-2 text-center">
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
                    </div>

                </div>
            </div>

            <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                <a href="{{ route('periode.index') }}" class="px-4 py-2 border border-gray-300 rounded-md text-xs font-semibold text-gray-700 hover:bg-gray-50 transition">
                    Batal
                </a>
                <x-primary-button class="bg-amber-600 hover:bg-amber-700 text-white">
                    {{ __('Perbarui') }}
                </x-primary-button>
            </div>
        </form>

    </x-form-card>
</x-app-layout>
