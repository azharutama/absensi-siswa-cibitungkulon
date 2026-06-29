<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-6 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 rounded shadow-sm">
                    {{ session('success') }}
                </div>
            @endif

            @if (session('warning'))
                <div class="mb-6 p-4 bg-yellow-100 border-l-4 border-yellow-500 text-yellow-800 rounded shadow-sm">
                    <p class="font-semibold">{{ session('warning') }}</p>

                    @if (session('import_errors'))
                        <ul class="mt-2 list-disc list-inside text-sm space-y-1">
                            @foreach (array_slice(session('import_errors'), 0, 10) as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>

                        @if (count(session('import_errors')) > 10)
                            <p class="mt-2 text-sm">Dan {{ count(session('import_errors')) - 10 }} error lainnya.</p>
                        @endif
                    @endif
                </div>
            @endif

            @if (session('error'))
                <div class="mb-6 p-4 bg-red-100 border-l-4 border-red-500 text-red-700 rounded shadow-sm">
                    {{ session('error') }}
                </div>
            @endif

            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
                
                <div class="flex flex-col md:flex-row justify-between items-center p-6 gap-4 border-b border-gray-200 bg-gray-50/50">
                    <div>
                        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                            {{ __('Daftar Data Siswa') }}
                        </h2>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto justify-end">
                        <x-search :action="route('siswa.index')" 
                                  placeholder="Cari NISN atau nama siswa..." 
                                  :value="request('search')" />

                        <a href="{{ route('siswa.import.form') }}" class="w-full sm:w-auto text-center inline-flex justify-center items-center px-4 py-2 bg-emerald-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-emerald-700 transition shrink-0">
                            Import Excel
                        </a>

                        <a href="{{ route('siswa.create') }}" class="w-full sm:w-auto text-center inline-flex justify-center items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition shrink-0">
                            + Tambah Siswa
                        </a>
                    </div>
                </div>

                @if($siswas->isNotEmpty())
                    <x-table :headers="['No', 'NISN', 'Nama Lengkap', 'Kelas', 'Jenis Kelamin', 'Aksi']">
                        @foreach ($siswas as $index => $siswa)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 border-b">{{ $index + 1 }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 border-b font-mono">{{ $siswa->nisn }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 border-b">{{ $siswa->nama_siswa }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 border-b">{{ $siswa->kelas->nama_kelas ?? '-' }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 border-b capitalize">{{ $siswa->jenis_kelamin }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-center border-b font-medium space-x-2">
                                    <a href="{{ route('siswa.edit', $siswa->id) }}" class="inline-flex items-center text-amber-600 hover:text-amber-900 bg-amber-50 px-3 py-1.5 rounded-md border border-amber-200 transition">Edit</a>
                                    
                                    <button type="button" onclick="openDeleteModal(@js(route('siswa.destroy', $siswa->id)))" class="text-red-600 hover:text-red-900 bg-red-50 px-3 py-1.5 rounded-md border border-red-200 transition">
                                        Hapus
                                    </button>
                                </td>
                            </tr>
                        @endforeach
                    </x-table>
                @else
                    <div class="p-12 text-center">
                        <svg class="mx-auto h-12 w-12 text-gray-400 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.172 16.172a4 4 0 015.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="text-lg font-medium text-gray-900 mb-1">Data Siswa Tidak Ditemukan</h3>
                        <p class="text-sm text-gray-500 mb-4">
                            Tidak ada hasil yang cocok untuk kata kunci pencarian <span class="font-semibold text-gray-700">"{{ request('search') }}"</span>.
                        </p>
                        <a href="{{ route('siswa.index') }}" class="inline-flex items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 transition">
                            Kembali Lihat Semua Siswa
                        </a>
                    </div>
                @endif

            </div> 
        </div>
    </div>

    <x-confirm-modal />
</x-app-layout>
