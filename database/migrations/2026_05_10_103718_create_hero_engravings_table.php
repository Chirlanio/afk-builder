<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_engravings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hero_id')->constrained('heroes')->cascadeOnDelete();
            $table->string('level', 4);
            $table->string('label', 128);
            $table->text('description');
            $table->string('source', 64)->default('afk.guide');
            $table->string('source_url', 500)->nullable();
            $table->timestamps();

            $table->unique(['hero_id', 'level', 'source'], 'hero_engravings_unique');
            $table->index(['hero_id', 'level']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_engravings');
    }
};
