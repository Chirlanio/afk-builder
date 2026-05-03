<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('collections', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 128)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('hp_bonus')->default(0);
            $table->unsignedInteger('attack_bonus')->default(0);
            $table->unsignedInteger('defense_bonus')->default(0);
            $table->string('portrait_url')->nullable();
            $table->string('source_url')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('is_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collections');
    }
};
