<?php

namespace App\Console\Commands;

use App\Jobs\SendAlpaWhatsappBatchJob;
use App\Models\WhatsappNotification;
use Illuminate\Console\Command;

class RetryFailedWhatsappNotifications extends Command
{
    public const MAX_ATTEMPTS = 20;

    protected $signature = 'whatsapp:retry-failed';

    protected $description = 'Mengirim ulang notifikasi WhatsApp yang gagal hingga berhasil';

    public function handle(): int
    {
        $ids = WhatsappNotification::query()
            ->where('status', 'failed')
            ->whereNotNull('parent_phone')
            ->where('parent_phone', '!=', '')
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->where('updated_at', '<=', now()->subMinutes(15))
            ->orderBy('updated_at')
            ->pluck('id')
            ->all();

        if ($ids === []) {
            $this->info('Tidak ada notifikasi WhatsApp gagal yang perlu dicoba ulang.');

            return self::SUCCESS;
        }

        SendAlpaWhatsappBatchJob::dispatch($ids);

        $this->info('Menjadwalkan ulang '.count($ids).' notifikasi WhatsApp yang gagal.');

        return self::SUCCESS;
    }
}
