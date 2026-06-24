<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HariLibur extends Model
{
    use HasFactory;

    protected $table = 'hari_liburs';

    // Diselaraskan dengan file migration dan controller penampung array request
    protected $fillable = [
        'periode_id',
        'tipe',
        'hari',
        'tanggal',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    /**
     * Relasi balik ke Periode Akademik
     */
    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }
}
