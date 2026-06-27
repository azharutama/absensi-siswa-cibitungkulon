<x-app-layout>
    <div class="p-6 space-y-6">
        <h2 class="text-xl font-bold text-gray-800">Edit / Perbarui Absensi</h2>
        
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

        <x-absensi-filter :action="route('absensi.edit')" :kelas="$kelas" :kelasId="$kelasId" :tanggal="$tanggal" />

        @if($holidayMessage)
            <div class="p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded shadow-sm">
                {{ $holidayMessage }}
            </div>
        @endif

        @if($kelasId && count($siswas) > 0 && !$holidayMessage)
            <x-absensi-form 
                :action="route('absensi.update')" 
                method="PUT"
                :siswas="$siswas" 
                :absensiSiswa="$absensiSiswa"
                :kelasId="$kelasId" 
                :tanggal="$tanggal" 
                buttonText="Perbarui Data Absensi" 
            />
        @endif
    </div>
</x-app-layout>
