<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('formations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('challenge_type_id')->constrained('challenge_types')->restrictOnDelete();
            $table->string('name');
            $table->enum('mode', ['real', 'target'])->default('real');
            $table->unsignedTinyInteger('teams_count')->default(1);
            $table->text('notes')->nullable();
            $table->unsignedInteger('total_score')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'mode']);
            $table->index('challenge_type_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('formations');
    }
};
