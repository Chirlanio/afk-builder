<?php

namespace Database\Seeders;

use App\Models\ChallengeType;
use Illuminate\Database\Seeder;

class ChallengeTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            [
                'code' => 'campaign',
                'name' => 'Campaign',
                'name_pt' => 'Campanha',
                'description' => 'Modo história principal — 1 equipe contra ondas de inimigos.',
                'min_teams' => 1,
                'max_teams' => 1,
                'is_pvp' => false,
                'teams_must_be_unique' => false,
                'scoring_profile' => [
                    'faction_bonus_weight' => 1.0,
                    'class_balance_weight' => 1.0,
                    'synergy_weight' => 1.0,
                    'power_weight' => 1.0,
                ],
                'display_order' => 10,
            ],
            [
                'code' => 'kings_tower',
                'name' => "King's Tower",
                'name_pt' => 'Torre do Rei',
                'description' => 'Torre principal — 1 equipe escalando andares.',
                'min_teams' => 1,
                'max_teams' => 1,
                'is_pvp' => false,
                'teams_must_be_unique' => false,
                'scoring_profile' => [
                    'faction_bonus_weight' => 1.0,
                    'class_balance_weight' => 1.0,
                    'synergy_weight' => 1.0,
                    'power_weight' => 1.2,
                ],
                'display_order' => 20,
            ],
            [
                'code' => 'arena_of_heroes',
                'name' => 'Arena of Heroes',
                'name_pt' => 'Arena dos Heróis',
                'description' => 'PvP individual — 1 equipe contra outro jogador.',
                'min_teams' => 1,
                'max_teams' => 1,
                'is_pvp' => true,
                'teams_must_be_unique' => false,
                'scoring_profile' => [
                    'faction_bonus_weight' => 1.2,
                    'class_balance_weight' => 0.8,
                    'synergy_weight' => 1.3,
                    'power_weight' => 1.0,
                    'pvp' => true,
                ],
                'display_order' => 30,
            ],
            [
                'code' => 'legends_championship',
                'name' => 'Legends Championship',
                'name_pt' => 'Campeonato das Lendas',
                'description' => 'PvP de 7 equipes distintas (heróis não podem se repetir entre as equipes).',
                'min_teams' => 7,
                'max_teams' => 7,
                'is_pvp' => true,
                'teams_must_be_unique' => true,
                'scoring_profile' => [
                    'faction_bonus_weight' => 1.3,
                    'class_balance_weight' => 1.0,
                    'synergy_weight' => 1.2,
                    'power_weight' => 1.0,
                    'pvp' => true,
                    'partition_optimization' => true,
                ],
                'display_order' => 40,
            ],
            [
                'code' => 'heroes_of_esperia',
                'name' => 'Heroes of Esperia',
                'name_pt' => 'Heróis de Esperia',
                'description' => 'PvP cross-server — formato similar ao Legends Championship com múltiplas equipes.',
                'min_teams' => 5,
                'max_teams' => 7,
                'is_pvp' => true,
                'teams_must_be_unique' => true,
                'scoring_profile' => [
                    'faction_bonus_weight' => 1.3,
                    'class_balance_weight' => 1.0,
                    'synergy_weight' => 1.2,
                    'power_weight' => 1.0,
                    'pvp' => true,
                    'partition_optimization' => true,
                ],
                'display_order' => 50,
            ],
            [
                'code' => 'dream_realm',
                'name' => 'Dream Realm',
                'name_pt' => 'Reino dos Sonhos',
                'description' => 'Boss PvE rotativo — 1 equipe focada em DPS contra um chefe.',
                'min_teams' => 1,
                'max_teams' => 1,
                'is_pvp' => false,
                'teams_must_be_unique' => false,
                'scoring_profile' => [
                    'faction_bonus_weight' => 0.7,
                    'class_balance_weight' => 0.6,
                    'synergy_weight' => 1.5,
                    'power_weight' => 1.5,
                    'dps_focus' => true,
                ],
                'display_order' => 60,
            ],
            [
                'code' => 'cursed_realm',
                'name' => 'Cursed Realm',
                'name_pt' => 'Reino Amaldiçoado',
                'description' => 'PvE com modificadores especiais — 1 equipe.',
                'min_teams' => 1,
                'max_teams' => 1,
                'is_pvp' => false,
                'teams_must_be_unique' => false,
                'scoring_profile' => [
                    'faction_bonus_weight' => 1.0,
                    'class_balance_weight' => 1.0,
                    'synergy_weight' => 1.2,
                    'power_weight' => 1.0,
                ],
                'display_order' => 70,
            ],
            [
                'code' => 'abyssal_expedition',
                'name' => 'Abyssal Expedition',
                'name_pt' => 'Expedição Abissal',
                'description' => 'Modo de guilda PvE com múltiplas equipes em batalhas distintas.',
                'min_teams' => 3,
                'max_teams' => 7,
                'is_pvp' => false,
                'teams_must_be_unique' => true,
                'scoring_profile' => [
                    'faction_bonus_weight' => 1.1,
                    'class_balance_weight' => 1.0,
                    'synergy_weight' => 1.0,
                    'power_weight' => 1.1,
                    'partition_optimization' => true,
                ],
                'display_order' => 80,
            ],
            [
                'code' => 'arcane_labyrinth',
                'name' => 'Arcane Labyrinth',
                'name_pt' => 'Labirinto Arcano',
                'description' => 'Roguelike — equipes variáveis ao longo dos andares com base num deck.',
                'min_teams' => 1,
                'max_teams' => 5,
                'is_pvp' => false,
                'teams_must_be_unique' => true,
                'scoring_profile' => [
                    'faction_bonus_weight' => 1.0,
                    'class_balance_weight' => 1.1,
                    'synergy_weight' => 1.1,
                    'power_weight' => 0.9,
                    'partition_optimization' => true,
                ],
                'display_order' => 90,
            ],
            [
                'code' => 'bountiful_trials',
                'name' => 'Bountiful Trials',
                'name_pt' => 'Provas Generosas',
                'description' => 'Trials de heróis específicos — 1 equipe normalmente com herói focal.',
                'min_teams' => 1,
                'max_teams' => 1,
                'is_pvp' => false,
                'teams_must_be_unique' => false,
                'scoring_profile' => [
                    'faction_bonus_weight' => 0.9,
                    'class_balance_weight' => 1.0,
                    'synergy_weight' => 1.3,
                    'power_weight' => 1.0,
                ],
                'display_order' => 100,
            ],
        ];

        foreach ($types as $data) {
            ChallengeType::updateOrCreate(['code' => $data['code']], $data);
        }
    }
}
