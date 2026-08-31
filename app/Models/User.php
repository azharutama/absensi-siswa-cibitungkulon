<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'nama',
        'nip',
        'username',
        'alamat',
        'address',
        'no_telepon',
        'password',
        'role',
        'jenis_kelamin',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
        ];
    }

    public function absensis(): HasMany
    {
        return $this->hasMany(Absensi::class);
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
     * Relasi one-to-one ke model Kelas
     */
    public function kelas(): HasOne
    {
        return $this->hasOne(Kelas::class, 'guru_id');
    }

    /**
     * Get the email address for password reset.
     */
    public function getEmailForPasswordReset(): string
    {
        return $this->username;
    }
}
