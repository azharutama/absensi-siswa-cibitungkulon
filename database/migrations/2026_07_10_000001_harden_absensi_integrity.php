<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasDuplicates = DB::table('absensis')
            ->select(['siswa_id', 'tanggal'])
            ->groupBy('siswa_id', 'tanggal')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicates) {
            throw new RuntimeException(
                'Migration dibatalkan: rapikan absensi duplikat untuk siswa dan tanggal yang sama terlebih dahulu.'
            );
        }

        Schema::table('absensis', function (Blueprint $table): void {
            $table->unique(
                ['siswa_id', 'tanggal'],
                'absensis_siswa_tanggal_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('absensis', function (Blueprint $table): void {
            $table->dropUnique('absensis_siswa_tanggal_unique');
        });
    }
};
