<?php

namespace Database\Seeders;

use App\Models\Hero;
use App\Models\HeroSynergy;
use Illuminate\Database\Seeder;

class HeroSynergySeeder extends Seeder
{
    public function run(): void
    {
        $pairs = [
            ['lucius', 'rosaline', 15, 'Dupla canônica — Rosaline é projetada para acompanhar Lucius.'],
            ['athalia', 'hendrik', 12, 'Hendrik provoca, Athalia executa alvos abaixo de 50%.'],
            ['brutus', 'lyca', 12, 'Lyca acelera Brutus para entrar em fúria mais cedo.'],
            ['eironn', 'antandra', 14, 'Eironn agrupa inimigos, Antandra causa dano em área.'],
            ['eironn', 'khazard', 12, 'Eironn agrupa, Khazard atinge a aglomeração.'],
            ['eironn', 'albedo', 11, 'Albedo permite Eironn puxar com mais facilidade.'],
            ['ezizh', 'skreg', 13, 'Ezizh aplica medo em cadeia, Skreg dispara nos alvos imobilizados.'],
            ['ezizh', 'elijah-lailah', 12, 'Ezizh + Twins potencializa CC e energia da equipe.'],
            ['belinda', 'mehira', 11, 'Combo de equipe mágica com charme e dano sustentado.'],
            ['talene', 'lucius', 10, 'Sustain duplo: Talene revive, Lucius escudo.'],
            ['rowan', 'estrilda', 11, 'Energia + buff de ataque para o time inteiro.'],
            ['numisu', 'brutus', 10, 'Totens de Numisu amplificam o burst do Brutus.'],
            ['lyca', 'estrilda', 10, 'Velocidade da Lyca + buff de ataque da Estrilda.'],
            ['tasi', 'mehira', 10, 'Equipe mágica com CC pesado.'],
            ['solise', 'atheus', 11, 'Núcleo Selvagem com buffs e DoT.'],
            ['pippa', 'belinda', 10, 'Geração de energia para Mages.'],
            ['hendrik', 'rowan', 10, 'Tank com taunt + sustain de energia.'],
        ];

        $slugs = collect($pairs)
            ->flatMap(fn ($p) => [$p[0], $p[1]])
            ->unique()
            ->values()
            ->all();

        $heroesBySlug = Hero::whereIn('slug', $slugs)->pluck('id', 'slug');

        foreach ($pairs as [$slugA, $slugB, $weight, $note]) {
            $idA = $heroesBySlug[$slugA] ?? null;
            $idB = $heroesBySlug[$slugB] ?? null;

            if ($idA === null || $idB === null) {
                $this->command?->warn("Synergia ignorada: {$slugA} + {$slugB} (slug não encontrado)");
                continue;
            }

            $heroAId = min($idA, $idB);
            $heroBId = max($idA, $idB);

            HeroSynergy::updateOrCreate(
                ['hero_a_id' => $heroAId, 'hero_b_id' => $heroBId],
                ['weight' => $weight, 'note' => $note],
            );
        }
    }
}
