<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasDuplicateGoogleIds = DB::table('user')
            ->select('google_id')
            ->whereNotNull('google_id')
            ->groupBy('google_id')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($hasDuplicateGoogleIds) {
            throw new RuntimeException(
                'Cannot add the unique Google ID index: duplicate google_id values must be resolved first.'
            );
        }

        Schema::table('user', function (Blueprint $table): void {
            $table->unique('google_id', 'user_google_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('user', function (Blueprint $table): void {
            $table->dropUnique('user_google_id_unique');
        });
    }
};
