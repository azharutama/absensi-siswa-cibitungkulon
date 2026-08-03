<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

class FonnteService
{
    private const RETRYABLE_STATUSES = [408, 425, 429];

    /**
     * @throws ConnectionException|RequestException
     */
    public function sendMessage(string $target, string $message): array
    {
        return $this->sendMessages([
            ['id' => 0, 'target' => $target, 'message' => $message],
        ])[0];
    }

    /**
     * Kirim beberapa pesan secara paralel dalam satu request pool.
     *
     * @param  array<int, array{id: int|string, target: string, message: string}>  $items
     * @return array<int|string, array{success: bool, message: string, data: mixed}>
     */
    public function sendMessages(array $items): array
    {
        $token = config('services.fonnte.token');

        if (blank($token) || $items === []) {
            $error = blank($token)
                ? 'Token Fonnte belum dikonfigurasi.'
                : 'Tidak ada penerima untuk dikirim.';

            return array_fill_keys(array_column($items, 'id'), [
                'success' => false,
                'message' => $error,
                'data' => null,
            ]);
        }

        $responses = Http::pool(function (Pool $pool) use ($items, $token): array {
            $requests = [];

            foreach ($items as $item) {
                $requests[] = $pool
                    ->as((string) $item['id'])
                    ->baseUrl(rtrim((string) config('services.fonnte.base_url'), '/'))
                    ->timeout((int) config('services.fonnte.timeout', 15))
                    ->withHeaders(['Authorization' => $token])
                    ->asForm()
                    ->post('/send', [
                        'target' => $item['target'],
                        'message' => $item['message'],
                        'countryCode' => config('services.fonnte.country_code', '62'),
                        'connectOnly' => config('services.fonnte.connect_only', true) ? 'true' : 'false',
                    ]);
            }

            return $requests;
        });

        $results = [];

        foreach ($items as $item) {
            $id = $item['id'];

            try {
                $response = $responses[$id];
                $data = $response->json();

                if (in_array($response->status(), self::RETRYABLE_STATUSES, true) || $response->serverError()) {
                    $results[$id] = [
                        'success' => false,
                        'message' => 'Fonnte merespons HTTP status '.$response->status().'.',
                        'data' => $data,
                    ];

                    continue;
                }

                $success = $response->successful()
                    && is_array($data)
                    && data_get($data, 'status') === true;

                $results[$id] = [
                    'success' => $success,
                    'message' => $success
                        ? 'Pesan berhasil dikirim ke Fonnte.'
                        : (data_get($data, 'reason') ?: data_get($data, 'message') ?: 'Pengiriman ke Fonnte gagal.'),
                    'data' => $data,
                ];
            } catch (ConnectionException $exception) {
                $results[$id] = [
                    'success' => false,
                    'message' => $exception->getMessage(),
                    'data' => null,
                ];
            }
        }

        return $results;
    }
}
