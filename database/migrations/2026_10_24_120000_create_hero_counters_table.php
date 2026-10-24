<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Counter: "hero_id é forte contra target_hero_id" num Contexto (mesmos
        // valores de hero_ratings.mode), com força 1-2, motivo e fonte.
        Schema::create('hero_counters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hero_id')->constrained('heroes')->cascadeOnDelete();
            $table->foreignId('target_hero_id')->constrained('heroes')->cascadeOnDelete();
            $table->string('context', 32);
            $table->unsignedTinyInteger('strength');
            $table->string('reason', 500);
            $table->string('source_url', 500)->nullable();
            $table->timestamps();

            $table->unique(['hero_id', 'target_hero_id', 'context']);
            $table->index(['target_hero_id', 'context']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_counters');
    }
};
