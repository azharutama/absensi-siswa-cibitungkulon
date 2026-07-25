<?php

namespace App\Jobs;

use App\Models\WhatsappNotification;
use App\Services\FonnteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class SendAlpaWhatsappNotificationJob implements ShouldQueue
{
  use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

  public function __construct(public int $notificationId) {}

  public function handle(FonnteService $fonnteService): void
  {
    $notification = WhatsappNotification::query()
      ->with(['absensi.siswa', 'absensi.kelas'])
      ->find($this->notificationId);

    if (! $notification || $notification->status === 'sent') {
      return;
    }

    if (blank($notification->parent_phone)) {
      $notification->update([
        'status' => 'failed',
        'last_error' => 'Nomor WhatsApp orang tua/wali tidak tersedia.',
        'sent_at' => null,
      ]);

      return;
    }

    $notification->update([
      'status' => 'processing',
      'attempts' => $notification->attempts + 1,
      'last_error' => null,
    ]);

    try {
      $result = $fonnteService->sendMessage((string) $notification->parent_phone, (string) $notification->message);
    } catch (Throwable $exception) {
      $notification->update([
        'status' => 'failed',
        'last_error' => $exception->getMessage(),
      ]);

      return;
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
