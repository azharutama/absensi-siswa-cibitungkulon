@props([
    'title',       // Untuk Judul Form
    'backUrl',     // Untuk Link Tombol Kembali
    'maxWidth' => 'max-w-4xl' // Ukuran lebar card (default max-w-4xl, bisa di-override)
])

<div class="py-12">
    <div class="{{ $maxWidth }} mx-auto sm:px-6 lg:px-8">
        <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg border border-gray-200">
            
            <div class="p-6 bg-gray-50/50 border-b border-gray-200 flex justify-between items-center">
                <h2 class="font-semibold text-xl text-gray-800 leading-tight">
                    {{ $title }}
                </h2>
                <a href="{{ $backUrl }}" class="text-sm text-gray-600 hover:text-gray-900 underline flex items-center gap-1">
                    &larr; Kembali
                </a>
            </div>

            <div class="p-6 text-gray-900">
                {{ $slot }}
            </div>

        </div>
    </div>
</div>