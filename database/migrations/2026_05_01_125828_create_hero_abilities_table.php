<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_abilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hero_id')->constrained('heroes')->cascadeOnDelete();
            $table->string('name');
            $table->enum('type', ['basic', 'active', 'passive', 'ultimate', 'signature', 'awakened']);
            $table->unsignedTinyInteger('display_order')->default(0);
            $table->string('ascension_unlock')->nullable();
            $table->text('description')->nullable();
            $table->json('effects')->nullable();
            $table->timestamps();

            $table->index(['hero_id', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_abilities');
    }
};
