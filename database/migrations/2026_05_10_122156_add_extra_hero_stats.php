<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona 27 atributos extras a hero_stats. Todos como decimal(10,2)
 * para uniformidade — aceita tanto inteiros quanto decimais até
 * 99.999.999,99.
 */
return new class extends Migration
{
    private const COLUMNS = [
        // Básicos extras
        'vitality',
        'armor',
        'attack_speed',
        'healing',
        'recovery_per_second',
        'energy_recovery_rate',
        // Crítico
        'crit_block_rate',
        'crit_damage_resistance',
        // Combate físico/mágico
        'physical_piercing',
        'magic_piercing',
        'physical_resistance',
        'magic_resistance',
        'magic_efficiency',
        'magic_suppression',
        // Status
        'life_steal',
        'tenacity',
        'damage_resistance',
        'perception',
        // Especiais
        'proficiency',
        'dispel',
        'erosion',
        'aid',
        // Dracônico
        'draconic_amplification',
        'draconic_resistance',
        // Modos específicos
        'arena_damage_boost',
        'arena_damage_reduction',
        'trial_damage_boost',
    ];

    public function up(): void
    {
        Schema::table('hero_stats', function (Blueprint $table) {
            $previous = 'haste';
            foreach (self::COLUMNS as $col) {
                $table->decimal($col, 10, 2)->nullable()->after($previous);
                $previous = $col;
            }
        });
    }

    public function down(): void
    {
        Schema::table('hero_stats', function (Blueprint $table) {
            $table->dropColumn(self::COLUMNS);
        });
    }
};
