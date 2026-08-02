<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('periodes', function (Blueprint $table) {
            $table->dropUnique('periodes_single_active_unique');
            $table->dropIndex(['status_aktif', 'tanggal_mulai']);
            $table->dropIndex(['status_aktif']);
            $table->dropColumn(['status_aktif', 'active_guard']);
        });
    }

    public function down(): void
    {
        Schema::table('periodes', function (Blueprint $table) {
            $table->boolean('status_aktif')->default(false)->after('tanggal_selesai');
            $table->unsignedTinyInteger('active_guard')
                ->nullable()
                ->storedAs('IF(status_aktif = 1, 1, NULL)')
                ->after('status_aktif');
            $table->unique('active_guard', 'periodes_single_active_unique');
            $table->index(['status_aktif', 'tanggal_mulai']);
            $table->index('status_aktif');
        });

        DB::statement('UPDATE periodes SET status_aktif = 1 WHERE id = (SELECT id FROM (SELECT id FROM periodes ORDER BY id DESC LIMIT 1) AS tmp)');
    }
};