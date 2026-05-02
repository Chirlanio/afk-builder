<?php

namespace Database\Seeders;

use App\Models\Faction;
use Illuminate\Database\Seeder;

class FactionSeeder extends Seeder
{
    public function run(): void
    {
        $factions = [
            [
                'code' => 'lightbearer',
                'name' => 'Lightbearers',
                'name_pt' => 'Portadores da Luz',
                'color_hex' => '#F4D03F',
                'advantage_over' => 'mauler',
                'special_behavior' => null,
                'display_order' => 1,
            ],
            [
                'code' => 'mauler',
                'name' => 'Maulers',
                'name_pt' => 'Perversos',
                'color_hex' => '#E67E22',
                'advantage_over' => 'wilder',
                'special_behavior' => null,
                'display_order' => 2,
            ],
            [
                'code' => 'wilder',
                'name' => 'Wilders',
                'name_pt' => 'Selvagens',
                'color_hex' => '#27AE60',
                'advantage_over' => 'graveborn',
                'special_behavior' => null,
                'display_order' => 3,
            ],
            [
                'code' => 'graveborn',
                'name' => 'Graveborns',
                'name_pt' => 'Tumulares',
                'color_hex' => '#8E44AD',
                'advantage_over' => 'lightbearer',
                'special_behavior' => null,
                'display_order' => 4,
            ],
            [
                'code' => 'celestial',
                'name' => 'Celestials',
                'name_pt' => 'Celestiais',
                'color_hex' => '#FFFFFF',
                'advantage_over' => null,
                'special_behavior' => [
                    'acts_as_faction_wildcard' => true,
                    'advantage_over' => 'hypogean',
                    'note' => 'Conta como qualquer facção para bônus 3+2 e 5-stack; tem +25% vs Hypogeans.',
                ],
                'display_order' => 5,
            ],
            [
                'code' => 'hypogean',
                'name' => 'Hypogeans',
                'name_pt' => 'Hipogeus',
                'color_hex' => '#1A1A1A',
                'advantage_over' => null,
                'special_behavior' => [
                    'advantage_over' => 'celestial',
                    'team_buffs_by_count' => [
                        '1' => '+30% Defense',
                        '2' => '+25% Energy on hit',
                        '3' => '+15% Crit Rating',
                        '4' => '+30% Crit Damage',
                        '5' => '+15 Haste',
                    ],
                ],
                'display_order' => 6,
            ],
            [
                'code' => 'dimensional',
                'name' => 'Dimensionals',
                'name_pt' => 'Dimensionais',
                'color_hex' => '#3498DB',
                'advantage_over' => null,
                'special_behavior' => [
                    'no_faction_interaction' => true,
                    'note' => 'Não recebe nem causa bônus de vantagem de facção.',
                ],
                'display_order' => 7,
            ],
            [
                'code' => 'draconis',
                'name' => 'Draconis',
                'name_pt' => 'Draconis',
                'color_hex' => '#C0392B',
                'advantage_over' => 'draconis',
                'special_behavior' => [
                    'scale_manifest' => true,
                    'advantage_self' => true,
                    'note' => 'Vantagem +25% sobre a própria facção; mecânica única Scale Manifest.',
                ],
                'display_order' => 8,
            ],
        ];

        foreach ($factions as $data) {
            $advantageCode = $data['advantage_over'];
            unset($data['advantage_over']);

            Faction::updateOrCreate(
                ['code' => $data['code']],
                $data + ['advantage_over_faction_id' => null]
            );
        }

        foreach ($factions as $data) {
            if ($data['advantage_over'] ?? null) {
                $advantage = Faction::where('code', $data['advantage_over'])->first();
                if ($advantage) {
                    Faction::where('code', $data['code'])->update([
                        'advantage_over_faction_id' => $advantage->id,
                    ]);
                }
            }
        }
    }
}
