<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('guides', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 128)->unique();
            $table->string('title');
            $table->string('category', 32); // mecanicas, modos, eventos
            $table->string('summary')->nullable();
            $table->longText('content')->nullable();
            $table->string('source_url', 500)->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['category', 'display_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('guides');
    }
};
