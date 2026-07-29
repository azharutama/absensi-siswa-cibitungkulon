<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RiwayatKelasSiswa extends Model
{
    use HasFactory;

    protected $table = 'riwayat_kelas_siswa';

    protected $fillable = [
        'siswa_id',
        'kelas_asal_id',
        'kelas_tujuan_id',
        'tanggal_kenaikan',
        'tahun_ajaran',
        'semester',
        'status',
        'keterangan',
    ];

    protected function casts(): array
    {
        return [
            'tanggal_kenaikan' => 'date',
            'semester' => 'integer',
        ];
    }

    public function siswa(): BelongsTo
    {
        return $this->belongsTo(Siswa::class);
    }

    public function kelasAsal(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_asal_id');
    }

    public function kelasTujuan(): BelongsTo
    {
        return $this->belongsTo(Kelas::class, 'kelas_tujuan_id');
    }
}
