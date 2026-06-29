<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WhatsappNotification extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsappNotificationFactory> */
    use HasFactory;

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

    public function absensi(): BelongsTo
    {
        return $this->belongsTo(Absensi::class);
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }
}
