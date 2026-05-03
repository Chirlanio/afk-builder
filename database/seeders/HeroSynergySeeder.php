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
            // ========= Originais (Fase 5.1) =========
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

            // ========= Expansão (Fase 12) — Maulers =========
            ['khazard', 'brutus', 11, 'Sinergia Mauler: Brutus enrage + Khazard AoE finaliza.'],
            ['peggy', 'brutus', 10, 'Peggy buff de ataque + Brutus enrage = burst Mauler.'],
            ['mishka', 'tarnos', 10, 'Tanques Maulers protegem o backline com taunt + cura.'],
            ['vurk', 'khazard', 9, 'Vurk segura backline, Khazard inflige AoE.'],

            // ========= Expansão — Lightbearers =========
            ['ezio', 'lucius', 12, 'Ezio carry com proteção do escudo de Lucius.'],
            ['gwyneth', 'ezio', 10, 'Dupla de rangers Lightbearer com burst alto.'],
            ['lucretia', 'lucius', 10, 'Lucretia DPS com sustain do Lucius.'],
            ['estrilda', 'lucius', 10, 'Buff de velocidade + escudo: core Lightbearer.'],

            // ========= Expansão — Wilders =========
            ['flora', 'atheus', 11, 'Flora controle + Atheus DoT — núcleo Selvagem clássico.'],
            ['nakoruru', 'atheus', 10, 'Nakoruru summon + Atheus dano sustentado.'],
            ['solise', 'flora', 10, 'Solise stun + Flora summon = controle Wilder pesado.'],
            ['saurus', 'atheus', 9, 'Saurus tank + Atheus carry — composição Wilder defensiva.'],

            // ========= Expansão — Graveborns =========
            ['thoran', 'grezhul', 12, 'Thoran reflete dano, Grezhul amplifica com debuffs.'],
            ['kelthur', 'thoran', 11, 'Lifesteal Graveborn — Kelthur sustain + Thoran tank.'],
            ['olgath', 'thoran', 9, 'Olgath energia + Thoran tank: pressão Graveborn.'],
            ['crassio', 'grezhul', 9, 'Crit Graveborn: Crassio + Grezhul amplifica burst.'],

            // ========= Expansão — Mages =========
            ['walker', 'tasi', 10, 'Walker DPS mágico + Tasi CC = magic team.'],
            ['mehira', 'walker', 10, 'Mehira charm + Walker burst mágico.'],
            ['rem', 'tasi', 9, 'Rem DoT + Tasi CC — core mágico de controle.'],

            // ========= Expansão — Speed/burst =========
            ['eironn', 'lyca', 12, 'Eironn pull + Lyca speed = setup AoE rápido.'],
            ['drez', 'zikis', 10, 'Assassinos de burst — Drez teleport + Zikis crit.'],
            ['ezio', 'joker', 10, 'Combo de assassinos com burst alto e CC.'],
            ['theowyn', 'ezizh', 11, 'CC em cadeia: Theowyn freeze + Ezizh fear.'],

            // ========= Expansão — Hypogeans =========
            ['ezizh', 'mehira', 10, 'Hypogean stack: medo + charme dominam o backline.'],
            ['baden', 'ezizh', 9, 'Hypogean burst + medo de Ezizh.'],

            // ========= Expansão — Celestials/revive =========
            ['talene', 'gwyneth', 10, 'Talene revive + Gwyneth carry sustentado.'],
            ['talene', 'saurus', 9, 'Talene revive + Saurus tank — sustain longo.'],

            // ========= Expansão — Collabs/Dimensionais =========
            ['ainz-ooal-gown', 'albedo', 12, 'Overlord duo: Ainz dominador + Albedo waifu tank.'],
            ['wu-kong', 'nakoruru', 9, 'Collab Asia: Wu Kong CC + Nakoruru summon.'],
            ['mulan', 'queen', 9, 'Burst Dimensional: Mulan finisher + QUEEN AoE.'],
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
