<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class FonnteService
{
    /**
     * @throws ConnectionException
     */
    public function sendMessage(string $target, string $message): array
    {
        $token = config('services.fonnte.token');

        if (blank($token)) {
            return [
                'success' => false,
                'message' => 'Token Fonnte belum dikonfigurasi.',
                'data' => null,
            ];
        }

        $response = Http::timeout((int) config('services.fonnte.timeout', 15))
            ->retry(2, 500)
            ->withHeaders([
                'Authorization' => $token,
            ])
            ->asForm()
            ->post(rtrim(config('services.fonnte.base_url'), '/') . '/send', [
                'target' => $target,
                'message' => $message,
                'countryCode' => config('services.fonnte.country_code', '62'),
                'connectOnly' => config('services.fonnte.connect_only', true) ? 'true' : 'false',
            ]);

        $data = $response->json();
        $success = $response->successful() && (bool) data_get($data, 'status', true);

        return [
            'success' => $success,
            'message' => $success
                ? 'Pesan berhasil dikirim ke Fonnte.'
                : (data_get($data, 'reason') ?: data_get($data, 'message') ?: 'Pengiriman ke Fonnte gagal.'),
            'data' => $data,
        ];
    }
}
