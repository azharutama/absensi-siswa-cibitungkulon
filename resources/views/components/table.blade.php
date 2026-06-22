@props(['headers' => []])

<div class="p-6 text-gray-900 overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 border">
        <thead class="bg-gray-50">
            <tr>
                @foreach ($headers as $header)
                    <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider border-b">
                        {{ $header }}
                    </th>
                @endforeach
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            {{ $slot }}
        </tbody>
    </table>
</div>