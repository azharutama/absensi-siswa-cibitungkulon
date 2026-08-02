@props([
    'action', 
    'method' => 'POST', 
    'siswas', 
    'absensiSiswa' => [], 
    'kelas',
    'kelasId', 
    'tanggal', 
    'buttonText' => 'Simpan', 
    'isLocked' => false
])

@php
    $initialStatuses = [];
    foreach ($siswas as $siswa) {
        $initialStatuses[$siswa->id] = strtolower($absensiSiswa[$siswa->id] ?? 'hadir');
    }
@endphp

<div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
    
    @if($isLocked)
        <div class="p-4 bg-amber-50 border-b border-amber-200 flex items-start gap-3">
            <div class="p-1 bg-amber-500 text-white rounded-lg mt-0.5">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
            </div>
            <div>
                <h4 class="text-sm font-bold text-amber-800">Absensi Sudah Terisi!</h4>
                <p class="text-xs text-amber-600 mt-0.5">
                    Kelas ini sudah melakukan pengisian absensi pada tanggal {{ date('d-m-Y', strtotime($tanggal)) }}. 
                    Untuk melakukan perubahan atau perbaikan data kehadiran, silakan gunakan menu <a href="{{ route('absensi.edit', ['kelas_id' => $kelasId, 'tanggal' => $tanggal]) }}" class="font-bold underline hover:text-amber-900">Edit Absensi</a>.
                </p>
            </div>
        </div>
    @endif

    <form 
        method="POST" 
        action="{{ $action }}" 
        @if(!$isLocked)
            data-confirm-message="Apakah Anda yakin semua data kehadiran sudah benar dan ingin melakukan {{ strtoupper($method) === 'PUT' ? 'perubahan/perbarui' : 'penyimpanan' }} data absensi ini?"
            data-confirm-title="Konfirmasi Simpan"
            data-confirm-text="Simpan"
            data-confirm-color="green"
        @endif
    >
        @csrf
        @if(strtoupper($method) !== 'POST')
            @method($method)
        @endif
        
        <input type="hidden" name="kelas_id" value="{{ $kelasId }}">
        <input type="hidden" name="tanggal" value="{{ $tanggal }}">

        <div class="px-4 py-3 bg-gray-50 border-b border-gray-100 flex items-center justify-between">
            <div>
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Kelas</p>
                <p class="text-lg font-bold text-gray-800">{{ $kelas->where('id', $kelasId)->first()?->nama_kelas ?? '-' }}</p>
            </div>
            <div class="text-right">
                <p class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Tanggal</p>
                <p class="text-lg font-bold text-gray-800">{{ date('d M Y', strtotime($tanggal)) }}</p>
            </div>
        </div>

        <div x-data="{
            statuses: @js($initialStatuses),
            get counts() {
                let c = { hadir: 0, izin: 0, sakit: 0, alpa: 0 };
                Object.values(this.statuses).forEach(s => { if (c[s] !== undefined) c[s]++; });
                return c;
            },
            setStatus(id, status) {
                this.statuses[id] = status;
            }
        }">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead>
                        <tr class="bg-slate-50 text-gray-500 font-semibold border-b border-gray-100">
