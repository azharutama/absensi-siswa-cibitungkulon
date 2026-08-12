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
            $table->foreignId('guru_id')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            $table->unique('nama_kelas');
            $table->index('nama_kelas');
            $table->index('status');
            $table->index('guru_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kelas');
    }
};
