<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Kelas extends Model
{
    /** @use HasFactory<\Database\Factories\KelasFactory> */
    use HasFactory;

    protected $table = 'kelas';

    protected $fillable = [
        'nama_kelas',
        'periode_id',
        'status',
    ];

    public function scopeAccessibleBy(Builder $query, User $user): Builder
    {
        return match ($user->role) {
            'operator', 'kepala_sekolah' => $query,
            'guru' => $query->whereHas('gurus', function (Builder $query) use ($user): void {
                $query->where('users.id', $user->getKey());
            }),
            default => $query->whereRaw('1 = 0'),
        };
    }

    public function periode(): BelongsTo
    {
        return $this->belongsTo(Periode::class);
    }

    public function siswas(): HasMany
    {
        return $this->hasMany(Siswa::class);
    }

    /**
     * Relasi many-to-many ke model User (Guru)
     */
    public function gurus(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'kelas_user', 'kelas_id', 'user_id')
            ->withPivot('is_wali_kelas')
            ->withTimestamps();
    }
}
