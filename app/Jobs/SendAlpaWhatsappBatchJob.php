<?php

namespace App\Jobs;

use App\Models\WhatsappNotification;
use App\Services\FonnteService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use RuntimeException;

class SendAlpaWhatsappBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $tries = 5;

    /** @var array<int, int> Penundaan antar percobaan dalam detik. */
    public $backoff = [60, 300, 900, 1800, 3600];

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

        $results = $fonnteService->sendMessages($items);

        foreach ($results as $id => $result) {
            $notification = $notifications->get((int) $id);

            if (! $notification) {
                continue;
            }

            $data = $result['data'] ?? [];

            if ($result['success']) {
                $notification->update([
                    'status' => 'sent',
                    'provider_message_id' => $this->stringValue(data_get($data, 'id.0')),
                    'provider_request_id' => $this->stringValue(data_get($data, 'requestid')),
                    'last_error' => null,
                    'sent_at' => now(),
                ]);
                continue;
            }

            $fallback = $this->extractFallback($notification->message);

            if ($fallback) {
                $fallbackNotification = $this->createFallbackNotification($notification, $fallback);
                $fallbackResult = $fonnteService->sendMessage($fallback['phone'], $fallbackNotification->message);

                if ($fallbackResult['success']) {
                    $fallbackNotification->update([
                        'status' => 'sent',
                        'provider_message_id' => $this->stringValue(data_get($fallbackResult['data'], 'id.0')),
                        'provider_request_id' => $this->stringValue(data_get($fallbackResult['data'], 'requestid')),
                        'last_error' => null,
                        'sent_at' => now(),
                    ]);
                    $notification->update([
                        'status' => 'cancelled',
                        'last_error' => 'Dikirim ke nomor cadangan ('.$fallback['name'].').',
                    ]);
                    continue;
                }

                $fallbackNotification->update([
                    'status' => 'failed',
                    'provider_message_id' => $this->stringValue(data_get($fallbackResult['data'], 'id.0')),
                    'provider_request_id' => $this->stringValue(data_get($fallbackResult['data'], 'requestid')),
                    'last_error' => $fallbackResult['message'],
                    'sent_at' => null,
                ]);
            }

            $notification->update([
                'status' => 'failed',
                'provider_message_id' => $this->stringValue(data_get($data, 'id.0')),
                'provider_request_id' => $this->stringValue(data_get($data, 'requestid')),
                'last_error' => $result['message'],
                'sent_at' => null,
            ]);
        }

        $unresolved = WhatsappNotification::query()
            ->whereIn('id', $this->notificationIds)
            ->where('status', '!=', 'sent')
            ->whereNotNull('parent_phone')
            ->where('parent_phone', '!=', '')
            ->exists();

        if ($unresolved) {
            throw new RuntimeException('Masih ada notifikasi WhatsApp yang belum terkirim. Akan dicoba kembali secara otomatis.');
        }
    }

    private function extractFallback(string $message): ?array
    {
        if (! preg_match('/\[Fallback: ([^\]]+) - ([^\]]+)\]/', $message, $matches)) {
            return null;
        }

        return [
            'name' => trim($matches[1]),
            'phone' => trim($matches[2]),
        ];
    }

    private function createFallbackNotification(WhatsappNotification $original, array $fallback): WhatsappNotification
    {
        $cleanMessage = preg_replace('/\n\n\[Fallback: [^\]]+\]/', '', $original->message);
        $message = $this->buildFallbackMessage($cleanMessage, $fallback['name'], $original);

        return WhatsappNotification::create([
            'absensi_id' => $original->absensi_id,
            'siswa_id' => $original->siswa_id,
            'parent_name' => $fallback['name'],
            'parent_phone' => $fallback['phone'],
            'message' => $message,
            'status' => 'processing',
            'provider' => $original->provider,
            'attempts' => 1,
            'last_error' => null,
            'sent_at' => null,
        ]);
    }

    private function buildFallbackMessage(string $originalMessage, string $fallbackName, WhatsappNotification $original): string
    {
        $siswa = $original->siswa;
        $absensiId = $original->absensi_id;

        if (! $siswa || ! $absensiId) {
            return str_replace(
                $original->parent_name ?? 'Bapak/Ibu Orang Tua/Wali',
                $fallbackName,
                $originalMessage
            );
        }

        return preg_replace(
            '/Assalamu\'alaikum [^,\n]+,/',
            "Assalamu'alaikum {$fallbackName},",
            $originalMessage,
            1
        );
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_scalar($value) ? (string) $value : json_encode($value);
    }
}
