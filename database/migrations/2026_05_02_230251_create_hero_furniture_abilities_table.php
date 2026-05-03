<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_furniture_abilities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hero_id')->unique()->constrained('heroes')->cascadeOnDelete();
            $table->string('name');
            $table->text('f3_description')->nullable();
            $table->text('f9_description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_furniture_abilities');
    }
};
