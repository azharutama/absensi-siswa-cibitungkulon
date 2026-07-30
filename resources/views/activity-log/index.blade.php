<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            Log Aktivitas
        </h2>
    </x-slot>

    <div class="py-6">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-4">
            
            {{-- Filter --}}
            <x-filter-form 
                :action="route('activity-logs.index')"
                :filters="[
                    [
                        'name' => 'model_type',
                        'label' => 'Jenis Data',
                        'placeholder' => 'Semua',
                        'options' => [
                            'Siswa' => 'Siswa',
                            'Guru' => 'Pengguna',
                            'Kelas' => 'Kelas',
                            'Periode' => 'Periode'
                        ]
                    ],
                    [
                        'name' => 'activity_type',
                        'label' => 'Jenis Aktivitas',
                        'placeholder' => 'Semua',
                        'options' => [
                            'create' => 'Tambah',
                            'update' => 'Ubah',
                            'delete' => 'Hapus'
                        ]
                    ]
                ]"
            />

            {{-- Table --}}
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <x-table :headers="['Waktu', 'Pengguna', 'Aktivitas', 'Deskripsi']" :rows="$logs">
                    @foreach ($logs as $log)
                        <tr class="hover:bg-gray-50">
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                {{ $log->created_at->format('d M Y, H:i') }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900">
                                {{ $log->user->name ?? 'Unknown' }}
                            </td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                <span class="px-2 py-1 rounded text-xs font-semibold
                                    {{ $log->activity_type === 'create' ? 'bg-green-100 text-green-700' : '' }}
                                    {{ $log->activity_type === 'update' ? 'bg-blue-100 text-blue-700' : '' }}
                                    {{ $log->activity_type === 'delete' ? 'bg-red-100 text-red-700' : '' }}">
                                    {{ ['create' => 'Tambah', 'update' => 'Ubah', 'delete' => 'Hapus'][$log->activity_type] ?? $log->activity_type }}
                                </span>
                                <span class="ml-2 text-gray-600">{{ $log->model_type }}</span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-700">
                                {{ $log->description }}
                            </td>
                        </tr>
                    @endforeach
                </x-table>
            </div>

            {{-- Pagination --}}
            @if($logs->hasPages())
                <div class="mt-4">
                    {{ $logs->links() }}
                </div>
            @endif
        </div>
    </div>
</x-app-layout>
