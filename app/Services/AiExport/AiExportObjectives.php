<?php

namespace App\Services\AiExport;

use App\Enums\Planning;
use App\Models\HeroRating;

/**
 * Catálogo fechado de Objetivos do Export para IA. É a única fonte: o backend
 * valida por ele e o modal o recebe pela rota de Objetivos.
 */
class AiExportObjectives
{
    public const ARENA_OF_HEROES = 'arena_of_heroes';

    public const CAMPAIGN = 'campaign';

    public const DRAGON_TRIAL = 'dragon_trial';

    public const KINGS_TOWER = 'kings_tower';

    public const DREAM_REALM = 'dream_realm';

    public const NEXT_INVESTMENT = 'next_investment';

    /** Torres da Torre do Rei; as de facção só aceitam a facção listada. */
    public const TOWERS = [
        'kings_tower' => ['name' => "King's Tower", 'label' => 'Torre do Rei', 'factions' => []],
        'tower_of_light' => ['name' => 'Tower of Light', 'label' => 'Tower of Light (Lightbearers)', 'factions' => ['Lightbearers']],
        'brutal_citadel' => ['name' => 'Brutal Citadel', 'label' => 'Brutal Citadel (Maulers)', 'factions' => ['Maulers']],
        'world_tree' => ['name' => 'World Tree', 'label' => 'World Tree (Wilders)', 'factions' => ['Wilders']],
        'forsaken_necropolis' => ['name' => 'Forsaken Necropolis', 'label' => 'Forsaken Necropolis (Graveborns)', 'factions' => ['Graveborns']],
        'celestial_sanctum' => ['name' => 'Celestial Sanctum', 'label' => 'Celestial Sanctum (Celestials)', 'factions' => ['Celestials']],
        'infernal_fortress' => ['name' => 'Infernal Fortress', 'label' => 'Infernal Fortress (Hypogeans)', 'factions' => ['Hypogeans']],
    ];

    /**
     * @return array<string, AiExportObjective>
     */
    public static function all(): array
    {
        $objectives = [
            new AiExportObjective(
                code: self::CAMPAIGN,
                label: 'Campanha',
                challengeType: 'campaign',
                fields: [
                    AiExportField::text('stage', 'Fase', '^\d{1,3}-\d{1,2}$', 'ex.: 45-60'),
                    // A 4ª fase de cada bloco é uma batalha de várias Equipes (até 5).
                    AiExportField::select('teams', 'Equipes (fase de várias Equipes)', self::range(1, 5), default: '1'),
                ],
                goal: fn (array $params) => "montar a Formação para passar da fase {$params['stage']} da Campanha (PvE)",
                teams: fn (array $params) => (int) ($params['teams'] ?? 1),
                uniqueTeams: true,
            ),
            new AiExportObjective(
                code: self::DRAGON_TRIAL,
                label: 'Provas da Forja Dracônica',
                challengeType: 'dragon_trial',
                fields: [
                    AiExportField::select('stage', 'Fase', array_map(
                        fn (string $stage) => $stage.' ('.self::dragonTrialTeams((int) $stage).' equipes)',
                        self::range(3, 12),
                    )),
                ],
                goal: fn (array $params) => "montar a Formação para passar da fase {$params['stage']} das Provas da Forja Dracônica (Dragonforge Trials)",
                teams: fn (array $params) => self::dragonTrialTeams((int) $params['stage']),
                uniqueTeams: true,
                notes: fn () => [
                    'Só podem lutar heróis Draconis, Dimensionals e os heróis que estão no Cristal Ressonante; os demais não são permitidos.',
                    'Os Draconis lutam no nível 600 sem supressão; os outros são suprimidos pela Fúria do Dragão. Coleções, artefatos e pets não valem.',
                ],
            ),
            new AiExportObjective(
                code: self::KINGS_TOWER,
                label: 'Torre do Rei / torres de facção',
                challengeType: 'kings_tower',
                fields: [
                    AiExportField::select('tower', 'Torre', array_map(fn (array $tower) => $tower['label'], self::TOWERS)),
                ],
                goal: fn (array $params) => $params['tower'] === self::KINGS_TOWER
                    ? "montar uma Equipe para subir andares da Torre do Rei (King's Tower, PvE)"
                    : 'montar uma Equipe para subir andares da '.self::TOWERS[$params['tower']]['name'].' (torre de facção, PvE)',
                notes: fn (array $params) => self::TOWERS[$params['tower']]['factions']
                    ? ['Só heróis '.implode(' e ', self::TOWERS[$params['tower']]['factions']).' podem entrar nesta torre.']
                    : [],
            ),
            new AiExportObjective(
                code: self::DREAM_REALM,
                label: 'Chefe do Reino dos Sonhos',
                challengeType: 'dream_realm',
                fields: [],
                goal: fn () => 'montar uma Equipe para tirar o máximo de dano do Chefe do Reino dos Sonhos (Dream Realm, PvE)',
                notes: fn () => [
                    'É uma luta contra um único Chefe: o controle não tem efeito, então priorize dano, buffs e sustentação.',
                ],
            ),
            new AiExportObjective(
                code: self::NEXT_INVESTMENT,
                label: 'Em quem investir a seguir',
                challengeType: null,
                fields: [],
                goal: fn () => 'saber em quem investir a seguir para fortalecer a minha Conta de jogo',
                formation: false,
                context: HeroRating::MODE_OVERALL,
            ),
            new AiExportObjective(
                code: self::ARENA_OF_HEROES,
                label: 'Arena dos Heróis',
                challengeType: 'arena_of_heroes',
                fields: [
                    AiExportField::select('side', 'Lado', ['defense' => 'Defesa', 'attack' => 'Ataque']),
                ],
                goal: fn (array $params) => $params['side'] === 'defense'
                    ? 'montar a defesa da Arena dos Heróis (PvP): uma Equipe que outros jogadores vão atacar e que precisa resistir sem o meu controle'
                    : 'montar o ataque na Arena dos Heróis (PvP): uma Equipe para vencer as defesas de outros jogadores',
            ),
        ];

        return collect($objectives)->keyBy(fn (AiExportObjective $objective) => $objective->code)->all();
    }

    /**
     * O que uma Formação já diz ao Export para IA: o Objetivo do seu Tipo de
     * desafio (null quando não há), o Planejamento e, na Campanha, quantas
     * Equipes. Fase, torre e lado não ficam na Formação: o jogador escolhe.
     *
     * @return array{objective: ?string, planning: string, params: array<string, string>}
     */
    public static function prefillFor(string $challengeCode, Planning $planning, int $teamsCount): array
    {
        $objective = collect(self::all())
            ->first(fn (AiExportObjective $objective) => $objective->challengeType === $challengeCode)
            ?->code;

        return [
            'objective' => $objective,
            'planning' => $planning->value,
            'params' => $objective === self::CAMPAIGN ? ['teams' => (string) $teamsCount] : [],
        ];
    }

    /**
     * @return string[]
     */
    public static function codes(): array
    {
        return array_keys(self::all());
    }

    public static function get(string $code): AiExportObjective
    {
        return self::all()[$code];
    }

    /** Provas da Forja Dracônica: 2 Equipes nas fases 3-6, 3 nas fases 7-12. */
    private static function dragonTrialTeams(int $stage): int
    {
        return $stage <= 6 ? 2 : 3;
    }

    /**
     * @return array<string, string> "n" => "n"
     */
    private static function range(int $from, int $to): array
    {
        $values = array_map('strval', range($from, $to));

        return array_combine($values, $values);
    }
}
