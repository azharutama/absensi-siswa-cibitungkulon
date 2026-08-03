@props(['action', 'kelas', 'kelasId' => null, 'tanggal' => null, 'disabled' => false])

<div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm mb-6">
    <form method="GET" action="{{ $action }}" class="flex flex-wrap items-end gap-4">
        <div class="w-full sm:w-48">
            <label for="absensi-kelas-id" class="block text-xs font-semibold text-gray-500 mb-1">Pilih Kelas</label>
            <select id="absensi-kelas-id" name="kelas_id" {{ $disabled ? 'disabled' : '' }} class="w-full bg-gray-50 border border-gray-200 rounded-lg text-sm px-3 py-2 text-gray-700 focus:ring-blue-500 focus:border-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
                <option value="" @selected(blank($kelasId)) disabled>Pilih Kelas</option>
                @foreach($kelas as $k)
                    <option value="{{ $k->id }}" {{ $kelasId == $k->id ? 'selected' : '' }}>
                        {{ $k->nama_kelas }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="w-full sm:w-48">
            <label for="absensi-tanggal" class="block text-xs font-semibold text-gray-500 mb-1">Tanggal</label>
            <input id="absensi-tanggal" type="date" name="tanggal" value="{{ $tanggal ?? date('Y-m-d') }}" max="{{ today()->toDateString() }}" {{ $disabled ? 'disabled' : '' }} class="w-full bg-gray-50 border border-gray-200 rounded-lg text-sm px-3 py-2 text-gray-700 focus:ring-blue-500 focus:border-blue-500 disabled:opacity-50 disabled:cursor-not-allowed">
        </div>

        <button type="submit" {{ $disabled ? 'disabled' : '' }} class="inline-flex w-full justify-center rounded-lg bg-blue-600 px-5 py-2 text-sm font-semibold text-white transition hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 sm:w-auto disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-blue-600">
            Tampilkan
        </button>
    </form>
</div>
