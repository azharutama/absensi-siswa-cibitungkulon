<x-app-layout>
    <div class="p-6 space-y-6">
        <h2 class="text-xl font-bold text-gray-800">Edit / Perbarui Absensi</h2>

        <x-absensi-filter :action="route('absensi.edit')" :kelas="$kelas" :kelasId="$kelasId" :tanggal="$tanggal" />

        @if($kelasId && count($siswas) > 0)
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