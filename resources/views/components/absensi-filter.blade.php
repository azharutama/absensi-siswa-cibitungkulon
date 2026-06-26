@props(['action', 'kelas', 'kelasId' => null, 'tanggal' => null])

<div class="bg-white p-6 rounded-2xl border border-gray-100 shadow-sm mb-6">
    <form method="GET" action="{{ $action }}" class="flex flex-wrap items-end gap-4">
        <div class="w-full sm:w-48">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Pilih Kelas</label>
            <select name="kelas_id" class="w-full bg-gray-50 border border-gray-200 rounded-lg text-sm px-3 py-2 text-gray-700 focus:ring-blue-500 focus:border-blue-500" onchange="this.form.submit()">
                <option value="" selected disabled>Pilih Kelas</option>
                @foreach($kelas as $k)
                    <option value="{{ $k->id }}" {{ $kelasId == $k->id ? 'selected' : '' }}>
                        {{ $k->nama_kelas }}
                    </option>
                @endforeach
            </select>
        </div>

        <div class="w-full sm:w-48">
            <label class="block text-xs font-semibold text-gray-500 mb-1">Tanggal</label>
            <input type="date" name="tanggal" value="{{ $tanggal ?? date('Y-m-d') }}" class="w-full bg-gray-50 border border-gray-200 rounded-lg text-sm px-3 py-2 text-gray-700 focus:ring-blue-500 focus:border-blue-500" onchange="this.form.submit()">
        </div>
    </form>
</div>