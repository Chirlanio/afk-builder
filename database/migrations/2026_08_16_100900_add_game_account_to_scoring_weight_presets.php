<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scoring_weight_presets', function (Blueprint $table) {
            $table->foreignId('game_account_id')
                ->nullable()
                ->after('id')
                ->constrained('game_accounts')
                ->cascadeOnDelete();
        });

        DB::statement('
            UPDATE scoring_weight_presets
            SET game_account_id = (
                SELECT id FROM game_accounts
                WHERE game_accounts.user_id = scoring_weight_presets.user_id
                  AND game_accounts.is_default = 1
            )
        ');
    }

    public function down(): void
    {
        Schema::table('scoring_weight_presets', function (Blueprint $table) {
            $table->dropConstrainedForeignId('game_account_id');
        });
    }
};
