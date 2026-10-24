<?php

namespace App\Services\AiExport;

/**
 * Catálogo fechado de Objetivos do Export para IA. Cada Objetivo define o
 * Tipo de desafio (ou nenhum), os parâmetros exigidos e o prompt.
 */
class AiExportObjectives
{
    public const ARENA_OF_HEROES = 'arena_of_heroes';

    public const CAMPAIGN = 'campaign';

    public const DRAGON_TRIAL = 'dragon_trial';

    public const KINGS_TOWER = 'kings_tower';

    public const DREAM_REALM = 'dream_realm';

    /** Torres da Torre do Rei; as de facção só aceitam as facções listadas. */
    public const TOWERS = [
        'kings_tower' => ['name' => "King's Tower", 'factions' => []],
        'tower_of_light' => ['name' => 'Tower of Light', 'factions' => ['Lightbearers']],
        'brutal_citadel' => ['name' => 'Brutal Citadel', 'factions' => ['Maulers']],
        'world_tree' => ['name' => 'World Tree', 'factions' => ['Wilders']],
        'forsaken_necropolis' => ['name' => 'Forsaken Necropolis', 'factions' => ['Graveborns']],
        'celestial_sanctum' => ['name' => 'Celestial Sanctum', 'factions' => ['Celestials']],
        'infernal_fortress' => ['name' => 'Infernal Fortress', 'factions' => ['Hypogeans']],
    ];

    /**
     * `goal` descreve, a partir dos parâmetros, o que o modelo deve resolver;
     * `teams` diz quantas Equipes pedir; `unique_teams` liga a regra de
     * Equipes exclusivas; `notes` dá as regras extras do Tipo de desafio.
     *
     * @return array<string, array{label: string, challenge_type: ?string, teams: callable(array): int, rules: array<string, array>, goal: callable(array): string, unique_teams?: bool, notes?: callable(array): string[]}>
     */
    public static function all(): array
    {
        return [
            self::CAMPAIGN => [
                'label' => 'Campanha',
                'challenge_type' => 'campaign',
                // A 4ª fase de cada bloco é uma batalha de várias Equipes (até 5).
                'teams' => fn (array $params) => (int) ($params['teams'] ?? 1),
                'rules' => [
                    'stage' => ['required', 'string', 'regex:/^\d{1,3}-\d{1,2}$/'],
                    'teams' => ['nullable', 'integer', 'min:1', 'max:5'],
                ],
                'goal' => fn (array $params) => "passar da fase {$params['stage']} da Campanha (PvE)",
                'unique_teams' => true,
            ],
            self::DRAGON_TRIAL => [
                'label' => 'Provas da Forja Dracônica',
                'challenge_type' => 'dragon_trial',
                'teams' => fn (array $params) => (int) $params['stage'] <= 6 ? 2 : 3,
                'rules' => ['stage' => ['required', 'integer', 'min:3', 'max:12']],
                'goal' => fn (array $params) => "passar da fase {$params['stage']} das Provas da Forja Dracônica (Dragonforge Trials)",
                'unique_teams' => true,
                'notes' => fn () => [
                    'Só podem lutar heróis Draconis, Dimensionals e os heróis que estão no Cristal Ressonante; os demais não são permitidos.',
                    'Os Draconis lutam no nível 600 sem supressão; os outros são suprimidos pela Fúria do Dragão. Coleções, artefatos e pets não valem.',
                ],
            ],
            self::KINGS_TOWER => [
                'label' => 'Torre do Rei / torres de facção',
                'challenge_type' => 'kings_tower',
                'teams' => fn () => 1,
                'rules' => ['tower' => ['required', 'in:'.implode(',', array_keys(self::TOWERS))]],
                'goal' => fn (array $params) => $params['tower'] === self::KINGS_TOWER
                    ? 'subir andares da Torre do Rei (King\'s Tower, PvE)'
                    : 'subir andares da '.self::TOWERS[$params['tower']]['name'].' (torre de facção, PvE)',
                'notes' => fn (array $params) => self::TOWERS[$params['tower']]['factions']
                    ? ['Só heróis '.implode(' e ', self::TOWERS[$params['tower']]['factions']).' podem entrar nesta torre.']
                    : [],
            ],
            self::DREAM_REALM => [
                'label' => 'Chefe do Reino dos Sonhos',
                'challenge_type' => 'dream_realm',
                'teams' => fn () => 1,
                'rules' => [],
                'goal' => fn () => 'o máximo de dano no chefe do Reino dos Sonhos (Dream Realm, PvE)',
                'notes' => fn () => [
                    'É uma luta contra um único Chefe: o controle não tem efeito, então priorize dano, buffs e sustentação.',
                ],
            ],
            self::ARENA_OF_HEROES => [
                'label' => 'Arena dos Heróis',
                'challenge_type' => 'arena_of_heroes',
                'teams' => fn () => 1,
                'rules' => ['side' => ['required', 'in:defense,attack']],
                'goal' => fn (array $params) => $params['side'] === 'defense'
                    ? 'a defesa da Arena dos Heróis (PvP): uma Equipe que outros jogadores vão atacar e que precisa resistir sem o meu controle'
                    : 'o ataque na Arena dos Heróis (PvP): uma Equipe para vencer as defesas de outros jogadores',
            ],
        ];
    }

    public static function codes(): array
    {
        return array_keys(self::all());
    }

    public static function get(string $code): array
    {
        return self::all()[$code];
    }
}
