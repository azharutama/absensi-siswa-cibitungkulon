<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'nama',
        'username',
        'nip',
        'alamat',
        'address',
        'email',
        'no_telepon',
        'password',
        'role',
        'jenis_kelamin',
    ];

    protected $hidden = [
        'password',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function rekaps(): HasMany
    {
        return $this->hasMany(Rekap::class);
    }

    public function getAlamatAttribute(): ?string
    {
        return $this->address;
    }

    public function setAlamatAttribute(?string $value): void
    {
        $this->attributes['address'] = $value;
    }

    /**
     * Relasi many-to-many ke model Kelas
     */
    public function kelas(): BelongsToMany
    {
        return $this->belongsToMany(Kelas::class, 'kelas_user', 'user_id', 'kelas_id')
            ->withPivot('is_wali_kelas')
            ->withTimestamps();
    }
}
