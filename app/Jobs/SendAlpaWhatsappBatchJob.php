<?php

namespace App\Jobs;

use App\Models\WhatsappNotification;
use App\Services\FonnteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendAlpaWhatsappBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * @param  array<int, int>  $notificationIds
     */
    public function __construct(public array $notificationIds) {}

    public function handle(FonnteService $fonnteService): void
    {
        $notifications = WhatsappNotification::query()
            ->whereIn('id', $this->notificationIds)
            ->get()
            ->keyBy('id');

        if ($notifications->isEmpty()) {
            return;
        }

        $items = [];

        foreach ($notifications as $notification) {
            if ($notification->status === 'sent') {
                continue;
            }

            if (blank($notification->parent_phone)) {
                $notification->update([
                    'status' => 'failed',
                    'last_error' => 'Nomor WhatsApp orang tua/wali tidak tersedia.',
                    'sent_at' => null,
                ]);

                continue;
            }

            $notification->update([
                'status' => 'processing',
                'attempts' => $notification->attempts + 1,
                'last_error' => null,
            ]);

            $items[] = [
                'id' => $notification->id,
                'target' => $notification->parent_phone,
                'message' => $notification->message,
            ];
        }

        if ($items === []) {
            return;
        }

        foreach ($fonnteService->sendMessages($items) as $id => $result) {
            $notification = $notifications->get((int) $id);

            if (! $notification) {
                continue;
            }

            $data = $result['data'] ?? [];

            $notification->update([
                'status' => $result['success'] ? 'sent' : 'failed',
                'provider_message_id' => $this->stringValue(data_get($data, 'data.id')),
                'provider_request_id' => $this->stringValue(data_get($data, 'data.requestid')),
                'last_error' => $result['success'] ? null : $result['message'],
                'sent_at' => $result['success'] ? now() : null,
            ]);
        }
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_scalar($value) ? (string) $value : json_encode($value);
    }
}
