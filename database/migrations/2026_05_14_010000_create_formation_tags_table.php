<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formation_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 30);
            // Cor pré-definida (uma das constantes do model — string 16 cobre "emerald", "rose", etc).
            $table->string('color', 16)->default('amber');
            $table->timestamps();

            $table->unique(['user_id', 'name']);
        });

        Schema::create('formation_tag_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('formation_tag_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['formation_id', 'formation_tag_id']);
            $table->index('formation_tag_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formation_tag_assignments');
        Schema::dropIfExists('formation_tags');
    }
};
