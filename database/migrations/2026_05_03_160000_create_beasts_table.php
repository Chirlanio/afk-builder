<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beasts', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 128)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            // Buffs aplicados aos heróis do tipo correspondente (Strength/Intelligence/Agility).
            // Valores em pontos (interpretação livre — admin cadastra baseado em conhecimento do jogo).
            $table->unsignedSmallInteger('strength_buff')->default(0);
            $table->unsignedSmallInteger('intelligence_buff')->default(0);
            $table->unsignedSmallInteger('agility_buff')->default(0);
            $table->string('portrait_url')->nullable();
            $table->string('source_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beasts');
    }
};
