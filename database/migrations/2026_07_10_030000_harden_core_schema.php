<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::table('kelas')->whereNull('periode_id')->exists()) {
            throw new RuntimeException('Migration dibatalkan: ada kelas tanpa periode.');
        }

        if (DB::table('siswas')->whereNull('nama_siswa')->exists()) {
            throw new RuntimeException('Migration dibatalkan: ada siswa tanpa nama.');
        }

        $duplicateAssignments = DB::table('kelas_user')
            ->select(['kelas_id', 'user_id'])
            ->groupBy('kelas_id', 'user_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicateAssignments) {
            throw new RuntimeException('Migration dibatalkan: terdapat relasi kelas dan guru yang duplikat.');
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'remember_token')) {
                $table->rememberToken();
            }
        });

        Schema::table('kelas', function (Blueprint $table): void {
            $table->foreignId('periode_id')->nullable(false)->change();
        });

        Schema::table('siswas', function (Blueprint $table): void {
            $table->string('nama_siswa')->nullable(false)->change();
        });

        Schema::table('kelas_user', function (Blueprint $table): void {
            $table->unique(['kelas_id', 'user_id'], 'kelas_user_kelas_user_unique');
        });

        Schema::table('whatsapp_notifications', function (Blueprint $table): void {
            $table->index(['created_at', 'id'], 'whatsapp_notifications_created_id_index');
        });
    }

    public function down(): void
    {
        Schema::table('whatsapp_notifications', function (Blueprint $table): void {
            $table->dropIndex('whatsapp_notifications_created_id_index');
        });

        Schema::table('kelas_user', function (Blueprint $table): void {
            $table->dropUnique('kelas_user_kelas_user_unique');
        });

        Schema::table('siswas', function (Blueprint $table): void {
            $table->string('nama_siswa')->nullable()->change();
        });

        Schema::table('kelas', function (Blueprint $table): void {
            $table->foreignId('periode_id')->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table): void {
            if (Schema::hasColumn('users', 'remember_token')) {
                $table->dropRememberToken();
            }
        });
    }
};
