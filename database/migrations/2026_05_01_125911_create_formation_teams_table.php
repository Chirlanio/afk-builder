<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formation_teams', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formation_id')->constrained('formations')->cascadeOnDelete();
            $table->unsignedTinyInteger('position');
            $table->string('name')->nullable();
            $table->unsignedInteger('score')->nullable();
            $table->json('faction_bonuses')->nullable();
            $table->timestamps();

            $table->unique(['formation_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formation_teams');
    }
};
