<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hero_id')->constrained('heroes')->cascadeOnDelete();
            $table->string('tag', 32);
            $table->text('note')->nullable();
            $table->string('source', 64)->default('afk.guide');
            $table->string('source_url', 500)->nullable();
            $table->timestamps();

            $table->unique(['hero_id', 'tag', 'source'], 'hero_features_unique');
            $table->index('tag');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_features');
    }
};
