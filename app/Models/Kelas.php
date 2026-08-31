<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kelas extends Model
{
    use HasFactory;

    protected $table = 'kelas';

    protected $fillable = [
        'nama_kelas',
        'status',
        'guru_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            'operator', 'kepala_sekolah' => $query,
            'guru' => $query->where('guru_id', $user->getKey()),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function siswas(): HasMany
    {
        return $this->hasMany(Siswa::class);
    }

    public function absensis(): HasMany
    {
        return $this->hasMany(Absensi::class);
    }

    public function riwayatMasuk(): HasMany
    {
        return $this->hasMany(RiwayatKelasSiswa::class, 'kelas_tujuan_id');
    }

    public function riwayatKeluar(): HasMany
    {
        return $this->hasMany(RiwayatKelasSiswa::class, 'kelas_asal_id');
    }

    public function guru(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guru_id');
    }

    public function hasRekapData(): bool
    {
        return $this->absensis()->exists();
    }
}
