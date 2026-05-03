<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artifacts', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 128)->unique();
            $table->string('name');
            $table->enum('kind', ['dura', 'class']);
            $table->foreignId('hero_class_id')->nullable()->constrained('hero_classes')->nullOnDelete();
            $table->text('description')->nullable();
            $table->json('attributes')->nullable();
            $table->json('powers')->nullable();
            $table->string('obtained_in')->nullable();
            $table->string('image_filename')->nullable();
            $table->string('portrait_url')->nullable();
            $table->string('source_url')->nullable();
            $table->unsignedTinyInteger('max_stars')->default(3);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('kind');
            $table->index('hero_class_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artifacts');
    }
};
