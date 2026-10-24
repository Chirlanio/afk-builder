<?php

use Database\Seeders\HeroCounterSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Lista inicial de Counters com fonte citada. Roda o seeder aqui porque o
     * deploy na Hostinger só executa `migrate` (sem SSH para db:seed).
     * Idempotente — pode rodar de novo com `db:seed --class=HeroCounterSeeder`.
     */
    public function up(): void
    {
        (new HeroCounterSeeder)->run();
    }

    public function down(): void
    {
        // Dados de catálogo: ficam (o seeder é idempotente).
    }
};
