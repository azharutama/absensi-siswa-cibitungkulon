<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('riwayat_kelas_siswa', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('siswa_id')->constrained('siswas')->onDelete('cascade');
            $table->foreignId('kelas_asal_id')->nullable()->constrained('kelas')->onDelete('restrict');
            $table->foreignId('kelas_tujuan_id')->constrained('kelas')->onDelete('restrict');
            $table->date('tanggal_kenaikan');
            $table->string('tahun_ajaran')->nullable();
            $table->tinyInteger('semester')->nullable();
            $table->enum('status', ['aktif', 'selesai'])->default('aktif');
            $table->text('keterangan')->nullable();
            $table->timestamps();

            $table->index(['siswa_id', 'tanggal_kenaikan']);
            $table->index(['kelas_asal_id', 'kelas_tujuan_id']);
            $table->index(['tahun_ajaran', 'semester']);
            $table->index(['siswa_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('riwayat_kelas_siswa');
    }
};
