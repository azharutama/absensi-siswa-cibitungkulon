<x-app-layout>
    <div class="p-6 max-w-4xl mx-auto space-y-6">
        <div class="text-center py-6">
            <h2 class="text-2xl font-bold text-gray-800">Sistem Pencatatan Absensi</h2>
            <p class="text-sm text-gray-500 mt-1">Silakan pilih menu tindakan absensi siswa di bawah ini</p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <a href="{{ route('absensi.create') }}" class="group bg-white p-8 rounded-2xl border border-gray-200 shadow-sm hover:border-blue-500 hover:shadow-md transition flex flex-col items-center text-center">
                <div class="w-16 h-16 bg-blue-50 rounded-2xl text-blue-600 flex items-center justify-center group-hover:bg-blue-600 group-hover:text-white transition mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                </div>
                <h3 class="text-lg font-bold text-gray-800">Isi Absensi Baru</h3>
                <p class="text-xs text-gray-500 mt-2 max-w-xs">Digunakan untuk mengisi kehadiran harian siswa pertama kali. Terproteksi dari pengisian ganda.</p>
            </a>

            <a href="{{ route('absensi.edit') }}" class="group bg-white p-8 rounded-2xl border border-gray-200 shadow-sm hover:border-amber-500 hover:shadow-md transition flex flex-col items-center text-center">
                <div class="w-16 h-16 bg-amber-50 rounded-2xl text-amber-600 flex items-center justify-center group-hover:bg-amber-500 group-hover:text-white transition mb-4">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 1121.253 8H18"/></svg>
                </div>
                <h3 class="text-lg font-bold text-gray-800">Edit / Perbarui Absensi</h3>
                <p class="text-xs text-gray-500 mt-2 max-w-xs">Ubah data kehadiran jika terjadi kekeliruan pengisian atau ingin memperbarui status absen harian.</p>
            </a>
        </div>
    </div>
</x-app-layout>