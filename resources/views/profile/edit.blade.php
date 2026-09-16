<x-app-layout title="Profil">

    {{-- Halaman profil menggabungkan form informasi akun dan perubahan password. --}}
    <div class="max-w-3xl space-y-6">
        <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm">
            <div class="max-w-xl">
                @include('profile.partials.update-profile-information-form')
            </div>
        </div>

        <div class="p-6 bg-white border border-gray-200 rounded-lg shadow-sm">
            <div class="max-w-xl">
                @include('profile.partials.update-password-form')
            </div>
        </div>

    </div>
</x-app-layout>
