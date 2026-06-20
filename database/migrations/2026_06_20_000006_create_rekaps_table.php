<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('rekap_absensis', function (Blueprint $table) {
            $table->id();
            $table->foreignId('absensi_id')->constrained('absensis');
            $table->foreignId('user_id')->constrained('users');
            $table->string('nomor_bulan');
            $table->string('id_pengun')->nullable();
            $table->enum('status_pengiriman', ['pending', 'terkirim', 'gagal'])->default('pending');
            $table->timestamp('waktu_kirim')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rekap_absensis');
    }
};
