<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kelas', function (Blueprint $table) {
            $table->id();
            $table->string('nama_kelas');
            $table->string('status')->default('aktif');
            $table->timestamps();

            $table->unique('nama_kelas');
            $table->index('nama_kelas');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas');
    }
};
