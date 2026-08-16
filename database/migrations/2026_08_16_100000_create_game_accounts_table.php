<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->string('server', 60)->nullable();
            $table->foreignId('avatar_hero_id')
                ->nullable()
                ->constrained('heroes')
                ->nullOnDelete();
            $table->boolean('is_default')->default(false);
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'is_default']);
        });

        DB::statement("
            INSERT INTO game_accounts (user_id, name, is_default, created_at, updated_at)
            SELECT id, 'Conta Principal', 1, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP FROM users
        ");
    }

    public function down(): void
    {
        Schema::dropIfExists('game_accounts');
    }
};
