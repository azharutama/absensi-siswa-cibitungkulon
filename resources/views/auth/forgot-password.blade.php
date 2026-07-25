<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        Masukkan alamat email akun Anda. Kami akan mengirimkan tautan untuk mengatur ulang kata sandi.
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.email') }}">
        @csrf

        <div>
            <x-input-label for="email" :value="__('Email')" />

            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />

            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="flex items-center justify-between mt-4">
            <a href="{{ route('login') }}" class="text-sm text-gray-600 hover:text-gray-900 hover:underline">
                Kembali ke masuk
            </a>

            <x-primary-button>
                Kirim Tautan
            </x-primary-button>
        </div>
    </form>
</x-guest-layout>
