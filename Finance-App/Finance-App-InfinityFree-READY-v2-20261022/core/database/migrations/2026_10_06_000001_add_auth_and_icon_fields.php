<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user', function (Blueprint $table) {
            $table->timestamp('email_verified_at')->nullable()->after('email');
            $table->timestamp('banned_at')->nullable()->after('last_seen_at');
        });

        DB::table('user')->whereNull('email_verified_at')->update([
            'email_verified_at' => DB::raw('created_at'),
        ]);

        Schema::table('kategori', function (Blueprint $table) {
            $table->string('icon', 30)->nullable()->after('jenis');
        });
    }

    public function down(): void
    {
        Schema::table('user', function (Blueprint $table) {
            $table->dropColumn(['email_verified_at', 'banned_at']);
        });

        Schema::table('kategori', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
