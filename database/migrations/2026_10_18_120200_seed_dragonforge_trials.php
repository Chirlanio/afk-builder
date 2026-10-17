<?php

use Database\Seeders\DragonforgeTrialsSeeder;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Formações e ratings das Provas da Forja Dracônica. Roda o seeder aqui
     * porque o deploy na Hostinger só executa `migrate` (sem SSH para db:seed).
     * Idempotente — pode rodar de novo com `db:seed --class=DragonforgeTrialsSeeder`.
     */
    public function up(): void
    {
        (new DragonforgeTrialsSeeder)->run();
    }

    public function down(): void
    {
        // Dados de catálogo: ficam (o seeder é idempotente).
    }
};
