<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->string('source', 64);
            $table->enum('status', ['running', 'success', 'failed', 'partial'])->default('running');
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('heroes_seen')->default(0);
            $table->unsignedInteger('heroes_created')->default(0);
            $table->unsignedInteger('heroes_updated')->default(0);
            $table->json('new_hero_slugs')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['source', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};
