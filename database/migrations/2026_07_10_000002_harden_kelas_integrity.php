<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasDuplicateNames = DB::table('kelas')
            ->select(['periode_id', 'nama_kelas'])
            ->whereNotNull('periode_id')
            ->groupBy('periode_id', 'nama_kelas')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateNames) {
            throw new RuntimeException(
                'Migration dibatalkan: terdapat nama kelas duplikat dalam periode yang sama.'
            );
        }

        Schema::table('kelas', function (Blueprint $table): void {
            $table->unique(
                ['periode_id', 'nama_kelas'],
                'kelas_periode_nama_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('kelas', function (Blueprint $table): void {
            $table->dropUnique('kelas_periode_nama_unique');
        });
    }
};
