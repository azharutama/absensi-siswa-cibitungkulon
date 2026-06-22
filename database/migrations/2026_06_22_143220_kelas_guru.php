<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas_user', function (Blueprint $table) {
            $table->id();
            // Menghubungkan ke tabel kelas
            $table->foreignId('kelas_id')->constrained('kelas')->onDelete('cascade');
            // Menghubungkan ke tabel users (guru)
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            // Kolom penanda apakah guru ini merupakan wali kelas di kelas tersebut
            $table->boolean('is_wali_kelas')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas_user');
    }
};
