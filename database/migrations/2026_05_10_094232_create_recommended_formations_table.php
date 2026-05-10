<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recommended_formations', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 128)->unique();
            $table->string('name');
            $table->string('mode', 32);
            $table->text('description')->nullable();
            $table->json('hero_slugs');
            $table->string('source_url', 500)->nullable();
            $table->string('source_label', 128)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['mode', 'is_active', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommended_formations');
    }
};
