<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('absensis', function (Blueprint $table): void {
            $table->foreignId('kelas_id')
                ->nullable()
                ->after('siswa_id')
                ->constrained('kelas');
        });

        DB::statement(<<<'SQL'
            UPDATE absensis AS absensi
            INNER JOIN siswas AS siswa ON siswa.id = absensi.siswa_id
            SET absensi.kelas_id = siswa.kelas_id
            WHERE absensi.kelas_id IS NULL
        SQL);

        if (DB::table('absensis')->whereNull('kelas_id')->exists()) {
            throw new RuntimeException(
                'Migration dibatalkan: ada absensi yang tidak dapat dipetakan ke kelas siswa.'
            );
        }

        Schema::table('absensis', function (Blueprint $table): void {
            $table->foreignId('kelas_id')->nullable(false)->change();
            $table->index(
                ['kelas_id', 'tanggal', 'status'],
                'absensis_kelas_tanggal_status_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('absensis', function (Blueprint $table): void {
            $table->dropIndex('absensis_kelas_tanggal_status_index');
            $table->dropConstrainedForeignId('kelas_id');
        });
    }
};
