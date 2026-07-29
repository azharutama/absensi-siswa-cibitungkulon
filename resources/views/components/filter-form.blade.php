@props([
    'action' => '',
    'filters' => [],
    'preserveSearch' => true,
])

<form method="GET" action="{{ $action }}" class="grid grid-cols-1 gap-3 border-t border-gray-200 bg-white p-4 sm:grid-cols-2 lg:grid-cols-{{ count($filters) + 1 }}">
    {{-- Preserve search if exists --}}
    @if($preserveSearch && request('search'))
        <input type="hidden" name="search" value="{{ request('search') }}">
    @endif

    {{-- Dynamic Filter Fields --}}
    @foreach($filters as $filter)
        <div>
            <label for="filter-{{ $filter['name'] }}" class="mb-1 block text-xs font-semibold text-gray-600">
                {{ $filter['label'] }}
            </label>
            <select 
                id="filter-{{ $filter['name'] }}" 
                name="{{ $filter['name'] }}" 
                class="block w-full rounded-md border-gray-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                <option value="">{{ $filter['placeholder'] ?? 'Semua' }}</option>
                @foreach($filter['options'] as $value => $label)
                    <option value="{{ $value }}" @selected((string) request($filter['name']) === (string) $value)>
                        {{ $label }}
                    </option>
                @endforeach
            </select>
        </div>
    @endforeach

    {{-- Action Buttons --}}
    <div class="flex items-end gap-2">
        <x-primary-button type="submit" class="flex-1">
            Terapkan Filter
        </x-primary-button>
        <x-secondary-button :href="$action">
            Reset
        </x-secondary-button>
    </div>
</form>
