<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('challenge_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->string('name_pt');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('min_teams')->default(1);
            $table->unsignedTinyInteger('max_teams')->default(1);
            $table->boolean('is_pvp')->default(false);
            $table->boolean('teams_must_be_unique')->default(false);
            $table->json('scoring_profile')->nullable();
            $table->unsignedSmallInteger('display_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('challenge_types');
    }
};
