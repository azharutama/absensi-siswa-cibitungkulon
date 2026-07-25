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
    /**
     * Display the password reset link request form.
     */
    public function create(): View
    {
        return view('auth.forgot-password');
    }

    /**
     * Send a password reset link to the registered WhatsApp number.
     */
    public function store(Request $request, FonnteService $fonnteService): RedirectResponse
    {
        $validated = $request->validate([
            'no_telepon' => ['required', 'string', 'max:25'],
        ]);

        $phoneNumber = trim($validated['no_telepon']);
        $user = User::query()->where('no_telepon', $phoneNumber)->first();
        $successMessage = 'Jika nomor WhatsApp terdaftar, tautan pengaturan ulang kata sandi akan dikirimkan.';

        if (! $user) {
            return back()->withInput($request->only('no_telepon'))
                ->withErrors(['no_telepon' => 'Tautan pengaturan ulang kata sandi gagal dikirim karena nomor WhatsApp tidak terdaftar.']);
        }

        $rateLimitKey = 'password-reset-whatsapp:'.hash('sha256', Str::lower($phoneNumber));

        if (RateLimiter::tooManyAttempts($rateLimitKey, 1)) {
            return back()->with('status', $successMessage);
        }

        $token = Password::createToken($user);
        $resetUrl = url(route('password.reset', [
            'token' => $token,
            'email' => $user->email,
        ], false));

        RateLimiter::hit($rateLimitKey, 60);

        try {
            $result = $fonnteService->sendMessage(
                $user->no_telepon,
                $this->resetMessage($user, $resetUrl),
            );
        } catch (Throwable $exception) {
            report($exception);

            return back()->withInput($request->only('no_telepon'))
                ->withErrors(['no_telepon' => 'Tautan pengaturan ulang kata sandi belum dapat dikirim melalui WhatsApp.']);
        }

        if (! $result['success']) {
            return back()->withInput($request->only('no_telepon'))
                ->withErrors(['no_telepon' => 'Tautan pengaturan ulang kata sandi belum dapat dikirim melalui WhatsApp.']);
        }

        return back()->with('status', $successMessage);
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
