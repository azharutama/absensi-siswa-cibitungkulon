<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Siswa extends Model
{
    use HasFactory;

    protected $table = 'siswas';

    protected $fillable = [
        'nis',
        'nisn',
        'nama_siswa',
        'jenis_kelamin',
        'alamat',
        'nama_ayah',
        'no_whatsapp_ayah',
        'nama_ibu',
        'no_whatsapp_ibu',
        'nama_wali',
        'no_whatsapp_wali',
        'kelas_id',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    public function kelas(): BelongsTo
    {
        return $this->belongsTo(Kelas::class);
    }

    public function absensis(): HasMany
    {
        return $this->hasMany(Absensi::class);
    }

    public function riwayatKelas(): HasMany
    {
        return $this->hasMany(RiwayatKelasSiswa::class);
    }

    public function kelasLulusan(): ?string
    {
        return $this->kelas?->nama_kelas;
    }

    public function pindahKeKelas(Kelas $kelasTujuan, string $tanggalKenaikan, ?string $keterangan = null): RiwayatKelasSiswa
    {
        $kelasAsal = $this->kelas;

        $this->update(['kelas_id' => $kelasTujuan->id]);

        $activePeriode = Periode::query()->latest('id')->first();

        return $this->riwayatKelas()->create([
            'kelas_asal_id' => $kelasAsal?->id,
            'kelas_tujuan_id' => $kelasTujuan->id,
            'tanggal_kenaikan' => $tanggalKenaikan,
            'tahun_ajaran' => $activePeriode?->tahun_ajaran,
            'semester' => $activePeriode?->semester,
            'keterangan' => $keterangan,
            'status' => 'aktif',
        ]);
    }
}
