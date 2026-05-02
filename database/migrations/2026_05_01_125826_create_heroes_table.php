<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heroes', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 128)->unique();
            $table->string('name');
            $table->string('title')->nullable();
            $table->foreignId('faction_id')->constrained('factions')->restrictOnDelete();
            $table->foreignId('hero_class_id')->constrained('hero_classes')->restrictOnDelete();
            $table->foreignId('tier_id')->constrained('tiers')->restrictOnDelete();
            $table->enum('gender', ['male', 'female', 'other'])->nullable();
            $table->string('role_label')->nullable();
            $table->string('special_mechanic')->nullable();
            $table->string('portrait_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->string('source_url')->nullable();
            $table->date('released_at')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('pending_review')->default(false);
            $table->timestamps();

            $table->index('faction_id');
            $table->index('hero_class_id');
            $table->index('tier_id');
            $table->index(['is_active', 'pending_review']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heroes');
    }
};
