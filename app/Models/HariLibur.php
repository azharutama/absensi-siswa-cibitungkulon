<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HariLibur extends Model
{
    use HasFactory;

    protected $table = 'hari_liburs';

    protected $fillable = [
        'periode_id',
        'nama_libur',
        'jenis',
        'tanggal',
        'hari',
    ];

    protected function casts(): array
    {
        return [
            'tanggal' => 'date',
        ];
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }
}
