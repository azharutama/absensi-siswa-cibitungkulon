<x-guest-layout>
    <div class="mb-4 text-sm text-gray-600">
        Masukkan nomor WhatsApp yang terdaftar pada akun Anda. Kami akan mengirimkan tautan untuk mengatur ulang kata sandi.
    </div>

    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('password.whatsapp') }}">
        @csrf

        <div>
            <x-input-label for="no_telepon" :value="__('Nomor WhatsApp')" />

            <x-text-input id="no_telepon" class="block mt-1 w-full" type="tel" name="no_telepon" :value="old('no_telepon')" placeholder="Contoh: 081234567890" required autofocus autocomplete="tel" />

            <x-input-error :messages="$errors->get('no_telepon')" class="mt-2" />
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
