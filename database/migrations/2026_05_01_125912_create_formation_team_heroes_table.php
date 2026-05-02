<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formation_team_heroes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formation_team_id')->constrained('formation_teams')->cascadeOnDelete();
            $table->foreignId('hero_id')->constrained('heroes')->restrictOnDelete();
            $table->unsignedTinyInteger('slot');
            $table->enum('row', ['front', 'back'])->nullable();
            $table->timestamps();

            $table->unique(['formation_team_id', 'slot']);
            $table->index('hero_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formation_team_heroes');
    }
};
