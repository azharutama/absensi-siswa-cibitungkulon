<x-app-layout>
    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-6 p-4 bg-green-100 border-l-4 border-green-500 text-green-700 rounded shadow-sm">
                    {{ session('success') }}
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
                            {{ __('Daftar Periode Akademik') }}
                        </h2>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row items-center gap-3 w-full md:w-auto justify-end">
                        <x-search :action="route('periode.index')" placeholder="Cari periode..." :value="request('search')" />

                        <a href="{{ route('periode.create') }}" class="w-full sm:w-auto text-center inline-flex justify-center items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-700 transition shrink-0">
                            + Tambah Periode
                        </a>
                    </div>
                </div>

                @if($periodes->isNotEmpty())
                    <x-table :headers="['No', 'Nama Periode', 'Tanggal Mulai', 'Tanggal Selesai', 'Status', 'Aksi']">
                        @foreach ($periodes as $index => $p)
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 border-b w-16">{{ $index + 1 }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 border-b">{{ $p->nama_periode }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 border-b">{{ \Carbon\Carbon::parse($p->tanggal_mulai)->format('d M Y') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 border-b">{{ \Carbon\Carbon::parse($p->tanggal_selesai)->format('d M Y') }}</td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm border-b">
                                    @if($p->status_aktif)
                                        <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-green-100 text-green-800 uppercase">Aktif</span>
                                    @else
                                        <span class="px-2.5 py-1 text-xs font-semibold rounded-full bg-gray-100 text-gray-600 uppercase">Tidak Aktif</span>
                                    @endif
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm text-center border-b font-medium space-x-2 w-48">
                                    <a href="{{ route('periode.edit', $p->id) }}" class="inline-flex items-center text-amber-600 hover:text-amber-900 bg-amber-50 px-3 py-1.5 rounded-md border border-amber-200 transition">Edit</a>
                                    
                                    <button type="button" onclick="openDeleteModal(@js(route('periode.destroy', $p->id)))" class="text-red-600 hover:text-red-900 bg-red-50 px-3 py-1.5 rounded-md border border-red-200 transition">Hapus</button>
                                </td>
                            </tr>
                        @endforeach
                    </x-table>
                @else
                    <div class="p-12 text-center text-gray-500">Data Periode Akademik kosong.</div>
                @endif

            </div> 
        </div>
    </div>

    <x-confirm-modal />
</x-app-layout>
