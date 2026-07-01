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
        Schema::create('siswas', function (Blueprint $table) {
            $table->id();
            $table->string('nis')->unique()->nullable();
            $table->string('nisn')->unique()->nullable();
            $table->string('nama_siswa')->nullable();
            $table->enum('jenis_kelamin', ['laki-laki', 'perempuan']);
            $table->string('nama_ayah');
            $table->string('no_whatsapp_ayah');
            $table->string('nama_ibu');
            $table->string('no_whatsapp_ibu');
            $table->string('nama_wali')->nullable();
            $table->string('no_whatsapp_wali')->nullable();
            $table->foreignId('kelas_id')->constrained('kelas');
            $table->foreignId('periode_id')->constrained('periodes');
            $table->string('status');
            $table->timestamps();

            $table->index('nama_siswa');
            $table->index('status');
            $table->index(['kelas_id', 'nama_siswa']);
            $table->index(['periode_id', 'status']);
            $table->index(['status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('siswas');
    }
};
