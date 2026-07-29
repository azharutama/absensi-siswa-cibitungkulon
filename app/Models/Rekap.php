<?php

namespace App\Models;

use Database\Factories\RekapFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rekap extends Model
{
    /** @use HasFactory<RekapFactory> */
    use HasFactory;

    protected $table = 'rekap_absensis';

    protected $fillable = [
        'absensi_id',
        'user_id',
        'nomor_bulan',
        'id_pengun',
        'status_pengiriman',
        'waktu_kirim',
    ];

    protected function casts(): array
    {
        return [
            'waktu_kirim' => 'datetime',
        ];
    }

    public function absensi(): BelongsTo
    {
        return $this->belongsTo(Absensi::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
