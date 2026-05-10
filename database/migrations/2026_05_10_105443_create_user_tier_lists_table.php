<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_tier_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            $table->string('share_token', 32)->unique()->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('user_tier_list_rows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_tier_list_id')->constrained('user_tier_lists')->cascadeOnDelete();
            $table->string('label', 16);
            $table->string('color', 16)->default('zinc');
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->index(['user_tier_list_id', 'position']);
        });

        Schema::create('user_tier_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_tier_list_row_id')->constrained('user_tier_list_rows')->cascadeOnDelete();
            $table->foreignId('hero_id')->constrained('heroes')->restrictOnDelete();
            $table->unsignedSmallInteger('position');
            $table->timestamps();

            $table->unique(['user_tier_list_row_id', 'hero_id']);
            $table->index(['user_tier_list_row_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_tier_list_items');
        Schema::dropIfExists('user_tier_list_rows');
        Schema::dropIfExists('user_tier_lists');
    }
};
