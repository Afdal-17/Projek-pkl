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
            $table->string('otp_kode')->nullable()->after('google_id');
            $table->datetime('otp_expires_at')->nullable()->after('otp_kode');
            $table->unsignedSmallInteger('otp_attempts')->default(0)->after('otp_expires_at');
            $table->datetime('otp_verified_at')->nullable()->after('email_verified_at');
        });

        // User yang sudah ada sebelum fitur OTP dianggap sudah terverifikasi OTP.
        DB::table('user')
            ->whereNull('otp_verified_at')
            ->update(['otp_verified_at' => DB::raw('COALESCE(email_verified_at, CURRENT_TIMESTAMP)')]);
    }

    public function down(): void
    {
        Schema::table('user', function (Blueprint $table) {
            $table->dropColumn(['otp_kode', 'otp_expires_at', 'otp_attempts', 'otp_verified_at']);
        });
    }
};
