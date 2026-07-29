<?php

namespace App\Models;

use Database\Factories\WhatsappNotificationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappNotification extends Model
{
    /** @use HasFactory<WhatsappNotificationFactory> */
    use HasFactory, MassPrunable;

    protected $fillable = [
        'absensi_id',
        'siswa_id',
        'parent_name',
        'parent_phone',
        'message',
        'status',
        'provider',
        'provider_message_id',
        'provider_request_id',
        'attempts',
        'last_error',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function prunable(): Builder
    {
        $retentionDays = max(30, (int) config('services.fonnte.retention_days', 365));

        return static::query()
            ->whereIn('status', ['sent', 'failed', 'cancelled'])
            ->where('created_at', '<=', now()->subDays($retentionDays));
    }

    public function absensi(): BelongsTo
    {
        return $this->belongsTo(Absensi::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function maskedParentPhone(): string
    {
        if (blank($this->parent_phone)) {
            return '-';
        }

        $phone = (string) $this->parent_phone;

        if (strlen($phone) <= 6) {
            return str_repeat('*', strlen($phone));
        }

        return substr($phone, 0, 4)
            .str_repeat('*', strlen($phone) - 6)
            .substr($phone, -2);
    }
}
