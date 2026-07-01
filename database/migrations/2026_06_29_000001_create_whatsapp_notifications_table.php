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
        Schema::create('whatsapp_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('absensi_id')->constrained('absensis')->cascadeOnDelete();
            $table->foreignId('siswa_id')->constrained('siswas')->cascadeOnDelete();
            $table->string('parent_name')->nullable();
            $table->string('parent_phone')->nullable();
            $table->text('message');
            $table->string('status')->default('pending');
            $table->string('provider')->default('fonnte');
            $table->string('provider_message_id')->nullable();
            $table->string('provider_request_id')->nullable();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->unique(['absensi_id', 'provider']);
            $table->index(['status', 'created_at']);
            $table->index(['siswa_id', 'created_at']);
            $table->index(['provider', 'status']);
            $table->index('provider_message_id');
            $table->index('provider_request_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_notifications');
    }
};
