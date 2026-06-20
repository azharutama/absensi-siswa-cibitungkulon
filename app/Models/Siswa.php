<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Siswa extends Model
{
    /** @use HasFactory<\Database\Factories\SiswaFactory> */
    use HasFactory;

    protected $table = 'siswas';

    protected $fillable = [
        'nis',
        'nama_siswa',
        'jenis_kelamin',
        'nama_ayah',
        'no_whatsapp_ayah',
        'nama_ibu',
        'no_whatsapp_ibu',
        'nama_wali',
        'no_whatsapp_wali',
        'kelas_id',
        'periode_id',
        'status',
    ];

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function absensis(): HasMany
    {
        return $this->hasMany(Absensi::class);
    }
}
