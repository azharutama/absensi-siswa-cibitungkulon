<x-app-layout>
    <div class="p-6 space-y-6">
        <h2 class="text-xl font-bold text-gray-800">Edit Absensi</h2>
        
        @if (session('success'))
            <div class="p-4 bg-green-100 border-l-4 border-green-500 text-green-700 rounded shadow-sm">
                {{ session('success') }}
            </div>
        @endif

        @if (session('error'))
            <div class="p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded shadow-sm">
                {{ session('error') }}
            </div>
        @endif

        @if($periodeWarning)
            <div class="p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded shadow-sm flex items-start gap-3">
                <svg class="w-5 h-5 mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div>
                    <p class="font-semibold">Periode Belum Dikonfigurasi</p>
                    <p class="mt-1">{{ $periodeWarning }}</p>
                </div>
            </div>
        @endif

        @if(auth()->user()->role === 'guru')
            <x-absensi-filter :action="route('absensi.edit')" :kelas="$kelas" :kelasId="$kelasId" :tanggal="$tanggal" :disabled="$periodeWarning !== null" :hideKelas="true" />
        @else
            <x-absensi-filter :action="route('absensi.edit')" :kelas="$kelas" :kelasId="$kelasId" :tanggal="$tanggal" :disabled="$periodeWarning !== null" />
        @endif

        @if($holidayMessage && !$periodeWarning)
            <div class="p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded shadow-sm">
                {{ $holidayMessage }}
            </div>
        @endif

        @if($kelasId && count($siswas) > 0 && !$holidayMessage && !$periodeWarning)
            <x-absensi-form 
                :action="route('absensi.update')" 
                method="PUT"
                :siswas="$siswas" 
                :absensiSiswa="$absensiSiswa"
                :kelas="$kelas"
                :kelasId="$kelasId" 
                :tanggal="$tanggal" 
                buttonText="Simpan Perubahan" 
            />
        @elseif($kelasId && count($siswas) === 0 && !$holidayMessage && !$periodeWarning)
            <div class="rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                Kelas ini belum memiliki siswa aktif yang dapat diperbarui absensinya.
            </div>
        @endif
    </div>
</x-app-layout>
