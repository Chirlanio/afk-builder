<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_ratings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hero_id')->constrained('heroes')->cascadeOnDelete();
            $table->string('mode', 32);
            $table->string('stage', 16);
            $table->string('tier', 4);
            $table->string('source', 64)->default('afk.guide');
            $table->string('source_url', 500)->nullable();
            $table->string('sync_batch', 64)->nullable();
            $table->timestamps();

            $table->unique(['hero_id', 'mode', 'stage', 'source'], 'hero_ratings_unique');
            $table->index(['mode', 'stage', 'tier']);
            $table->index('sync_batch');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_ratings');
    }
};
