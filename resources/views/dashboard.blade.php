<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Dashboard') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl x-auto sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-blue-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">
                                {{ Auth::user()->role === 'guru' ? 'Kelas Yang Diajar' : 'Total Semua Kelas' }}
                            </p>
                            <h3 class="text-3xl font-bold text-gray-900 mt-1">{{ $totalKelas }}</h3>
                        </div>
                        <div class="p-3 bg-blue-100 rounded-full text-blue-500">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"></path></svg>
                        </div>
                    </div>
                </div>

                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-green-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">
                                {{ Auth::user()->role === 'guru' ? 'Total Siswa Anda' : 'Total Semua Siswa' }}
                            </p>
                            <h3 class="text-3xl font-bold text-gray-900 mt-1">{{ $totalSiswa }}</h3>
                        </div>
                        <div class="p-3 bg-green-100 rounded-full text-green-500">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                        </div>
                    </div>
                </div>

                @if(Auth::user()->role === 'operator' || Auth::user()->role === 'kepala sekolah')
                <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg p-6 border-l-4 border-purple-500">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-500 uppercase tracking-wider">Total Guru Aktif</p>
                            <h3 class="text-3xl font-bold text-gray-900 mt-1">{{ $totalGuru }}</h3>
                        </div>
                        <div class="p-3 bg-purple-100 rounded-full text-purple-500">
                            <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2M15 11h3m-3 4h2"></path></svg>
                        </div>
                    </div>
                </div>
                @endif

            </div>
        </div>
    </div>
</x-app-layout>