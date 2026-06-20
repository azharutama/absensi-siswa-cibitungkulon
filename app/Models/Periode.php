<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Periode extends Model
{
    /** @use HasFactory<\Database\Factories\PeriodeFactory> */
    use HasFactory;

    protected $fillable = [
        'nama_periode',
        'tanggal_mulai',
        'tanggal_selesai',
        'status_aktif',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'status_aktif' => 'boolean',
        ];
    }

    public function kelas(): HasMany
    {
        return $this->hasMany(Kelas::class);
    }

    public function siswas(): HasMany
    {
        return $this->hasMany(Siswa::class);
    }

    public function hariLiburs(): HasMany
    {
        return $this->hasMany(HariLibur::class);
    }

    public function absensis(): HasMany
    {
        return $this->hasMany(Absensi::class);
    }
}
