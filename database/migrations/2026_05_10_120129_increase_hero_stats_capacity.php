<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stats de endgame ascended chegam a centenas de milhões (HP da Shemira ~664M).
 * O `unsignedInteger` (max ~4.29B) ainda comportaria, mas levantar `hp` para
 * `unsignedBigInteger` dá folga futura sem custo prático.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hero_stats', function (Blueprint $table) {
            $table->unsignedBigInteger('hp')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('hero_stats', function (Blueprint $table) {
            $table->unsignedInteger('hp')->nullable()->change();
        });
    }
};
