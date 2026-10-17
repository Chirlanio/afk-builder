<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * 1. Campanha: a cada 4 fases, a 4ª é uma batalha de vários grupos (até 5
     *    equipes, heróis não se repetem entre elas). O tipo aceitava só 1 equipe.
     * 2. dragon_trial era um palpite da Fase 9 ("1 equipe contra alvo dracônico").
     *    O modo real é o Dragonforge Trials (Provas da Forja Dracônica): 2 equipes
     *    nas fases 3-6 e 3 nas fases 7-12, heróis exclusivos por equipe
     *    (afk.guide/dragonforge-trials-guide). Mantém o code para não quebrar
     *    formações existentes.
     * 3. Cristal Ressonante: teto do jogo passou de 501 (há contas em 1040) —
     *    o jogador informa o teto e as posições ocupadas que o jogo mostra.
     */
    public function up(): void
    {
        DB::table('challenge_types')->where('code', 'campaign')->update([
            'description' => 'Modo história principal. A cada 4 fases, a 4ª é uma batalha de vários grupos (até 5 equipes, heróis não se repetem); as demais usam 1 equipe.',
            'max_teams' => 5,
            'teams_must_be_unique' => true,
        ]);

        DB::table('challenge_types')->where('code', 'dragon_trial')->update([
            'name' => 'Dragonforge Trials',
            'name_pt' => 'Provas da Forja Dracônica',
            'description' => 'Modo do Refúgio do Dragão: 2 equipes nas fases 3-6 e 3 nas fases 7-12, heróis exclusivos por equipe. Heróis Dracônicos lutam no nível 600 sem supressão; os demais são suprimidos pela Fúria do Dragão (o Chifre de Dragão reduz). Coleções, artefatos e pets não valem.',
            'min_teams' => 2,
            'max_teams' => 3,
            'teams_must_be_unique' => true,
            'scoring_profile' => json_encode([
                'faction_bonus_weight' => 0.8,
                'class_balance_weight' => 0.8,
                'synergy_weight' => 1.0,
                'power_weight' => 0.8,
                'tier_rating_weight' => 1.6,
                'partition_optimization' => true,
            ]),
        ]);

        Schema::table('user_resonating_crystals', function (Blueprint $table) {
            $table->unsignedSmallInteger('level_cap')->nullable()->after('level');
            $table->unsignedSmallInteger('slots_occupied')->nullable()->after('slots_unlocked');
        });
    }

    public function down(): void
    {
        Schema::table('user_resonating_crystals', function (Blueprint $table) {
            $table->dropColumn(['level_cap', 'slots_occupied']);
        });

        DB::table('challenge_types')->where('code', 'campaign')->update([
            'description' => 'Modo história principal — 1 equipe contra ondas de inimigos.',
            'max_teams' => 1,
            'teams_must_be_unique' => false,
        ]);
    }
};
