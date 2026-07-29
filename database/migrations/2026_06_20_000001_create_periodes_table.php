<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('periodes', function (Blueprint $table) {
            $table->id();
            $table->string('tahun_ajaran');
            $table->tinyInteger('semester')->nullable();
            $table->enum('tipe_periode', ['semester', 'tahunan'])->default('semester');
            $table->string('nama_periode');
            $table->date('tanggal_mulai');
            $table->date('tanggal_selesai');
            $table->boolean('status_aktif');
            $table->unsignedTinyInteger('active_guard')
                ->nullable()
                ->storedAs('IF(status_aktif = 1, 1, NULL)');
            $table->timestamps();

            $table->unique('active_guard', 'periodes_single_active_unique');
            $table->unique(['tahun_ajaran', 'semester'], 'periodes_tahun_semester_unique');
            $table->index('status_aktif');
            $table->index('tahun_ajaran');
            $table->index('tanggal_mulai');
            $table->index(['status_aktif', 'tanggal_mulai']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('periodes');
    }
};
