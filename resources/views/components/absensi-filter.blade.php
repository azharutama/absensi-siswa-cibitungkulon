@props(['action', 'kelas', 'kelasId' => null, 'tanggal' => null, 'disabled' => false, 'hideKelas' => false])

<div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm mb-6">
    <form method="GET" action="{{ $action }}" class="flex flex-wrap items-end gap-4">
        @if(!$hideKelas)
        <div class="w-full sm:w-48">
            <label for="absensi-kelas-id" class="block text-xs font-semibold text-gray-500 mb-1">Pilih Kelas</label>
            <select id="absensi-kelas-id" name="kelas_id" {{ $disabled ? 'disabled' : '' }} onchange="this.form.submit()" class="w-full bg-gray-50 border border-gray-200 rounded-lg text-sm px-3 py-2 text-gray-700 focus:ring-blue-500 focus:border-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                <option value="" @selected(blank($kelasId)) disabled>Pilih Kelas</option>
                @foreach($kelas as $k)
                    <option value="{{ $k->id }}" {{ $kelasId == $k->id ? 'selected' : '' }}>
                        {{ $k->nama_kelas }}
                    </option>
                @endforeach
            </select>
        </div>
        @endif

        <div class="w-full sm:w-48">
            <label for="absensi-tanggal" class="block text-xs font-semibold text-gray-500 mb-1">Tanggal</label>
            <input id="absensi-tanggal" type="date" name="tanggal" value="{{ $tanggal ?? today()->toDateString() }}" max="{{ today()->toDateString() }}" {{ $disabled ? 'disabled' : '' }} onchange="this.form.submit()" class="w-full bg-gray-50 border border-gray-200 rounded-lg text-sm px-3 py-2 text-gray-700 focus:ring-blue-500 focus:border-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
        </div>
        
        @if($hideKelas && $kelasId)
            <input type="hidden" name="kelas_id" value="{{ $kelasId }}">
        @endif
    </form>
</div>
