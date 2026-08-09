<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Izinkan penghapusan siswa yang memiliki riwayat absensi dengan
     * menghapus juga data terkait (absensi, rekap, notifikasi WhatsApp).
     */
    public function up(): void
    {
        Schema::table('absensis', function (Blueprint $table) {
            $table->dropForeign(['siswa_id']);
            $table->foreign('siswa_id')->references('id')->on('siswas')->onDelete('cascade');
        });

        Schema::table('rekap_absensis', function (Blueprint $table) {
            $table->dropForeign(['absensi_id']);
            $table->foreign('absensi_id')->references('id')->on('absensis')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('absensis', function (Blueprint $table) {
            $table->dropForeign(['siswa_id']);
            $table->foreign('siswa_id')->references('id')->on('siswas');
        });

        Schema::table('rekap_absensis', function (Blueprint $table) {
            $table->dropForeign(['absensi_id']);
            $table->foreign('absensi_id')->references('id')->on('absensis');
        });
    }
};
