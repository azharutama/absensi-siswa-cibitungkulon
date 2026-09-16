@props(['value'])

{{-- Label form reusable dengan class default yang dapat dioverride. --}}
<label {{ $attributes->merge(['class' => 'block font-medium text-sm text-gray-700']) }}>
    {{ $value ?? $slot }}
</label>
