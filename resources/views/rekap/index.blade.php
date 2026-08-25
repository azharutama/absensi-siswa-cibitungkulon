<x-app-layout>
    <div class="p-6 space-y-6">
        
        <!-- Header Utama -->
        <div class="flex justify-between items-center pb-2">
            <h2 class="text-xl font-bold text-gray-800">Rekap Absensi</h2>
        </div>

        @if($kelasId)
            <!-- 1. WIDGET CARDS STATISTIK KELAS -->
            <div class="grid grid-cols-1 sm:grid-cols-4 gap-4">
                <!-- Hari Aktif -->
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex flex-col items-center justify-center text-center">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Hari Aktif</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1">{{ $totalHariAktif }}</p>
                    <p class="text-xs text-gray-400 mt-1">hari</p>
                </div>

                <!-- Total Sakit -->
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex flex-col items-center justify-center text-center">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Sakit</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['total_sakit'] }}</p>
                </div>

                <!-- Total Izin -->
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex flex-col items-center justify-center text-center">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Izin</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['total_izin'] }}</p>
                </div>

                <!-- Total Alpa -->
                <div class="bg-white p-5 rounded-xl border border-gray-200 shadow-sm flex flex-col items-center justify-center text-center">
                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wider">Total Alpa</p>
                    <p class="text-3xl font-bold text-gray-900 mt-1">{{ $stats['total_alpa'] }}</p>
                </div>
            </div>
        @endif

        <!-- 2. BLOK FILTER CEPAT DAN MANUAL -->
        <div class="bg-white p-6 rounded-2xl border border-gray-200 shadow-sm space-y-4">
            
            {{-- Pilih Kelas (Untuk operator & kepala sekolah) --}}
            @if(auth()->user()->role !== 'guru')
                <div>
                    <label for="kelas_id_preset" class="block text-sm font-semibold text-gray-700 mb-2">Pilih Kelas</label>
                    <select id="kelas_id_preset" onchange="window.location.href = this.value" class="w-full sm:w-64 bg-gray-50 border border-gray-200 rounded-lg text-sm px-3 py-2 text-gray-700 focus:ring-blue-500 focus:border-blue-500">
                        <option value="{{ route('rekap.index', ['preset' => $preset ?? 'this_month']) }}">-- Pilih Kelas --</option>
                        @foreach($kelas as $k)
                            <option value="{{ route('rekap.index', ['preset' => $preset ?? 'this_month', 'kelas_id' => $k->id]) }}" {{ $kelasId == $k->id ? 'selected' : '' }}>
                                {{ $k->nama_kelas }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endif

            @if($kelasId)
                {{-- Filter Cepat --}}
                <div>
                    <label class="block text-sm font-semibold text-gray-700 mb-3">Filter Periode</label>
                    <div class="flex flex-wrap gap-2">
                        <a href="{{ route('rekap.index', ['preset' => 'today', 'kelas_id' => $kelasId]) }}" 
                           class="px-4 py-2 rounded-lg text-sm font-medium transition {{ ($preset ?? 'this_month') === 'today' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            Hari Ini
                        </a>
                        <a href="{{ route('rekap.index', ['preset' => 'this_week', 'kelas_id' => $kelasId]) }}" 
                           class="px-4 py-2 rounded-lg text-sm font-medium transition {{ ($preset ?? 'this_month') === 'this_week' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            Minggu Ini
                        </a>
                        <a href="{{ route('rekap.index', ['preset' => 'this_month', 'kelas_id' => $kelasId]) }}" 
                           class="px-4 py-2 rounded-lg text-sm font-medium transition {{ ($preset ?? 'this_month') === 'this_month' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            Bulan Ini
                        </a>
                        <a href="{{ route('rekap.index', ['preset' => 'semester_1', 'kelas_id' => $kelasId]) }}" 
                           class="px-4 py-2 rounded-lg text-sm font-medium transition {{ ($preset ?? 'this_month') === 'semester_1' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            Semester 1
                        </a>
                        <a href="{{ route('rekap.index', ['preset' => 'semester_2', 'kelas_id' => $kelasId]) }}" 
                           class="px-4 py-2 rounded-lg text-sm font-medium transition {{ ($preset ?? 'this_month') === 'semester_2' ? 'bg-blue-600 text-white' : 'bg-gray-100 text-gray-700 hover:bg-gray-200' }}">
                            Semester 2
                        </a>
                    </div>
                </div>

                {{-- Filter Manual --}}
                <div x-data="{ showManual: {{ ($preset ?? 'this_month') === 'custom' ? 'true' : 'false' }} }">
                    <button @click="showManual = !showManual" type="button" class="text-sm font-medium text-blue-600 hover:text-blue-700 flex items-center gap-1">
                        <svg class="w-4 h-4 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24" :class="showManual ? 'rotate-180' : ''">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                        </svg>
                        <span x-text="showManual ? 'Sembunyikan Filter Custom' : 'Gunakan Tanggal Custom'"></span>
                    </button>

                    <form method="GET" action="{{ route('rekap.index') }}" class="mt-4 flex flex-wrap items-end gap-4" x-show="showManual" x-cloak>
                        <input type="hidden" name="preset" value="custom">
                        <input type="hidden" name="kelas_id" value="{{ $kelasId }}">
                        
                        <div class="w-full sm:w-48">
                            <label for="tanggal_mulai" class="block text-xs font-semibold text-gray-500 mb-1">Tanggal Mulai</label>
                            <input id="tanggal_mulai" name="tanggal_mulai" type="date" value="{{ $tanggalMulai }}" onchange="this.form.submit()" class="w-full bg-gray-50 border border-gray-200 rounded-lg text-sm px-3 py-2 text-gray-700 focus:ring-blue-500 focus:border-blue-500" />
                        </div>

                        <div class="w-full sm:w-48">
                            <label for="tanggal_berakhir" class="block text-xs font-semibold text-gray-500 mb-1">Tanggal Berakhir</label>
                            <input id="tanggal_berakhir" name="tanggal_berakhir" type="date" value="{{ $tanggalBerakhir }}" onchange="this.form.submit()" class="w-full bg-gray-50 border border-gray-200 rounded-lg text-sm px-3 py-2 text-gray-700 focus:ring-blue-500 focus:border-blue-500" />
                        </div>

                        <div class="flex gap-2 w-full sm:w-auto">
                            <x-secondary-button :href="route('rekap.index', ['kelas_id' => $kelasId])">
                                Reset
                            </x-secondary-button>
                        </div>
                    </form>
                </div>
            @else
                <p class="text-sm text-gray-500 italic">Silakan pilih kelas terlebih dahulu untuk melihat rekap absensi.</p>
            @endif
        </div>

        <!-- 3. TABEL UTAMA DATA REKAP KEHADIRAN -->
        @if($kelasId)
            <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden p-6">
                <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center pb-4 gap-4 border-b border-gray-100 mb-4">
                    <div>
                        <h3 class="font-bold text-gray-800 text-lg">Data Kehadiran Siswa</h3>
                        <p class="text-sm text-gray-500 mt-0.5">
                            {{ $tanggalMulaiDisplay }}
                            s/d
                            {{ $tanggalBerakhirDisplay }}
                        </p>
                    </div>
                    <a href="{{ route('rekap.export', ['kelas_id' => $kelasId, 'preset' => 'custom', 'tanggal_mulai' => $tanggalMulai, 'tanggal_berakhir' => $tanggalBerakhir]) }}" download class="bg-green-600 hover:bg-green-700 text-white text-sm font-medium px-4 py-2 rounded-lg flex items-center gap-2 transition shadow-sm focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                        </svg>
                        Unduh Excel
                    </a>
                </div>

                @if($hideRekapTabel)
                    <div class="border border-dashed border-gray-200 p-12 text-center rounded-xl">
                        <svg class="mx-auto h-10 w-10 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                        <p class="text-sm font-semibold text-gray-600 mb-1">Tidak Ada Data Rekap</p>
                        <p class="text-sm text-gray-400">
                            Tidak ada data absensi pada periode yang dipilih.
                        </p>
                    </div>
                @else
                {{-- Tabel rekap kehadiran siswa --}}
                <div class="overflow-x-auto">
                    <table class="w-full text-center border border-gray-100 text-sm">
                        <thead>
                            <tr class="bg-blue-50/40 text-gray-600 font-semibold border-b border-gray-200">
                                <th class="px-4 py-4 border-r border-gray-200 w-16" rowspan="2">No</th>
                                <th class="px-6 py-4 border-r border-gray-200 text-left" rowspan="2">Nama Siswa</th>
                                <th class="px-4 py-4 border-r border-gray-200 w-24" rowspan="2">Kelas</th>
                                <th class="px-4 py-2 border-b border-gray-200" colspan="4">Status Kehadiran</th>
                                <th class="px-4 py-4 border-l border-gray-200 w-36" rowspan="2">Persentase</th>
                            </tr>
                            <tr class="bg-gray-50/50 text-gray-500 font-medium text-xs border-b border-gray-200">
                                <th class="px-2 py-2 border-r border-gray-200 bg-green-50/30 text-green-700 w-20">Hadir</th>
                                <th class="px-2 py-2 border-r border-gray-200 bg-amber-50/30 text-amber-600 w-20">Sakit</th>
                                <th class="px-2 py-2 border-r border-gray-200 bg-indigo-50/30 text-indigo-600 w-20">Izin</th>
                                <th class="px-2 py-2 bg-red-50/30 text-red-600 w-20">Alpa</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 bg-white text-gray-900">
                            @forelse($rekapSiswa as $index => $data)
                                <tr class="hover:bg-gray-50/50 transition">
                                    <td class="px-4 py-3.5 border-r border-gray-100 font-medium text-gray-900">{{ $index + 1 }}</td>
                                    <td class="px-6 py-3.5 border-r border-gray-100 text-left font-bold text-gray-900">{{ $data['nama_siswa'] }}</td>
                                    <td class="px-4 py-3.5 border-r border-gray-100 text-gray-900">{{ $data['nama_kelas'] }}</td>
                                    <td class="px-2 py-3.5 border-r border-gray-100 font-semibold text-gray-900">{{ $data['hadir'] }}</td>
                                    <td class="px-2 py-3.5 border-r border-gray-100 font-semibold text-gray-900">{{ $data['sakit'] }}</td>
                                    <td class="px-2 py-3.5 border-r border-gray-100 font-semibold text-gray-900">{{ $data['izin'] }}</td>
                                    <td class="px-2 py-3.5 border-r border-gray-100 font-semibold text-gray-900">{{ $data['alpa'] }}</td>
                                    <td class="px-4 py-3.5 font-bold text-gray-900">{{ $data['persentase'] }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="9" class="px-6 py-10 text-center text-gray-400">Tidak ada rekam data siswa pada rentang kelas ini.</td>
                                </tr>
                            @endforelse
                            @if($rekapSiswa && count($rekapSiswa) > 0)
                                <tr class="bg-blue-50 font-bold border-t-2 border-blue-200 text-gray-900">
                                    <td class="px-4 py-3 border-r border-gray-200" colspan="3">TOTAL</td>
                                    <td class="px-2 py-3 border-r border-gray-200">{{ $stats['total_hadir'] ?? 0 }}</td>
                                    <td class="px-2 py-3 border-r border-gray-200">{{ $stats['total_sakit'] ?? 0 }}</td>
                                    <td class="px-2 py-3 border-r border-gray-200">{{ $stats['total_izin'] ?? 0 }}</td>
                                    <td class="px-2 py-3 border-r border-gray-200">{{ $stats['total_alpa'] ?? 0 }}</td>
                                    <td class="px-4 py-3">-</td>
                                </tr>
                                <tr class="bg-gray-50 font-medium text-sm text-gray-900">
                                    <td class="px-4 py-2 border-r border-gray-200" colspan="3">PERSENTASE (%)</td>
                                    <td class="px-2 py-2 border-r border-gray-200">{{ $stats['persentase_hadir'] ?? 0 }}%</td>
                                    <td class="px-2 py-2 border-r border-gray-200">{{ $stats['persentase_sakit'] ?? 0 }}%</td>
                                    <td class="px-2 py-2 border-r border-gray-200">{{ $stats['persentase_izin'] ?? 0 }}%</td>
                                    <td class="px-2 py-2 border-r border-gray-200">{{ $stats['persentase_alpa'] ?? 0 }}%</td>
                                    <td class="px-4 py-2">-</td>
                                </tr>
                            @endif
                        </tbody>
                    </table>
                </div>
                @endif
            </div>
        @else
            <!-- State Kosong / Belum Memilih Filter -->
            <div class="bg-white border border-dashed border-gray-200 p-16 text-center rounded-2xl text-gray-400">
                Silakan pilih kelas terlebih dahulu untuk melihat rekap absensi siswa.
            </div>
        @endif

    </div>
</x-app-layout>
