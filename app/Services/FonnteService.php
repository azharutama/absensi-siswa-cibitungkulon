<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;

/**
 * Service untuk mengirim pesan WhatsApp melalui API Fonnte.
 *
 * Fonnte adalah penyedia layanan WhatsApp Gateway yang memungkinkan
 * pengiriman pesan massal via API HTTP. Service ini menangani:
 * - Pengiriman single message
 * - Pengiriman batch/parallel message menggunakan HTTP Pool
 * - Validasi response dan error handling
 * - Retry logic untuk status code tertentu
 */
class FonnteService
{
    /** Status HTTP yang layak untuk dicoba ulang (retry) */
    private const RETRYABLE_STATUSES = [408, 425, 429];

    /**
     * Kirim satu pesan WhatsApp ke satu nomor target.
     *
     * Wrapper sederhana di atas sendMessages() untuk kemudahan penggunaan
     * saat hanya perlu mengirim ke satu penerima.
     *
     * @throws ConnectionException|RequestException
     * @return array{success: bool, message: string, data: mixed}
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
     * Menggunakan Http::pool() Laravel untuk mengirim multiple request
     * secara concurrent, jauh lebih efisien daripada sequential loop.
     * Setiap item dalam $items akan dikirim sebagai request terpisah
     * tapi dieksekusi bersamaan.
     *
     * @param  array<int, array{id: int|string, target: string, message: string}>  $items
     * @return array<int|string, array{success: bool, message: string, data: mixed}>
     */
    public function sendMessages(array $items): array
    {
        // Ambil token API dari config (diset via env FONNTE_TOKEN)
        $token = config('services.fonnte.token');

        // Validasi awal: token harus ada dan minimal ada 1 penerima
        if (blank($token) || $items === []) {
            $error = blank($token)
                ? 'Token Fonnte belum dikonfigurasi.'
                : 'Tidak ada penerima untuk dikirim.';

            // Return array hasil error untuk setiap ID yang diminta
            return array_fill_keys(array_column($items, 'id'), [
                'success' => false,
                'message' => $error,
                'data' => null,
            ]);
        }

        // Eksekusi pool request paralel ke endpoint /send Fonnte
        $responses = Http::pool(function (Pool $pool) use ($items, $token): array {
            $requests = [];

            foreach ($items as $item) {
                // Bangun request individual untuk setiap item
                // ->as() digunakan sebagai key untuk mapping response nanti
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

        // Proses response dari pool request
        foreach ($items as $item) {
            $id = $item['id'];

            try {
                $response = $responses[$id];
                $data = $response->json();

                // Cek status code yang layak retry (timeout, too many requests, dll)
                // atau server error (5xx) -> tandai gagal tapi bisa dicoba lagi
                if (in_array($response->status(), self::RETRYABLE_STATUSES, true) || $response->serverError()) {
                    $results[$id] = [
                        'success' => false,
                        'message' => 'Fonnte merespons HTTP status '.$response->status().'.',
                        'data' => $data,
                    ];

                    continue;
                }

                // Evaluasi keberhasilan berdasarkan response Fonnte:
                // - HTTP 2xx (successful)
                // - Response JSON memiliki status: true
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
                // Tangani koneksi gagal (DNS, timeout, SSL, dll)
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
