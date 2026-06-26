<x-app-layout>
    <div class="p-6 space-y-6">
        <h2 class="text-xl font-bold text-gray-800">Isi Absensi Baru</h2>

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

        <x-absensi-filter :action="route('absensi.create')" :kelas="$kelas" :kelasId="$kelasId" :tanggal="$tanggal" />

        @if($kelasId && count($siswas) > 0)
            <x-absensi-form 
                :action="route('absensi.store')" 
                method="POST"
                :siswas="$siswas" 
                :absensiSiswa="$absensiSiswa"
                :kelasId="$kelasId" 
                :tanggal="$tanggal" 
                :isLocked="$isLocked"
                buttonText="Simpan" 
            />
        @endif
    </div>
</x-app-layout>
