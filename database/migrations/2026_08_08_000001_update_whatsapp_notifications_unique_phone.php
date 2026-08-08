<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('whatsapp_notifications', function (Blueprint $table) {
            $table->unique(['absensi_id', 'provider', 'parent_phone']);
            $table->dropUnique('whatsapp_notifications_absensi_id_provider_unique');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_notifications', function (Blueprint $table) {
            $table->unique(['absensi_id', 'provider']);
            $table->dropUnique('whatsapp_notifications_absensi_id_provider_parent_phone_unique');
        });
    }
};
