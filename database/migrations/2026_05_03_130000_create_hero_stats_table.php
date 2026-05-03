<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hero_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('hero_id')->unique()->constrained('heroes')->cascadeOnDelete();
            $table->unsignedInteger('hp')->nullable();
            $table->unsignedInteger('attack')->nullable();
            $table->unsignedInteger('defense')->nullable();
            $table->unsignedSmallInteger('crit_rate')->nullable();
            $table->unsignedSmallInteger('crit_amp')->nullable();
            $table->unsignedSmallInteger('accuracy')->nullable();
            $table->unsignedSmallInteger('dodge')->nullable();
            $table->unsignedSmallInteger('haste')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hero_stats');
    }
};
