<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\FonnteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Throwable;

class PasswordResetLinkController extends Controller
{
    private const RESET_LINK_SENT_MESSAGE = 'Jika nomor WhatsApp terdaftar, tautan pengaturan ulang kata sandi akan dikirimkan.';

    private const UNREGISTERED_PHONE_MESSAGE = 'Tautan pengaturan ulang kata sandi gagal dikirim karena nomor WhatsApp tidak terdaftar.';

    private const SEND_FAILURE_MESSAGE = 'Tautan pengaturan ulang kata sandi belum dapat dikirim melalui WhatsApp.';

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request, FonnteService $fonnteService): RedirectResponse
    {
        $validated = $request->validate([
            'no_telepon' => ['required', 'numeric', 'max_digits:25'],
        ]);

        $phoneNumber = trim((string) $validated['no_telepon']);
        $user = User::query()->where('no_telepon', $phoneNumber)->first();

        if (! $user) {
            return $this->resetLinkFailure($request, self::UNREGISTERED_PHONE_MESSAGE);
        }

        $rateLimitKey = 'password-reset-whatsapp:'.hash('sha256', Str::lower($phoneNumber));

        if (RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
            return back()->with('status', self::RESET_LINK_SENT_MESSAGE);
        }

        $token = Password::createToken($user);
        $resetUrl = url(route('password.reset', [
            'token' => $token,
            'username' => $user->username,
        ], false));

        RateLimiter::hit($rateLimitKey, 60);

        try {
            $result = $fonnteService->sendMessage(
                $user->no_telepon,
                $this->resetMessage($user, $resetUrl),
            );
        } catch (Throwable $exception) {
            report($exception);

            return $this->resetLinkFailure($request, self::SEND_FAILURE_MESSAGE);
        }

        if (! ($result['success'] ?? false)) {
            return $this->resetLinkFailure($request, self::SEND_FAILURE_MESSAGE);
        }

        return back()->with('status', self::RESET_LINK_SENT_MESSAGE);
    }

    private function resetLinkFailure(Request $request, string $message): RedirectResponse
    {
        return back()->withInput($request->only('no_telepon'))
            ->withErrors(['no_telepon' => $message]);
    }

    /**
     * Build the WhatsApp password reset message.
     */
    private function resetMessage(User $user, string $resetUrl): string
    {
        return "Halo {$user->nama},\n\n"
            ."Gunakan tautan berikut untuk mengatur ulang kata sandi akun Anda:\n{$resetUrl}\n\n"
            .'Tautan ini berlaku selama 60 menit. Jika Anda tidak meminta pengaturan ulang kata sandi, abaikan pesan ini.';
    }
}
