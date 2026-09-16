@props(['status'])

{{-- Tampilkan pesan status session hanya ketika nilainya tersedia. --}}
@if ($status)
    <div {{ $attributes->merge(['class' => 'font-medium text-sm text-green-600']) }}>
        {{ $status }}
    </div>
@endif
