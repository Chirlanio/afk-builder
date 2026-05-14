<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roster_power_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('snapshot_date');
            $table->unsignedBigInteger('total_power');
            $table->unsignedInteger('hero_count');
            $table->foreignId('top_hero_id')->nullable()->constrained('heroes')->nullOnDelete();
            $table->unsignedBigInteger('top_hero_power')->nullable();
            $table->timestamp('created_at')->nullable();

            // 1 snapshot por user por dia. Upsert no comando reusa essa restrição.
            $table->unique(['user_id', 'snapshot_date']);
            $table->index(['user_id', 'snapshot_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roster_power_snapshots');
    }
};
