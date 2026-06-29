<x-app-layout title="Riwayat Notifikasi WhatsApp">
    <div class="p-6 space-y-6">
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm p-6">
            <form method="GET" action="{{ route('notifikasi.index') }}" class="flex flex-wrap items-end gap-4">
                <div class="w-full sm:w-44">
                    <label for="kelas_id" class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Kelas</label>
                    <select id="kelas_id" name="kelas_id" class="block w-full rounded-lg border-gray-300 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500">
                        <option value="">Semua kelas</option>
                        @foreach($kelas as $item)
                            <option value="{{ $item->id }}" @selected((string) request('kelas_id') === (string) $item->id)>
                                {{ $item->nama_kelas }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div class="w-full sm:w-56">
                    <label for="tanggal_mulai" class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Tanggal Mulai</label>
                    <input
                        id="tanggal_mulai"
                        name="tanggal_mulai"
                        type="date"
                        value="{{ request('tanggal_mulai') }}"
                        class="block w-full rounded-lg border-gray-300 text-sm text-gray-700 shadow-sm focus:border-blue-500 focus:ring-blue-500"
                    >
                </div>

                <div class="hidden sm:flex h-10 items-center text-gray-400">-</div>

                <div class="w-full sm:w-56">
                    <label for="tanggal_berakhir" class="block text-xs font-bold uppercase tracking-wider text-gray-500 mb-2">Tanggal Berakhir</label>
                    <input
                        id="tanggal_berakhir"
                        name="tanggal_berakhir"
                        type="date"
                        value="{{ request('tanggal_berakhir') }}"
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
            <x-table :headers="['No', 'Siswa', 'Kelas', 'Nomor Tujuan', 'Status', 'Waktu']">
                @forelse($notifikasi as $index => $item)
                    <tr class="hover:bg-gray-50">
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 border-b">
                            {{ $notifikasi->firstItem() + $index }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-semibold text-gray-900 border-b">
                            {{ $item->siswa?->nama_siswa ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 border-b">
                            {{ $item->siswa?->kelas?->nama_kelas ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 border-b">
                            {{ $item->parent_phone ?? '-' }}
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm border-b">
                            @php
                                $statusClass = [
                                    'sent' => 'bg-green-100 text-green-700',
                                    'failed' => 'bg-red-100 text-red-700',
                                    'cancelled' => 'bg-gray-100 text-gray-700',
                                    'processing' => 'bg-blue-100 text-blue-700',
                                    'pending' => 'bg-yellow-100 text-yellow-700',
                                ][$item->status] ?? 'bg-gray-100 text-gray-700';

                                $statusLabel = [
                                    'sent' => 'Terkirim',
                                    'failed' => 'Gagal',
                                    'cancelled' => 'Dibatalkan',
                                    'processing' => 'Diproses',
                                    'pending' => 'Menunggu',
                                ][$item->status] ?? ucfirst($item->status);
                            @endphp

                            <span class="inline-flex items-center rounded-full px-3 py-1 text-xs font-semibold {{ $statusClass }}" title="{{ $item->last_error }}">
                                {{ $statusLabel }}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-600 border-b">
                            {{ optional($item->sent_at ?? $item->updated_at)->format('d-m-Y H:i') }}
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="6" class="px-6 py-8 text-center text-sm text-gray-500">
                            Belum ada riwayat notifikasi WhatsApp.
                        </td>
                    </tr>
                @endforelse
            </x-table>

            <div class="border-t border-gray-100 bg-white px-6 py-4">
                {{ $notifikasi->links() }}
            </div>
        </div>
    </div>
</x-app-layout>
