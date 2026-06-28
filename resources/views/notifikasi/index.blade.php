<x-app-layout title="Riwayat Notifikasi WhatsApp">
    @php
        $kelasOptions = ['1-A', '1-B', '2-A', '2-B', '3-A', '3-B', '4-A', '4-B', '5-A', '5-B', '6-A', '6-B'];

        $notifikasi = [
            [
                'nama_siswa' => 'Ahmad Fauzi',
                'nomor_telepon' => '081234567890',
                'tanggal' => '12 Mei 2006, 08:30',
                'status' => 'Terkirim',
            ],
            [
                'nama_siswa' => 'Budi Santoso',
                'nomor_telepon' => '081234567891',
                'tanggal' => '12 Mei 2006, 08:31',
                'status' => 'Terkirim',
            ],
            [
                'nama_siswa' => 'Citra Lestari',
                'nomor_telepon' => '081234567892',
                'tanggal' => '12 Mei 2006, 08:35',
                'status' => 'Gagal',
            ],
        ];
    @endphp

    <div class="p-6 space-y-6">
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
            <form method="GET" action="{{ route('notifikasi.index') }}" class="flex flex-wrap items-end gap-4">
                <div class="w-full sm:w-36">
                    <label for="kelas" class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Kelas</label>
                    <select id="kelas" name="kelas" class="block w-full rounded-lg border-gray-300 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        @foreach($kelasOptions as $kelas)
                            <option value="{{ $kelas }}" {{ request('kelas', '1-A') === $kelas ? 'selected' : '' }}>{{ $kelas }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="w-full sm:w-64">
                    <label for="tanggal_mulai" class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Tanggal Mulai</label>
                    <input
                        id="tanggal_mulai"
                        name="tanggal_mulai"
                        type="date"
                        value="{{ request('tanggal_mulai', '2006-05-01') }}"
                        class="block w-full rounded-lg border-gray-300 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    >
                </div>

                <div class="hidden sm:flex h-10 items-center text-gray-400">-</div>

                <div class="w-full sm:w-64">
                    <label for="tanggal_berakhir" class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Tanggal Berakhir</label>
                    <input
                        id="tanggal_berakhir"
                        name="tanggal_berakhir"
                        type="date"
                        value="{{ request('tanggal_berakhir', '2006-06-01') }}"
                        class="block w-full rounded-lg border-gray-300 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    >
                </div>

                <div class="flex w-full sm:w-auto gap-2 sm:ml-auto">
                    <button type="submit" class="inline-flex flex-1 sm:flex-none items-center justify-center rounded-lg bg-blue-600 px-6 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2">
                        Filter
                    </button>
                    <a href="{{ route('notifikasi.index') }}" class="inline-flex flex-1 sm:flex-none items-center justify-center rounded-lg border border-gray-300 bg-white px-6 py-2.5 text-sm font-semibold text-gray-600 shadow-sm transition hover:bg-gray-50">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        <div class="bg-white rounded-lg border border-gray-200 shadow-sm overflow-hidden">
            <x-table :headers="['No', 'Nama Siswa', 'Nomor Telepon', 'Tanggal', 'Status Pengiriman']">
                @foreach($notifikasi as $index => $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 border-b">{{ $index + 1 }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 border-b">{{ $item['nama_siswa'] }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 border-b">{{ $item['nomor_telepon'] }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 border-b">{{ $item['tanggal'] }}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm border-b">
                            @if($item['status'] === 'Terkirim')
                                <span class="inline-flex items-center rounded-full bg-green-100 px-3 py-1 text-xs font-semibold text-green-700">
                                    Terkirim
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-red-100 px-3 py-1 text-xs font-semibold text-red-700">
                                    Gagal
                                </span>
                            @endif
                        </td>
                    </tr>
                @endforeach

                @for($i = count($notifikasi); $i < 8; $i++)
                    <tr>
                        <td class="px-6 py-5 border-b border-gray-100" colspan="5">&nbsp;</td>
                    </tr>
                @endfor
            </x-table>

            <div class="flex justify-end border-t border-gray-100 bg-white px-6 py-4">
                <nav class="inline-flex overflow-hidden rounded-lg border border-gray-300 shadow-sm" aria-label="Pagination">
                    <button type="button" class="inline-flex h-9 w-10 items-center justify-center border-r border-gray-300 bg-white text-gray-500 hover:bg-gray-50" aria-label="Halaman sebelumnya">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                        </svg>
                    </button>
                    @foreach([1, 2, 3, 4] as $page)
                        <button type="button" class="inline-flex h-9 w-10 items-center justify-center border-r border-gray-300 text-sm font-medium {{ $page === 1 ? 'bg-blue-600 text-white' : 'bg-white text-gray-600 hover:bg-gray-50' }}">
                            {{ $page }}
                        </button>
                    @endforeach
                    <button type="button" class="inline-flex h-9 w-10 items-center justify-center bg-white text-gray-500 hover:bg-gray-50" aria-label="Halaman berikutnya">
                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </button>
                </nav>
            </div>
        </div>
    </div>
</x-app-layout>
