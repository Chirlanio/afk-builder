<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_tier_lists', function (Blueprint $table) {
            $table->boolean('is_template')->default(false)->after('share_token');
            $table->foreignId('cloned_from_id')
                ->nullable()
                ->after('is_template')
                ->constrained('user_tier_lists')
                ->nullOnDelete();

            $table->index('is_template');
        });
    }

    public function down(): void
    {
        Schema::table('user_tier_lists', function (Blueprint $table) {
            $table->dropForeign(['cloned_from_id']);
            $table->dropIndex(['is_template']);
            $table->dropColumn(['is_template', 'cloned_from_id']);
        });
    }
};
