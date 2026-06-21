<x-app-layout>
    @if (auth()->user()->role === 'operator' || auth()->user()->role === 'kepala_sekolah')
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <x-stat-card-operator title="Total Guru" value="16" badge="+2 baru" badge-icon="trend" />
            <x-stat-card-operator title="Total Murid" value="250" badge="Aktif Semester Ini" badge-icon="user" />
            <x-stat-card-operator title="Total Kelas" value="12" badge="Kapasitas Maksimum" badge-icon="building" />
        </div>
    @elseif (auth()->user()->role === 'guru')
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 max-w-3xl">
            <x-stat-card-guru title="Total Murid" value="250" icon="users" />
            <x-stat-card-guru title="Total Kelas" value="1" icon="cap" />
        </div>
    @endif
</x-app-layout>
