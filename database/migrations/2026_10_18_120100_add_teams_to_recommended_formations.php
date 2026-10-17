<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comps de várias equipes (Provas da Forja Dracônica: 2-3 equipes por fase).
     * `teams` = [[slugs da equipe 1], [equipe 2], ...]; `hero_slugs` continua com
     * a lista achatada (busca e exibição). Null = comp de uma equipe (legado).
     */
    public function up(): void
    {
        Schema::table('recommended_formations', function (Blueprint $table) {
            $table->json('teams')->nullable()->after('hero_slugs');
        });
    }

    public function down(): void
    {
        Schema::table('recommended_formations', function (Blueprint $table) {
            $table->dropColumn('teams');
        });
    }
};
