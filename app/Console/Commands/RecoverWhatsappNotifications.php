<?php

namespace App\Console\Commands;

use App\Jobs\SendAlpaWhatsappNotificationJob;
use App\Models\WhatsappNotification;
use Illuminate\Console\Command;

class RecoverWhatsappNotifications extends Command
{
    protected $signature = 'whatsapp:recover-notifications
        {--limit=500 : Maksimum notifikasi yang dimasukkan kembali ke antrean}';

    protected $description = 'Antrekan kembali notifikasi WhatsApp pending atau processing yang tertinggal';

    public function handle(): int
    {
        $limit = min(5000, max(1, (int) $this->option('limit')));
        $pendingBefore = now()->subMinutes(10);
        $processingBefore = now()->subMinutes(5);

        $notificationIds = WhatsappNotification::query()
            ->where(function ($query) use ($pendingBefore, $processingBefore): void {
                $query->where(function ($query) use ($pendingBefore): void {
                    $query->where('status', 'pending')
                        ->where('updated_at', '<=', $pendingBefore);
                })->orWhere(function ($query) use ($processingBefore): void {
                    $query->where('status', 'processing')
                        ->where('updated_at', '<=', $processingBefore);
                });
            })
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        foreach ($notificationIds as $notificationId) {
            SendAlpaWhatsappNotificationJob::dispatch((int) $notificationId);
        }

        $this->info("{$notificationIds->count()} notifikasi dimasukkan kembali ke antrean.");

        return self::SUCCESS;
    }
}
