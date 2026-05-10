<?php

namespace Database\Seeders;

use App\Models\RecommendedFormation;
use Illuminate\Database\Seeder;

/**
 * Comps curados manualmente do afk.guide via captura humana com a extensão
 * Claude Chrome. Heroes "Awakened X" mapeiam para slug do X regular (a flag
 * is_awakened distingue no banco). hero_slugs: primeiros [core_count]
 * heróis são core, restante é flex (substituições viáveis).
 */
class RecommendedFormationsSeeder extends Seeder
{
    public function run(): void
    {
        $comps = [
            // ================== PvE ENDGAME (CAMPANHA) ==================
            [
                'slug' => 'thoran-cheese',
                'name' => 'Thoran Cheese',
                'mode' => RecommendedFormation::MODE_PVE,
                'tier' => 'S+',
                'level_deficit' => '120-450+',
                'description' => 'Thoran absorve dano e reflete tudo ao time inimigo com seu ultimate.',
                'notes' => 'Não atualizar Engraving do Thoran. SI+30 obrigatório. Funciona melhor acima de 180 de déficit de nível.',
                'hero_slugs' => [
                    'thoran', 'lorsan', 'kelthur', 'lyca', 'eironn', // core (5)
                    'athalia', 'nara', 'zaphrael', 'ezizh', 'rowan', 'raine', 'skriath', 'pippa', // flex
                ],
                'core_count' => 5,
                'artifacts' => ['Call (Lv5)', 'Eye', 'Blade'],
                'source_url' => 'https://afk.guide/best-team/',
                'source_label' => 'afk.guide / Best Teams',
                'sort_order' => 10,
            ],
            [
                'slug' => 'charm-comp-mehira',
                'name' => 'Charm Comp (Mehira)',
                'mode' => RecommendedFormation::MODE_PVE,
                'tier' => 'S+',
                'level_deficit' => '100-400+',
                'description' => 'Mehira charms inimigos que passam a se atacar mutuamente.',
                'notes' => 'Rosaline deve sempre seguir Mehira. Khazard SI+30 estende o Charm de 5s para 8s.',
                'hero_slugs' => [
                    'mehira', 'rosaline', 'rowan', // core (3)
                    'zolrath', 'lyca', 'khazard', 'oden', 'raku', 'kren', 'belinda', 'lucretia', 'scarlet', 'daimon', 'grezhul', 'brutus',
                ],
                'core_count' => 3,
                'artifacts' => ['Windbinder', 'Winged Warden', 'Call'],
                'source_url' => 'https://afk.guide/best-team/',
                'source_label' => 'afk.guide / Best Teams',
                'sort_order' => 11,
            ],
            [
                'slug' => 'leo-permastun',
                'name' => 'Leo Permastun',
                'mode' => RecommendedFormation::MODE_PVE,
                'tier' => 'S',
                'level_deficit' => '100-400+',
                'description' => 'Leonardo mantém inimigos stunned durante quase toda a batalha.',
                'notes' => 'Khazard SI+30 aumenta duração do CC do Leonardo.',
                'hero_slugs' => [
                    'leonardo', 'mishka', 'zolrath', // core (3)
                    'ezizh', 'nemora', 'oden', 'raku', 'thane', 'belinda', 'brutus', 'kren', 'scarlet', 'mortas', 'silas', 'palmer', 'khazard', 'ezio',
                ],
                'core_count' => 3,
                'artifacts' => null,
                'source_url' => 'https://afk.guide/best-team/',
                'source_label' => 'afk.guide / Best Teams',
                'sort_order' => 12,
            ],
            [
                'slug' => 'alna-gb',
                'name' => 'Alna GB (Graveborn)',
                'mode' => RecommendedFormation::MODE_PVE,
                'tier' => 'S',
                'level_deficit' => '100-350+',
                'description' => 'Alna e frontline com invencibilidade no início da batalha.',
                'notes' => 'Furniture 9 de Alna é obrigatório. Grezhul é o frontline padrão.',
                'hero_slugs' => [
                    'alna', 'grezhul', // core (2)
                    'baden', 'oden', 'scarlet', 'ferael', 'daimon', 'solise', 'mortas', 'silas', 'nevanthi', 'desira',
                ],
                'core_count' => 2,
                'artifacts' => null,
                'source_url' => 'https://afk.guide/best-team/',
                'source_label' => 'afk.guide / Best Teams',
                'sort_order' => 13,
            ],
            [
                'slug' => 'five-pull-eironn-skriath',
                'name' => 'Five-Pull Comp (Eironn + Skriath)',
                'mode' => RecommendedFormation::MODE_PVE,
                'tier' => 'S',
                'level_deficit' => '100-350+',
                'description' => 'Eironn puxa todos os 5 inimigos com ajuda do Furniture 3 de Skriath.',
                'notes' => 'Queen stuna todos os 5 inimigos por toda a batalha. Skriath SI+30 é ideal.',
                'hero_slugs' => [
                    'eironn', 'skriath', 'queen', // core (3)
                    'lucretia', 'raku', 'scarlet', 'daimon', 'talene', 'brutus', 'mishka', 'zolrath', 'lyca', 'ezio',
                ],
                'core_count' => 3,
                'artifacts' => null,
                'source_url' => 'https://afk.guide/best-team/',
                'source_label' => 'afk.guide / Best Teams',
                'sort_order' => 14,
            ],
            [
                'slug' => 'lucretia-invade',
                'name' => 'Lucretia Invade',
                'mode' => RecommendedFormation::MODE_PVE,
                'tier' => 'S',
                'level_deficit' => '100-350+',
                'description' => 'Aliados morrem para buffar Lucretia, que então mata todos os inimigos.',
                'notes' => 'Funciona dentro e fora do Five-Pull. Lorsan linka 2 inimigos no início.',
                'hero_slugs' => [
                    'lucretia', // core (1)
                    'lorsan', 'granit', 'raine', 'haelus', 'hodgkin', 'mortas', 'silas', 'zolrath', 'lyca', 'nemora', 'zaphrael', 'ezizh', 'brutus',
                ],
                'core_count' => 1,
                'artifacts' => null,
                'source_url' => 'https://afk.guide/best-team/',
                'source_label' => 'afk.guide / Best Teams',
                'sort_order' => 15,
            ],

            // ================== PvP ==================
            [
                'slug' => 'god-comp-lockout',
                'name' => 'God Comp (Lockout)',
                'mode' => RecommendedFormation::MODE_PVP,
                'tier' => 'S+',
                'level_deficit' => null,
                'description' => 'Nega os ultimates inimigos via passiva do Ezizh. Os aliados spammam ults continuamente.',
                'notes' => 'O time mais forte na Arena. Capacidade ofensiva e defensiva excelentes.',
                'hero_slugs' => [
                    'ezizh', 'elijah-lailah', 'talene', // core (3)
                    'saurus',
                ],
                'core_count' => 3,
                'artifacts' => null,
                'source_url' => 'https://afk.guide/best-team/',
                'source_label' => 'afk.guide / Best Teams',
                'sort_order' => 20,
            ],
            [
                'slug' => 'gwyneth-burst',
                'name' => 'Gwyneth Burst',
                'mode' => RecommendedFormation::MODE_PVP,
                'tier' => 'S',
                'level_deficit' => null,
                'description' => 'Posiciona inimigos para máximo dano em AoE da Gwyneth.',
                'notes' => 'Forte no ataque. Fraco como defesa.',
                'hero_slugs' => ['gwyneth', 'eironn', 'lyca', 'lorsan'], // core (4)
                'core_count' => 4,
                'artifacts' => null,
                'source_url' => 'https://afk.guide/best-team/',
                'source_label' => 'afk.guide / Best Teams',
                'sort_order' => 21,
            ],
            [
                'slug' => 'flora-stall',
                'name' => 'Flora Stall',
                'mode' => RecommendedFormation::MODE_PVP,
                'tier' => 'S',
                'level_deficit' => null,
                'description' => 'Controla a batalha e escala dano e tankiness ao longo do tempo.',
                'notes' => 'Counter forte ao God Comp. Requer alto investimento. Skreg 9/9 é vital.',
                'hero_slugs' => [
                    'flora', 'skreg', // core (2)
                    'athalia', 'nara', 'orthros', 'talene',
                ],
                'core_count' => 2,
                'artifacts' => null,
                'source_url' => 'https://afk.guide/best-team/',
                'source_label' => 'afk.guide / Best Teams',
                'sort_order' => 22,
            ],
            [
                'slug' => 'mauler-core',
                'name' => 'Mauler Core',
                'mode' => RecommendedFormation::MODE_PVP,
                'tier' => 'A',
                'level_deficit' => null,
                'description' => 'Baseado no Furniture 9/9 do Skreg que dá buffs insanos ao time.',
                'notes' => 'Skreg cruza para o lado inimigo. Satrana é counter de times tankudos.',
                'hero_slugs' => ['skreg', 'satrana', 'numisu', 'tidus', 'ezizh'],
                'core_count' => 5,
                'artifacts' => null,
                'source_url' => 'https://afk.guide/best-team/',
                'source_label' => 'afk.guide / Best Teams',
                'sort_order' => 23,
            ],
            [
                'slug' => 'graveborn-celestial',
                'name' => 'Graveborn Celestial',
                'mode' => RecommendedFormation::MODE_PVP,
                'tier' => 'A',
                'level_deficit' => null,
                'description' => 'Controla batalha enquanto escala dano com Talene e/ou Orthros.',
                'notes' => 'Disrupts carries que tentam burst-down o time.',
                'hero_slugs' => ['talene', 'orthros', 'athalia', 'nara'],
                'core_count' => 4,
                'artifacts' => null,
                'source_url' => 'https://afk.guide/best-team/',
                'source_label' => 'afk.guide / Best Teams',
                'sort_order' => 24,
            ],
            [
                'slug' => 'energy-battery',
                'name' => 'Energy Battery',
                'mode' => RecommendedFormation::MODE_PVP,
                'tier' => 'A',
                'level_deficit' => null,
                'description' => 'Time que casta ultimates rápido e frequentemente, bloqueando os do inimigo.',
                'notes' => 'Inimigos permastunnados.',
                'hero_slugs' => ['rowan', 'rosaline', 'tasi'],
                'core_count' => 3,
                'artifacts' => null,
                'source_url' => 'https://afk.guide/best-team/',
                'source_label' => 'afk.guide / Best Teams',
                'sort_order' => 25,
            ],
            [
                'slug' => 'burst-pvp',
                'name' => 'Burst PvP',
                'mode' => RecommendedFormation::MODE_PVP,
                'tier' => 'A',
                'level_deficit' => null,
                'description' => 'Atacar primeiro (necessita Furniture do Zolrath) e atacar forte.',
                'notes' => 'Precisa do furniture do Zolrath.',
                'hero_slugs' => ['zolrath', 'lucretia', 'eironn'],
                'core_count' => 3,
                'artifacts' => null,
                'source_url' => 'https://afk.guide/best-team/',
                'source_label' => 'afk.guide / Best Teams',
                'sort_order' => 26,
            ],
        ];

        $keepSlugs = array_column($comps, 'slug');

        // Idempotência: remove comps de seed antigos que não estão mais na lista curada.
        // Não toca em comps com slugs fora deste conjunto (caso admin adicione manualmente no futuro).
        RecommendedFormation::query()
            ->whereIn('source_label', ['afk.guide / Best Teams', 'afk.guide / Twisted Realm', 'afk.guide / Arcane Labyrinth', 'afk.guide / Kings Tower'])
            ->whereNotIn('slug', $keepSlugs)
            ->delete();

        foreach ($comps as $comp) {
            RecommendedFormation::updateOrCreate(['slug' => $comp['slug']], $comp);
        }
    }
}
