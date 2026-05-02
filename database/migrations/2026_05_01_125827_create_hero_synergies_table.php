<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_synergies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hero_a_id')->constrained('heroes')->cascadeOnDelete();
            $table->foreignId('hero_b_id')->constrained('heroes')->cascadeOnDelete();
            $table->smallInteger('weight')->default(10);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->unique(['hero_a_id', 'hero_b_id']);
            $table->index('hero_b_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_synergies');
    }
};
