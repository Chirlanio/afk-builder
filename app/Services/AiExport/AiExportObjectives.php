<?php

namespace App\Services\AiExport;

/**
 * Catálogo fechado de Objetivos do Export para IA. Cada Objetivo define o
 * Tipo de desafio (ou nenhum), os parâmetros exigidos e o prompt.
 */
class AiExportObjectives
{
    public const ARENA_OF_HEROES = 'arena_of_heroes';

    /**
     * `goal` descreve, a partir dos parâmetros, o que o modelo deve resolver.
     *
     * @return array<string, array{label: string, challenge_type: ?string, teams: int, rules: array<string, array>, goal: callable(array): string}>
     */
    public static function all(): array
    {
        return [
            self::ARENA_OF_HEROES => [
                'label' => 'Arena dos Heróis',
                'challenge_type' => 'arena_of_heroes',
                'teams' => 1,
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
