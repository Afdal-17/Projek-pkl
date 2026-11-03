<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep idempotency records independent so deleting related users or transfers cannot enable a replay.
        Schema::create('transfer_idempotency_keys', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('id_user');
            $table->char('key_hash', 64);
            $table->char('request_hash', 64);
            $table->unsignedBigInteger('id_transfer')->nullable();
            $table->timestamps();
            $table->unique(['id_user', 'key_hash'], 'transfer_idempotency_user_key_unique');
        });
    }

    public function down(): void
    {
        if (DB::table('transfer_idempotency_keys')->exists()) {
            throw new RuntimeException(
                'Cannot remove transfer idempotency records after keys have been used.'
            );
        }

        Schema::dropIfExists('transfer_idempotency_keys');
    }
};
