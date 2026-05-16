<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('beasts', function (Blueprint $table) {
            // JSON columns por escopo. Cada um é um array assoc { "hp": 17.59, "attack": 10.24, ... }.
            // Valores são percentuais (já no level máximo / 36). Escalonamento por level é feito no PHP.
            $table->json('buffs_strength')->nullable()->after('agility_buff');
            $table->json('buffs_intelligence')->nullable()->after('buffs_strength');
            $table->json('buffs_agility')->nullable()->after('buffs_intelligence');
            $table->json('buffs_team')->nullable()->after('buffs_agility');
        });

        // Dropa as colunas antigas em uma segunda alter (mais portável que combinar add+drop).
        Schema::table('beasts', function (Blueprint $table) {
            $table->dropColumn(['strength_buff', 'intelligence_buff', 'agility_buff']);
        });

        Schema::table('formation_teams', function (Blueprint $table) {
            // Level da besta no team (1-36). Default 36 = besta no max.
            // Aplicado como multiplicador (level/36) sobre os buffs do scope.
            $table->unsignedTinyInteger('beast_level')->default(36)->after('beast_id');
        });
    }

    public function down(): void
    {
        Schema::table('formation_teams', function (Blueprint $table) {
            $table->dropColumn('beast_level');
        });

        Schema::table('beasts', function (Blueprint $table) {
            $table->unsignedSmallInteger('strength_buff')->default(0);
            $table->unsignedSmallInteger('intelligence_buff')->default(0);
            $table->unsignedSmallInteger('agility_buff')->default(0);
        });

        Schema::table('beasts', function (Blueprint $table) {
            $table->dropColumn(['buffs_strength', 'buffs_intelligence', 'buffs_agility', 'buffs_team']);
        });
    }
};
