<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-6 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 rounded shadow-sm">
                    {{ session('success') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                
                <div class="flex flex-col md:flex-row justify-between items-center p-6 gap-4 border-b border-gray-200 bg-gray-50/50">
                    <div>
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                            {{ __('Daftar Data Guru') }}
                        </h2>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto justify-end">
                        <form method="GET" action="{{ route('guru.index') }}" class="w-full sm:w-auto flex items-center gap-2">
                            <div class="relative w-full sm:w-64">
                                <input type="text" id="search-input" name="search" value="{{ request('search') }}" placeholder="Cari nama atau no telepon..." class="w-full pl-10 pr-4 py-2 border border-gray-300 rounded-md focus:ring-indigo-500 focus:border-indigo-500 text-sm">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <svg class="h-5 w-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                    </svg>
                                </div>
                            </div>
                            <button type="submit" class="bg-gray-800 hover:bg-gray-700 text-white px-4 py-2 rounded-md text-sm transition font-medium">
                                Cari
                            </button>
                            
                            <a href="{{ route('guru.index') }}" id="btn-reset" class="text-sm text-gray-500 hover:text-gray-700 underline shrink-0 hidden">
                                Reset
                            </a>
                        </form>

                        <a href="{{ route('guru.create') }}" class="w-full sm:w-auto text-center inline-flex justify-center items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition shrink-0">
                            + Tambah Guru
                        </a>
                    </div>
                </div>

                @if($gurus->isNotEmpty())
                    <div class="p-6 text-gray-900 overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 border">
                            <thead class="bg-gray-50">
                                <tr>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">No</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Nama Lengkap</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">No. Telepon</th>
                                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Jenis Kelamin</th>
                                    <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider border-b">Aksi</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white divide-y divide-gray-200">
                                @foreach ($gurus as $index => $guru)
                                    <tr class="hover:bg-gray-50">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 border-b">{{ $index + 1 }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 border-b">{{ $guru->nama }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 border-b">{{ $guru->no_telepon }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 border-b capitalize">{{ $guru->jenis_kelamin }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center border-b font-medium space-x-2">
                                            <a href="{{ route('guru.edit', $guru->id) }}" class="inline-flex items-center text-amber-600 hover:text-amber-900 bg-amber-50 px-3 py-1.5 rounded-md border border-amber-200 transition">Edit</a>
                                            
                                            <form action="{{ route('guru.destroy', $guru->id) }}" method="POST" class="inline-block" onsubmit="return confirm('Apakah Anda yakin ingin menghapus data guru ini?')">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="text-red-600 hover:text-red-900 bg-red-50 px-3 py-1.5 rounded-md border border-red-200 transition">
                                                    Hapus
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="p-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 mb-1">Data Guru Tidak Ditemukan</h3>
                        <p class="text-sm text-gray-500 mb-4">
                            Tidak ada hasil yang cocok untuk kata kunci pencarian <span class="font-semibold text-gray-700">"{{ request('search') }}"</span>.
                        </p>
                        <a href="{{ route('guru.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition">
                            Kembali Lihat Semua Guru
                        </a>
                    </div>
                @endif

            </div> </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const searchInput = document.getElementById('search-input');
            const btnReset = document.getElementById('btn-reset');

            function toggleResetButton() {
                if (searchInput.value.trim().length > 0) {
                    btnReset.classList.remove('hidden');
                } else {
                    btnReset.classList.add('hidden');
                }
            }

            searchInput.addEventListener('input', toggleResetButton);
            toggleResetButton();
        });
    </script>
</x-app-layout>