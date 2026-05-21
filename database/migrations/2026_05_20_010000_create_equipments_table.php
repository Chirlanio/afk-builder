<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catálogo de Equipamentos Míticos do AFK Arena.
 *
 * Os equipamentos são items NOMEADOS (Gaius/Ancelot/Místico) com slot, material,
 * faction associada (pra bônus de 30%) e quality_tier (mythic → mythic_t4).
 * Conceito DIFERENTE do tier genérico T1-T6 que já existe no UserHero (que
 * representa raridade genérica de equipamento padrão sem nome).
 *
 * Upgrade levels (0-5) escalam linearmente em +10% do valor base por nível,
 * exceto mythic_t3 e mythic_t4 que não têm upgrades (max_upgrade_level = 0).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('equipments', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');           // EN (placeholder pra futura sync)
            $table->string('name_pt');        // PT-BR ("Grevas de Gaius")
            $table->string('set_code', 32)->index();   // 'gaius', 'ancelot', 'mystic'
            $table->string('slot', 16);                // 'boots', 'chest', 'helmet', 'weapon'
            $table->string('material', 32);            // 'plate', 'leather', 'padded', 'heavy_weapon', 'light_weapon', 'magic_staff'
            $table->string('hero_type', 16);           // 'strength', 'agility', 'intelligence'
            $table->foreignId('faction_id')->constrained('factions')->cascadeOnDelete();
            $table->string('quality_tier', 16)->default('mythic')->index(); // 'mythic', 'mythic_t1', 'mythic_t2', 'mythic_t3', 'mythic_t4'

            // Força (Power) base, exibida pelo jogo. Escala em +10% por upgrade level.
            $table->unsignedInteger('power_rating');

            // Stats do equipamento. Convenção: sufixo `_pct` indica valor percentual
            // (ex.: physical_resistance_pct = 5 → 5%), sem sufixo é valor flat.
            // Ex.: {"hp": 36500, "accuracy": 60, "haste": 5, "physical_resistance_pct": 5}
            $table->json('stats');

            // Set bonuses (mythic_t3+ recebem buffs adicionais quando set completo).
            // Mesma convenção: `_pct` percentual, sem sufixo flat. Null pra tiers inferiores.
            // Ex.: {"hp_pct": 0.7, "hp_flat": 1392830, "attack_pct": 4, "attack_flat": 582446}
            $table->json('set_bonuses')->nullable();

            // 5 pra mythic/T1/T2, 0 pra T3/T4 (não tem upgrade segundo o user)
            $table->unsignedTinyInteger('max_upgrade_level')->default(5);

            // Multiplier % por upgrade level (default 10%). Cada upgrade adiciona
            // base × (this/100) ao stat e ao power.
            $table->unsignedTinyInteger('upgrade_percent_per_level')->default(10);

            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['set_code', 'slot']);
            $table->index(['hero_type', 'slot']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('equipments');
    }
};
