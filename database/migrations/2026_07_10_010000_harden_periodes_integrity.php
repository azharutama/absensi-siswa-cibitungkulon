<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateNameExists = DB::table('periodes')
            ->select('nama_periode')
            ->groupBy('nama_periode')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateNameExists) {
            throw new RuntimeException('Migration dibatalkan: terdapat nama periode yang duplikat.');
        }

        if (DB::table('periodes')->where('status_aktif', true)->count() > 1) {
            throw new RuntimeException('Migration dibatalkan: terdapat lebih dari satu periode aktif.');
        }

        Schema::table('periodes', function (Blueprint $table): void {
            $table->unique('nama_periode', 'periodes_nama_unique');
            $table->unsignedTinyInteger('active_guard')
                ->nullable()
                ->storedAs('IF(status_aktif = 1, 1, NULL)');
            $table->unique('active_guard', 'periodes_single_active_unique');
        });
    }

    public function down(): void
    {
        Schema::table('periodes', function (Blueprint $table): void {
            $table->dropUnique('periodes_single_active_unique');
            $table->dropColumn('active_guard');
            $table->dropUnique('periodes_nama_unique');
        });
    }
};
