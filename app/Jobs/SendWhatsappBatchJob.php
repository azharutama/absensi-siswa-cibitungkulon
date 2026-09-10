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

/**
 * Job untuk mengirim notifikasi WhatsApp absensi "Alpa" (tidak hadir) ke orang tua/wali.
 *
 * Job ini dijalankan via queue (ShouldQueue) dengan retry strategy:
 * - Max 5 kali percobaan ($tries = 5)
 * - Exponential backoff: 1m, 5m, 15m, 30m, 1h ($backoff)
 *
 * Fitur utama:
 * - Batch processing: kirim multiple notifikasi dalam satu job
 * - Parallel sending via FonnteService::sendMessages() (HTTP pool)
 * - Fallback ke nomor cadangan jika pengiriman gagal
 * - Auto-retry via exception jika ada notifikasi yang belum terkirim
 */
class SendWhatsappBatchJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** Maksimal percobaan eksekusi job (inklusi attempt pertama) */
    public $tries = 5;

    /**
     * Penundaan antar percobaan dalam detik (exponential backoff).
     * Index 0 = delay sebelum retry ke-1, dst.
     * [60, 300, 900, 1800, 3600] = 1m, 5m, 15m, 30m, 1h
     *
     * @var array<int, int>
     */
    public $backoff = [60, 300, 900, 1800, 3600];

    /**
     * Daftar ID notifikasi WhatsApp yang akan diproses.
     *
     * @param  array<int, int>  $notificationIds
     */
    public function __construct(public array $notificationIds) {}

    /**
     * Eksekusi job: kirim notifikasi WhatsApp alpa ke orang tua/wali.
     */
    public function handle(FonnteService $fonnteService): void
    {
        // Ambil semua notifikasi berdasarkan ID yang dikirim ke job
        // keyBy('id') memudahkan lookup by ID nanti
        $notifications = WhatsappNotification::query()
            ->whereIn('id', $this->notificationIds)
            ->get()
            ->keyBy('id');

        // Tidak ada notifikasi valid -> selesai
        if ($notifications->isEmpty()) {
            return;
        }

        $items = [];

        // Persiapkan item pengiriman untuk setiap notifikasi
        foreach ($notifications as $notification) {
            // Skip yang sudah terkirim (mungkin diproses job lain/retri sebelumnya)
            if ($notification->status === 'sent') {
                continue;
            }

            // Validasi nomor telepon wajib ada
            if (blank($notification->parent_phone)) {
                $notification->update([
                    'status' => 'failed',
                    'last_error' => 'Nomor WhatsApp orang tua/wali tidak tersedia.',
                    'sent_at' => null,
                ]);

                continue;
            }

            // Tandai sedang diproses & increment attempts
            $notification->update([
                'status' => 'processing',
                'attempts' => $notification->attempts + 1,
                'last_error' => null,
            ]);

            // Siapkan data untuk FonnteService::sendMessages()
            // Hapus tag [Fallback: ...] dari pesan sebelum dikirim ke penerima utama
            $cleanMessage = preg_replace('/\s*\[Fallback: [^\]]+\]/', '', $notification->message);

            $items[] = [
                'id' => $notification->id,           // digunakan sebagai key di pool response
                'target' => $notification->parent_phone,
                'message' => $cleanMessage,
            ];
        }

        // Tidak ada item valid untuk dikirim
        if ($items === []) {
            return;
        }

        // Kirim batch paralel via FonnteService (menggunakan HTTP pool)
        $results = $fonnteService->sendMessages($items);

        // Proses hasil pengiriman per notifikasi
        foreach ($results as $id => $result) {
            $notification = $notifications->get((int) $id);

            // Notifikasi tidak ditemukan (seharusnya tidak terjadi)
            if (! $notification) {
                continue;
            }

            $data = $result['data'] ?? [];

            // ===== SUKSES =====
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

            // ===== GAGAL: Coba fallback ke nomor cadangan =====
            // Format fallback di pesan: [Fallback: Nama - NomorTelepon]
            $fallback = $this->extractFallback($notification->message);

            if ($fallback) {
                // Buat notifikasi baru untuk nomor cadangan
                $fallbackNotification = $this->createFallbackNotification($notification, $fallback);

                // Kirim ke nomor cadangan (single message, bukan batch)
                $fallbackResult = $fonnteService->sendMessage($fallback['phone'], $fallbackNotification->message);

                // Fallback berhasil -> update kedua notifikasi
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

                // Fallback juga gagal -> catat error fallback
                $fallbackNotification->update([
                    'status' => 'failed',
                    'provider_message_id' => $this->stringValue(data_get($fallbackResult['data'], 'id.0')),
                    'provider_request_id' => $this->stringValue(data_get($fallbackResult['data'], 'requestid')),
                    'last_error' => $fallbackResult['message'],
                    'sent_at' => null,
                ]);
            }

            // ===== GAGAL TOTAL (tanpa fallback atau fallback gagal) =====
            $notification->update([
                'status' => 'failed',
                'provider_message_id' => $this->stringValue(data_get($data, 'id.0')),
                'provider_request_id' => $this->stringValue(data_get($data, 'requestid')),
                'last_error' => $result['message'],
                'sent_at' => null,
            ]);
        }

        // ===== CEK APAKAH MASIH ADA YANG BELUM TERKIRIM =====
        // Jika ada notifikasi dengan nomor valid tapi status != sent,
        // lempar exception agar job di-retry otomatis sesuai $backoff
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

    /**
     * Ekstrak informasi fallback dari pesan.
     *
     * Format yang diharapkan: [Fallback: Nama Lengkap - 08xxxxxxxxxx]
     * Contoh: [Fallback: Bapak Budi - 081234567890]
     */
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

    /**
     * Buat notifikasi baru untuk nomor fallback/cadangan.
     *
     * Menghapus tag [Fallback: ...] dari pesan asli dan
     * menyesuaikan sapaan ke nama kontak cadangan.
     */
    private function createFallbackNotification(WhatsappNotification $original, array $fallback): WhatsappNotification
    {
        // Hapus baris fallback dari pesan
        $cleanMessage = preg_replace('/\n\n\[Fallback: [^\]]+\]/', '', $original->message);

        // Bangun pesan baru dengan nama fallback
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

    /**
     * Bangun pesan WhatsApp untuk nomor fallback.
     *
     * Mengganti sapaan "Assalamu'alaikum [Nama Asli],"
     * menjadi "Assalamu'alaikum [Nama Fallback],"
     *
     * Jika relasi siswa/absensi tidak tersedia, gunakan str_replace
     * sederhana pada nama orang tua asli.
     */
    private function buildFallbackMessage(string $originalMessage, string $fallbackName, WhatsappNotification $original): string
    {
        $siswa = $original->siswa;
        $absensiId = $original->absensi_id;

        // Fallback sederhana jika relasi tidak termuat
        if (! $siswa || ! $absensiId) {
            return str_replace(
                $original->parent_name ?? 'Bapak/Ibu Orang Tua/Wali',
                $fallbackName,
                $originalMessage
            );
        }

        // Ganti sapaan formal di awal pesan (hanya kemunculan pertama)
        return preg_replace(
            '/Assalamu\'alaikum [^,\n]+,/',
            "Assalamu'alaikum {$fallbackName},",
            $originalMessage,
            1
        );
    }

    /**
     * Helper: konversi nilai ke string aman untuk DB.
     *
     * - null -> null
     * - scalar -> (string)
     * - array/object -> json_encode
     */
    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return is_scalar($value) ? (string) $value : json_encode($value);
    }
}
