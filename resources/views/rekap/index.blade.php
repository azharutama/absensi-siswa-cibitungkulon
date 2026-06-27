<x-app-layout>
    <div class="p-6 space-y-6">
        
        <!-- Header Utama -->
        <div class="flex justify-between items-center pb-2">
            <h2 class="text-xl font-bold text-gray-800">Pencatatan Absensi</h2>
        </div>

        @if($kelasId)
            <!-- 1. WIDGET CARDS STATISTIK KELAS (Sesuai Desain image_618dc9.png) -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <!-- Rata-rata Hadir -->
                <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex flex-col items-center justify-center text-center">
                    <div class="p-2 bg-blue-50 text-blue-600 rounded-full mb-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Rata-Rata Hadir</p>
                    <p class="text-3xl font-bold text-blue-600 mt-1">{{ $stats['rata_hadir'] }}%</p>
                </div>

                <!-- Total Sakit -->
                <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex flex-col items-center justify-center text-center">
                    <div class="p-2 bg-amber-50 text-amber-500 rounded-full mb-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v3m0 0v3m0-3h3m-3 0H9m12 0a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Sakit</p>
                    <p class="text-3xl font-bold text-gray-800 mt-1">{{ $stats['total_sakit'] }}</p>
                </div>

                <!-- Total Izin -->
                <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex flex-col items-center justify-center text-center">
                    <div class="p-2 bg-indigo-50 text-indigo-500 rounded-full mb-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                    </div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Izin</p>
                    <p class="text-3xl font-bold text-gray-800 mt-1">{{ $stats['total_izin'] }}</p>
                </div>

                <!-- Total Alpa -->
                <div class="bg-white p-5 rounded-2xl border border-gray-200 shadow-sm flex flex-col items-center justify-center text-center">
                    <div class="p-2 bg-red-50 text-red-500 rounded-full mb-2">
                        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                        </svg>
                    </div>
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Alfa</p>
                    <p class="text-3xl font-bold text-red-500 mt-1">{{ $stats['total_alpa'] }}</p>
                </div>
            </div>
        @endif

        <!-- 2. BLOK FILTER RENTANG WAKTU -->
        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm">
            <form method="GET" action="{{ route('rekap.index') }}" class="flex flex-wrap items-end gap-4">
                <div class="w-full sm:w-48">
                    <x-input-label for="kelas_id" :value="__('Kelas')" />
                    <select id="kelas_id" name="kelas_id" class="mt-1 block w-full bg-gray-50 border-gray-200 rounded-lg text-sm px-3 py-2 text-gray-700 focus:ring-blue-500 focus:border-blue-500">
                        <option value="" selected disabled>Pilih Kelas</option>
                        @foreach($kelas as $k)
                            <option value="{{ $k->id }}" {{ $kelasId == $k->id ? 'selected' : '' }}>{{ $k->nama_kelas }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-full sm:w-48">
                    <x-input-label for="tanggal_mulai" :value="__('Tanggal Mulai')" />
                    <x-text-input id="tanggal_mulai" name="tanggal_mulai" type="date" class="mt-1 block w-full bg-gray-50" :value="$tanggalMulai" />
                </div>

                <div class="w-full sm:w-48">
                    <x-input-label for="tanggal_berakhir" :value="__('Tanggal Berakhir')" />
                    <x-text-input id="tanggal_berakhir" name="tanggal_berakhir" type="date" class="mt-1 block w-full bg-gray-50" :value="$tanggalBerakhir" />
                </div>

                <div class="flex gap-2 w-full sm:w-auto">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-5 py-2.5 rounded-lg flex items-center gap-2 transition shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/>
                        </svg>
                        Filter
                    </button>
                    <a href="{{ route('rekap.index') }}" class="bg-gray-100 text-gray-600 hover:bg-gray-200 text-sm font-medium px-5 py-2.5 rounded-lg transition text-center">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <!-- 3. TABEL UTAMA DATA REKAP KEHADIRAN -->
        @if($kelasId)
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden p-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-4 gap-4 border-b border-gray-100 mb-4">
                    <h3 class="font-bold text-gray-800 text-lg">Data Kehadiran Siswa</h3>
                    <button type="button" onclick="window.print()" class="bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium px-4 py-2 rounded-lg flex items-center gap-2 transition shadow-sm">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Export ke Excel
                    </button>
                </div>

                {{-- Tabel rekap kehadiran siswa --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-center border border-gray-100 text-sm">
                        <thead>
                            <tr class="bg-blue-50/40 text-gray-600 font-semibold border-b border-gray-200">
                                <th class="px-4 py-4 border-r border-gray-200 w-16" rowspan="2">No</th>
                                <th class="px-6 py-4 border-r border-gray-200 text-left" rowspan="2">Nama Siswa</th>
                                <th class="px-4 py-4 border-r border-gray-200 w-24" rowspan="2">Kelas</th>
                                <th class="px-4 py-2 border-b border-gray-200" colspan="4">Status Kehadiran</th>
                                <th class="px-4 py-4 border-l border-gray-200 w-32" rowspan="2">Persentase</th>
                            </tr>
                            <tr class="bg-gray-50/50 text-gray-500 font-medium text-xs border-b border-gray-200">
                                <th class="px-2 py-2 border-r border-gray-200 bg-green-50/30 text-green-700 w-20">Hadir</th>
                                <th class="px-2 py-2 border-r border-gray-200 bg-amber-50/30 text-amber-600 w-20">Sakit</th>
                                <th class="px-2 py-2 border-r border-gray-200 bg-indigo-50/30 text-indigo-600 w-20">Izin</th>
                                <th class="px-2 py-2 bg-red-50/30 text-red-600 w-20">Alfa</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white text-gray-700">
                            @forelse($rekapSiswa as $index => $data)
                                <tr class="hover:bg-gray-50/50 transition">
                                    <td class="px-4 py-3.5 border-r border-gray-100 font-medium text-gray-400">{{ $index + 1 }}</td>
                                    <td class="px-6 py-3.5 border-r border-gray-100 text-left font-bold text-gray-800">{{ $data['nama_siswa'] }}</td>
                                    <td class="px-4 py-3.5 border-r border-gray-100">{{ $data['nama_kelas'] }}</td>
                                    <td class="px-2 py-3.5 border-r border-gray-100 text-green-700 font-semibold">{{ $data['hadir'] }}</td>
                                    <td class="px-2 py-3.5 border-r border-gray-100 text-amber-600 font-semibold">{{ $data['sakit'] }}</td>
                                    <td class="px-2 py-3.5 border-r border-gray-100 text-indigo-600 font-semibold">{{ $data['izin'] }}</td>
                                    <td class="px-2 py-3.5 border-r border-gray-100 text-red-600 font-semibold">{{ $data['alpa'] }}</td>
                                    <td class="px-4 py-3.5 font-bold text-blue-600">{{ $data['persentase'] }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="8" class="px-6 py-10 text-center text-gray-400">Tidak ada rekam data siswa pada rentang kelas ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @else
            <!-- State Kosong / Belum Memilih Parameter -->
            <div class="bg-white border border-dashed border-gray-200 p-16 text-center rounded-2xl text-gray-400">
                Silakan tentukan parameter filter kelas terlebih dahulu untuk memuat rangkuman rekap absensi harian siswa.
            </div>
        @endif

    </div>
</x-app-layout>
