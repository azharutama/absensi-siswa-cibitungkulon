<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Periode extends Model
{
    use HasFactory;

    protected $table = 'periodes';

    protected $fillable = [
        'tahun_ajaran',
        'semester',
        'tipe_periode',
        'nama_periode',
        'tanggal_mulai',
        'tanggal_selesai',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_mulai' => 'date',
            'tanggal_selesai' => 'date',
            'tahun_ajaran' => 'string',
            'semester' => 'integer',
        ];
    }

    public function scopeForTahunAjaran(Builder $query, string $tahunAjaran): void
    {
        $query->where('tahun_ajaran', $tahunAjaran);
    }

    public function scopeSemester(Builder $query, int $semester): void
    {
        $query->where('semester', $semester);
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

    public function namaLengkap(): string
    {
        if ($this->tipe_periode === 'tahunan') {
            return "Tahun Ajaran {$this->tahun_ajaran}";
        }

        $semesterNama = $this->semester === 1 ? 'Ganjil' : 'Genap';

        return "Semester {$semesterNama} {$this->tahun_ajaran}";
    }

    public function isEndingSoon(int $days = 7): bool
    {
        return $this->tanggal_selesai->diffInDays(now()) <= $days;
    }

    public function daysUntilEnd(): int
    {
        return $this->tanggal_selesai->diffInDays(now(), false);
    }
}
