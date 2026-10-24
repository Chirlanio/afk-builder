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

    /**
     * `goal` descreve, a partir dos parâmetros, o que o modelo deve resolver;
     * `teams` diz quantas Equipes pedir; `unique_teams` liga a regra de
     * Equipes exclusivas; `notes` são regras extras do Tipo de desafio.
     *
     * @return array<string, array{label: string, challenge_type: ?string, teams: callable(array): int, rules: array<string, array>, goal: callable(array): string, unique_teams?: bool, notes?: string[]}>
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
                'notes' => [
                    'Só podem lutar heróis Draconis, Dimensionals e os heróis que estão no Cristal Ressonante; os demais não são permitidos.',
                    'Os Draconis lutam no nível 600 sem supressão; os outros são suprimidos pela Fúria do Dragão. Coleções, artefatos e pets não valem.',
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
