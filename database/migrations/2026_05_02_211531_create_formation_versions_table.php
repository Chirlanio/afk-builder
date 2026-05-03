<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formation_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('formation_id')->constrained('formations')->cascadeOnDelete();
            $table->json('snapshot');
            $table->timestamp('created_at')->nullable();

            $table->index(['formation_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formation_versions');
    }
};
