<?php

namespace App\Jobs;

use App\Models\WhatsappNotification;
use App\Services\FonnteService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class SendAlpaWhatsappNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public int $notificationId) {}

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    /**
     * Execute the job.
     */
    public function handle(FonnteService $fonnteService): void
    {
        $notification = WhatsappNotification::with('absensi')->find($this->notificationId);

        if (! $notification || ! in_array($notification->status, ['pending', 'processing'], true)) {
            return;
        }

        if ($notification->absensi?->status !== 'alpa') {
            $notification->update([
                'status' => 'cancelled',
                'last_error' => 'Status absensi sudah bukan alpa.',
            ]);

            return;
        }

        if (blank($notification->parent_phone)) {
            $notification->update([
                'status' => 'failed',
                'last_error' => 'Nomor WhatsApp orang tua/wali tidak tersedia.',
            ]);

            return;
        }

        $notification->update([
            'status' => 'processing',
            'attempts' => $this->attempts(),
            'last_error' => null,
        ]);

        try {
            $result = $fonnteService->sendMessage($notification->parent_phone, $notification->message);
        } catch (Throwable $exception) {
            $notification->update([
                'status' => $this->attempts() >= $this->tries ? 'failed' : 'pending',
                'last_error' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        $data = $result['data'] ?? [];

        $notification->update([
            'status' => $result['success'] ? 'sent' : 'failed',
            'provider_message_id' => $this->stringValue(data_get($data, 'id')),
            'provider_request_id' => $this->stringValue(data_get($data, 'requestid')),
            'last_error' => $result['success'] ? null : $result['message'],
            'sent_at' => $result['success'] ? now() : null,
        ]);
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_scalar($value) ? (string) $value : json_encode($value);
    }
}
