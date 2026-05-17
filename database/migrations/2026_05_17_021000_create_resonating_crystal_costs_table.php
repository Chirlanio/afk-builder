<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resonating_crystal_costs', function (Blueprint $table) {
            // Nível resultante (PK direto — sem incrementing). Subir DE nivel-1 PARA nivel custa esses recursos.
            $table->unsignedSmallInteger('level')->primary();
            $table->unsignedBigInteger('gold_cost');
            $table->unsignedBigInteger('hero_exp_cost');
            $table->unsignedInteger('dust_cost'); // essência de herói total no ciclo de 10 fortalecimentos
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resonating_crystal_costs');
    }
};
