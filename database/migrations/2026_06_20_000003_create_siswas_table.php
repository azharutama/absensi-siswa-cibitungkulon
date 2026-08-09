<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('siswas', function (Blueprint $table) {
            $table->id();
            $table->string('nis')->unique()->nullable();
            $table->string('nisn')->unique()->nullable();
            $table->string('nama_siswa');
            $table->enum('jenis_kelamin', ['laki-laki', 'perempuan']);
            $table->text('alamat')->nullable();
            $table->string('nama_ayah');
            $table->string('no_whatsapp_ayah')->nullable();
            $table->string('nama_ibu');
            $table->string('no_whatsapp_ibu')->nullable();
            $table->foreignId('kelas_id')->constrained('kelas');
            $table->timestamps();

            $table->index('nama_siswa');
            $table->index(['kelas_id', 'nama_siswa']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('siswas');
    }
};
