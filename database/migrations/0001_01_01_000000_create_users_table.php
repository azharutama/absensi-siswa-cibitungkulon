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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('nama');
            $table->string('nip')->nullable();
            $table->string('username')->unique();
            $table->string('alamat')->nullable();
            $table->string('no_telepon');
            $table->string('password');
            $table->rememberToken();
            $table->enum('role', ['operator', 'guru', 'kepala_sekolah']);
            $table->string('jenis_kelamin')->nullable();
            $table->timestamps();

            $table->index('nama');
            $table->index('role');
            $table->index(['role', 'nama']);
            $table->unique(['role', 'nip'], 'users_role_nip_unique');
            $table->unique(['role', 'no_telepon'], 'users_role_no_telepon_unique');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
