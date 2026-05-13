<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scoring_weight_presets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 60);
            $table->foreignId('challenge_type_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();
            $table->json('weights');
            $table->timestamps();

            $table->unique(['user_id', 'name']);
            $table->index(['user_id', 'challenge_type_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scoring_weight_presets');
    }
};
