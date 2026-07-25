<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ResetPasswordNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public string $token) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Build the password reset email.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        return (new MailMessage)
            ->subject('Atur Ulang Kata Sandi')
            ->greeting("Halo {$notifiable->nama},")
            ->line('Kami menerima permintaan untuk mengatur ulang kata sandi akun Anda.')
            ->action('Atur Ulang Kata Sandi', $resetUrl)
            ->line('Tautan ini berlaku selama 60 menit.')
            ->line('Jika Anda tidak meminta pengaturan ulang kata sandi, abaikan email ini.');
    }
}
