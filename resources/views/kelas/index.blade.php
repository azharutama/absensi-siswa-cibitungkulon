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
                            {{ __('Daftar Data Kelas') }}
                        </h2>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto justify-end">
                        <x-search :action="route('kelas.index')" placeholder="Cari nama kelas..." :value="request('search')" />
                        <a href="{{ route('kelas.create') }}" class="w-full sm:w-auto text-center inline-flex justify-center items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition shrink-0">
                            + Tambah Kelas
                        </a>
                    </div>
                </div>

                @if($kelas->isNotEmpty())
                    <x-table :headers="['No', 'Nama Kelas', 'Aksi']">
                        @foreach ($kelas as $index => $k)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 border-b w-20">
                                    {{ $index + 1 }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 border-b">
                                    {{ $k->nama_kelas }}
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-center border-b font-medium space-x-2 w-48">
                                    <a href="{{ route('kelas.edit', $k->id) }}" class="inline-flex items-center text-amber-600 hover:text-amber-900 bg-amber-50 px-3 py-1.5 rounded-md border border-amber-200 transition">Edit</a>
                                    <button type="button" onclick="openDeleteModal(@js(route('kelas.destroy', $k->id)))" class="text-red-600 hover:text-red-900 bg-red-50 px-3 py-1.5 rounded-md border border-red-200 transition">Hapus</button>
                                </td>
                            </tr>
                        @endforeach
                    </x-table>
                @else
                    <div class="p-12 text-center">
                        <h3 class="text-lg font-medium text-gray-900 mb-1">Data Kelas Tidak Ditemukan</h3>
                    </div>
                @endif

            </div> 
        </div>
    </div>

    <x-confirm-modal />
</x-app-layout>
