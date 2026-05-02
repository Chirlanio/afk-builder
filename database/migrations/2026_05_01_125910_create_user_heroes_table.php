<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_heroes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('hero_id')->constrained('heroes')->cascadeOnDelete();
            $table->unsignedSmallInteger('level')->default(1);
            $table->string('ascension_code', 32)->nullable();
            $table->unsignedTinyInteger('ascension_value')->default(0);
            $table->unsignedTinyInteger('signature_item')->default(0);
            $table->unsignedTinyInteger('furniture')->default(0);
            $table->unsignedTinyInteger('engraving')->default(0);
            $table->unsignedInteger('power_rating')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'hero_id']);
            $table->index(['user_id', 'ascension_value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_heroes');
    }
};
