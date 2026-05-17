<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_resources', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            // Emblemas SI (alinhados com SignatureItemCost::EMBLEM_*)
            $table->unsignedInteger('primordial_emblems')->default(0);
            $table->unsignedInteger('amplifying_emblems')->default(0);
            $table->unsignedInteger('faction_emblems')->default(0);

            // Pools simplificados (jogo tem variantes por tipo, mas mantemos 1 pool
            // pra reduzir fricção de input — user soma o que tem)
            $table->unsignedInteger('mythic_furniture')->default(0);
            $table->unsignedInteger('engraving_scrolls')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_resources');
    }
};