<th class="ps-6 pe-3 py-2 w-10 text-base">No</th>
                        <th class="ps-6 pe-3 py-2 text-base">Nama Siswa</th>
                        <th class="px-3 py-2 text-center w-48 text-base">Status Kehadiran</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 bg-white">
                        @foreach($siswas as $index => $siswa)
                            @php 
                                $currentStatus = $initialStatuses[$siswa->id];
                            @endphp
                            <tr class="hover:bg-gray-50/80 transition-colors" x-data="{ statusSiswa: '{{ $currentStatus }}' }">
                                <td class="ps-6 pe-3 py-2.5 text-gray-400 font-medium text-base">{{ $index + 1 }}</td>
                                <td class="ps-6 pe-3 py-2.5">
                                    <p class="font-bold text-gray-800 text-base uppercase">{{ $siswa->nama_siswa }}</p>
                                    <p class="text-sm text-gray-400">{{ $siswa->nisn ?? '-' }}</p>
                                </td>
                                <td class="px-3 py-2.5">
                                    @if($isLocked)
                                        <div class="flex justify-center">
                                            @if($currentStatus == 'hadir')
                                                <span class="px-3 py-1 bg-green-50 text-green-700 border border-green-200 text-sm font-bold rounded-full">Hadir</span>
                                            @elseif($currentStatus == 'izin')
                                                <span class="px-3 py-1 bg-blue-50 text-blue-700 border border-blue-200 text-sm font-bold rounded-full">Izin</span>
                                            @elseif($currentStatus == 'sakit')
                                                <span class="px-3 py-1 bg-amber-50 text-amber-700 border border-amber-200 text-sm font-bold rounded-full">Sakit</span>
                                            @else
                                                <span class="px-3 py-1 bg-red-50 text-red-700 border border-red-200 text-sm font-bold rounded-full">Alpa</span>
                                            @endif
                                        </div>
                                    @else
                                        <div class="flex justify-center items-center gap-3">
                                            
                                            <label class="cursor-pointer relative flex items-center justify-center">
                                                <input type="radio" name="absensi[{{ $siswa->id }}]" value="hadir" x-model="statusSiswa" @change="setStatus({{ $siswa->id }}, 'hadir')" aria-label="Hadir untuk {{ $siswa->nama_siswa }}" class="sr-only peer">
                                                <span class="w-8 h-8 rounded-full border flex items-center justify-center text-sm font-bold transition peer-focus-visible:ring-2 peer-focus-visible:ring-blue-500 peer-focus-visible:ring-offset-2"
                                                      :class="statusSiswa == 'hadir' ? 'bg-green-600 border-green-600 text-white' : 'bg-white border-gray-300 text-gray-500'">
                                                    H
                                                </span>
                                            </label>

                                            <label class="cursor-pointer relative flex items-center justify-center">
                                                <input type="radio" name="absensi[{{ $siswa->id }}]" value="izin" x-model="statusSiswa" @change="setStatus({{ $siswa->id }}, 'izin')" aria-label="Izin untuk {{ $siswa->nama_siswa }}" class="sr-only peer">
                                                <span class="w-8 h-8 rounded-full border flex items-center justify-center text-sm font-bold transition peer-focus-visible:ring-2 peer-focus-visible:ring-blue-500 peer-focus-visible:ring-offset-2"
                                                      :class="statusSiswa == 'izin' ? 'bg-blue-500 border-blue-500 text-white' : 'bg-white border-gray-300 text-gray-500'">
                                                    I
                                                </span>
                                            </label>

                                            <label class="cursor-pointer relative flex items-center justify-center">
                                                <input type="radio" name="absensi[{{ $siswa->id }}]" value="sakit" x-model="statusSiswa" @change="setStatus({{ $siswa->id }}, 'sakit')" aria-label="Sakit untuk {{ $siswa->nama_siswa }}" class="sr-only peer">
                                                <span class="w-8 h-8 rounded-full border flex items-center justify-center text-sm font-bold transition peer-focus-visible:ring-2 peer-focus-visible:ring-blue-500 peer-focus-visible:ring-offset-2"
                                                      :class="statusSiswa == 'sakit' ? 'bg-amber-500 border-amber-500 text-white' : 'bg-white border-gray-300 text-gray-500'">
                                                    S
                                                </span>
                                            </label>

                                            <label class="cursor-pointer relative flex items-center justify-center">
                                                <input type="radio" name="absensi[{{ $siswa->id }}]" value="alpa" x-model="statusSiswa" @change="setStatus({{ $siswa->id }}, 'alpa')" aria-label="Alpa untuk {{ $siswa->nama_siswa }}" class="sr-only peer">
                                                <span class="w-8 h-8 rounded-full border flex items-center justify-center text-sm font-bold transition peer-focus-visible:ring-2 peer-focus-visible:ring-blue-500 peer-focus-visible:ring-offset-2"
                                                      :class="statusSiswa == 'alpa' ? 'bg-red-500 border-red-500 text-white' : 'bg-white border-gray-300 text-gray-500'">
                                                    A
                                                </span>
                                            </label>

                                        </div>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if(!$isLocked)
                <div class="px-4 py-3 bg-gray-50 border-t border-gray-100">
                    <div class="flex flex-wrap items-center gap-x-5 gap-y-2 text-sm">
                        <span class="flex items-center gap-1.5">
                            <span class="w-3.5 h-3.5 rounded-full bg-green-600 inline-block"></span>
                            <span class="text-gray-600">Hadir:</span>
                            <span class="font-bold text-gray-800" x-text="counts.hadir">0</span>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-3.5 h-3.5 rounded-full bg-blue-500 inline-block"></span>
                            <span class="text-gray-600">Izin:</span>
                            <span class="font-bold text-gray-800" x-text="counts.izin">0</span>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-3.5 h-3.5 rounded-full bg-amber-500 inline-block"></span>
                            <span class="text-gray-600">Sakit:</span>
                            <span class="font-bold text-gray-800" x-text="counts.sakit">0</span>
                        </span>
                        <span class="flex items-center gap-1.5">
                            <span class="w-3.5 h-3.5 rounded-full bg-red-500 inline-block"></span>
                            <span class="text-gray-600">Alpa:</span>
                            <span class="font-bold text-gray-800" x-text="counts.alpa">0</span>
                        </span>
                    </div>
                </div>
            @endif

            <div class="px-4 py-3 bg-white border-t border-gray-100 flex justify-end">
                @if($isLocked)
                    <div class="w-full flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <p class="text-sm text-gray-600">
                            Form isi baru dikunci karena absensi kelas ini sudah tersimpan untuk tanggal tersebut.
                        </p>
                        <a href="{{ route('absensi.edit', ['kelas_id' => $kelasId, 'tanggal' => $tanggal]) }}" class="inline-flex justify-center bg-blue-600 hover:bg-blue-700 text-white font-semibold text-sm px-6 py-2.5 rounded-lg transition shadow-sm focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                            Buka Edit Absensi
                        </a>
                    </div>
                @else
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold text-base px-10 py-3 rounded-xl transition shadow-md">
                        {{ $buttonText }}
                    </button>
                @endif
            </div>
        </div>
    </form>
</div>