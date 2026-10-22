<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Dibutuhkan oleh SESSION_DRIVER=database (default .env.example).
     * Tanpa tabel ini, setup baru akan error di setiap request.
     *
     * user_id tanpa foreign key: tabel user memakai primary key id_user
     * (bukan id), dan pembersih sesi cukup menghapus baris sesi saja.
     */
    public function up(): void
    {
        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });

        // Tabel standar Laravel untuk broker reset password bawaan.
        // Alur reset saat ini memakai cache, tetapi tabel ini disiapkan agar
        // aman bila kelak memakai broker bawaan atau package lain.
        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
