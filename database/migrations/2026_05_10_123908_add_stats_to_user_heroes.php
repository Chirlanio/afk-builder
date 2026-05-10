<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adiciona os 35 stats ao user_heroes para que cada user possa cadastrar
 * os valores reais do seu próprio herói (após investimento de SI/furniture/
 * equipamento), separados dos stats "base" cadastrados em hero_stats.
 *
 * Mesmo schema que hero_stats:
 *  - hp / attack / defense → unsigned big/int
 *  - crit_rate / crit_amp / accuracy / dodge / haste → decimal(8,2)
 *  - 27 extras → decimal(10,2)
 */
return new class extends Migration
{
    private const PERCENT_CORE = ['crit_rate', 'crit_amp', 'accuracy', 'dodge', 'haste'];

    private const EXTRAS = [
        'vitality', 'armor', 'attack_speed', 'healing', 'recovery_per_second', 'energy_recovery_rate',
        'crit_block_rate', 'crit_damage_resistance',
        'physical_piercing', 'magic_piercing', 'physical_resistance', 'magic_resistance', 'magic_efficiency', 'magic_suppression',
        'life_steal', 'tenacity', 'damage_resistance', 'perception',
        'proficiency', 'dispel', 'erosion', 'aid',
        'draconic_amplification', 'draconic_resistance',
        'arena_damage_boost', 'arena_damage_reduction', 'trial_damage_boost',
    ];

    public function up(): void
    {
        Schema::table('user_heroes', function (Blueprint $table) {
            $table->unsignedBigInteger('hp')->nullable()->after('notes');
            $table->unsignedInteger('attack')->nullable()->after('hp');
            $table->unsignedInteger('defense')->nullable()->after('attack');
            $previous = 'defense';
            foreach (self::PERCENT_CORE as $col) {
                $table->decimal($col, 8, 2)->nullable()->after($previous);
                $previous = $col;
            }
            foreach (self::EXTRAS as $col) {
                $table->decimal($col, 10, 2)->nullable()->after($previous);
                $previous = $col;
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_heroes', function (Blueprint $table) {
            $table->dropColumn(array_merge(
                ['hp', 'attack', 'defense'],
                self::PERCENT_CORE,
                self::EXTRAS,
            ));
        });
    }
};
