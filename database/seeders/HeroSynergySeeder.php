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

            // ========= Expansão v2 — Lightbearers extras =========
            ['raku', 'rowan', 10, 'Raku cura em cadeia + Rowan energia global: sustain LB.'],
            ['raine', 'gwyneth', 10, 'Dupla Ranger LB com burst focado no backline.'],
            ['fawkes', 'belinda', 10, 'Fawkes zoneia com pilares, Belinda escala como carry mágico.'],
            ['leofric', 'gwyneth', 9, 'Leofric buff crítico + Gwyneth escala crit no ult.'],
            ['haelus', 'lucius', 10, 'Haelus purifica debuffs, Lucius escuda o time.'],

            // ========= Expansão v2 — Wilders extras =========
            ['lorsan', 'eironn', 11, 'Lorsan amplifica dano mágico + Eironn AoE ao agrupar.'],
            ['ukyo', 'eironn', 10, 'Ukyo dodge counter + Eironn cria a hitbox pra ele farmar.'],
            ['silas', 'solise', 10, 'Silas buff crítico Wilder + Solise burst mágico.'],
            ['nemora', 'saurus', 10, 'Nemora dispel/heal + Saurus tank Wilder resistente.'],
            ['respen', 'eironn', 9, 'Respen debuff mágico amplifica o AoE do Eironn.'],

            // ========= Expansão v2 — Graveborns (Shemira core) =========
            ['shemira', 'grezhul', 13, 'Duo canônico: Grezhul CC + Shemira drain no backline.'],
            ['shemira', 'thoran', 12, 'Thoran reflete o dano recebido enquanto Shemira drena.'],
            ['ferael', 'shemira', 12, 'Silence do Ferael impede reação ao ult da Shemira.'],
            ['mortas', 'grezhul', 10, 'Mortas executa alvos low-HP amaciados pelo Grezhul.'],
            ['izold', 'thoran', 10, 'Izold ganha stacks conforme Thoran leva porrada.'],
            ['daimon', 'shemira', 11, 'Daimon burst dark + Shemira sustain: núcleo Graveborn de topo.'],

            // ========= Expansão v2 — Maulers extras =========
            ['kaz', 'brutus', 11, 'Kaz haste no time = Brutus entra em enrage muito antes.'],
            ['satrana', 'brutus', 10, 'AoE de Satrana amacia, Brutus finaliza.'],
            ['khasos', 'brutus', 10, 'Khasos buff ATK Mauler + scaling do Brutus.'],
            ['skriath', 'eironn', 10, 'Skriath summons agrupados pelo pull do Eironn ("5-pull").'],
            ['warek', 'antandra', 9, 'Dupla AoE Mauler de área com sustain.'],

            // ========= Expansão v2 — Celestials / stall / Hypogeans =========
            ['alna', 'talene', 12, 'Alna immortality + Talene revive = stall Celestial supremo.'],
            ['zaphrael', 'talene', 10, 'Zaphrael execute + Talene revive: pressão sustentada.'],
            ['desira', 'ezizh', 10, 'Desira lockdown + Ezizh medo em cadeia no backline.'],
            ['oden', 'baden', 10, 'Burst Hypogean sinérgico: Oden setup + Baden execute.'],

            // ========= Expansão v2 — Collabs / cross-faction =========
            ['ainz-ooal-gown', 'shemira', 10, 'Ainz domina 1 alvo, Shemira drena a equipe toda.'],
            ['zolrath', 'talene', 10, 'Zolrath time-control + Talene revive: stall dimensional.'],
            ['merlin', 'belinda', 9, 'Merlin buff mago + Belinda como carry mago principal.'],
            ['leonardo', 'tasi', 9, 'Leonardo support versátil + Tasi CC mágico.'],
            ['prince-of-persia', 'ezio', 9, 'Assassinos dimensionais com mobilidade absurda.'],
            ['estrilda', 'brutus', 9, 'Estrilda buff ATK acelera o snowball do Brutus.'],
            ['rowan', 'gwyneth', 9, 'Rowan energia + Gwyneth libera ult antes do inimigo.'],
            ['ferael', 'ezizh', 10, 'Silence + medo: lockdown total de backline PvP.'],

            // ========= Expansão v3 — Draconis (facção nova, sem representação anterior) =========
            ['kregor', 'leviathan', 11, 'Dupla tank Draconis: wall defensivo raro entre duas frontlines.'],
            ['odysseus', 'sion', 11, 'Odysseus tank Draconis + Sion ranger burst por trás.'],
            ['lan', 'kaelon', 10, 'Lan support + Kaelon ranger de burst — core Draconis.'],
            ['serennia', 'pulina', 10, 'Rangers Draconis com foco em crítico e AoE.'],
            ['nyla', 'hildwin', 10, 'Nyla support + Hildwin mage sustain: sustain Draconis.'],
            ['tavriel', 'elthara', 10, 'Tavriel support + Elthara mage carry dimensional.'],
            ['begris', 'kregor', 9, 'Warrior Draconis + tank ativo garantem frontline pesada.'],
            ['haelia', 'odysseus', 9, 'Haelia warrior + Odysseus tank: frontline dupla Draconis.'],
            ['melion', 'skylan', 9, 'Melion support + Skylan ranger burst.'],
            ['velufira', 'sion', 9, 'Support + ranger burst: core ranged Draconis.'],
            ['kaelon', 'eironn', 10, 'Cross-fac: Kaelon Draconis burst + Eironn agrupa alvos.'],
            ['odysseus', 'shemira', 10, 'Cross-fac: tank Draconis segura, Shemira drena.'],

            // ========= Expansão v3 — Awakened variants (slugs próprios com stats maiores) =========
            ['shemira-the-soulreaver', 'grezhul', 14, 'Awakened Shemira drain amplificado + Grezhul debuff.'],
            ['thoran-deathlord', 'grezhul', 13, 'Awakened Thoran reflete mais dano com debuffs do Grezhul.'],
            ['lucius-oath-of-radiance', 'rosaline', 15, 'Awakened do par canônico — Rosaline segue Lucius.'],
            ['estrilda-crimson-reaver', 'brutus', 11, 'Awakened Estrilda buff ATK + Brutus enrage.'],
            ['brutus-lions-pride', 'kaz', 12, 'Awakened Brutus + Kaz haste = enrage instantâneo.'],
            ['eironn-frostglade-paladin', 'antandra', 14, 'Awakened Eironn pull expandido + Antandra AoE.'],
            ['talene-the-resurging-flame', 'alna', 13, 'Awakened Talene revive + Alna immortality: stall Celestial de topo.'],
            ['ezizh-lord-of-nightmares', 'mehira', 11, 'Awakened Ezizh medo global + Mehira charm.'],

            // ========= Expansão v3 — Dimensionais modernos (colabs) =========
            ['geralt', 'yennefer', 12, 'The Witcher canônico: Geralt melee + Yennefer mage.'],
            ['sung-jinwoo', 'cha-hae-in', 12, 'Solo Leveling couple — burst ranger + warrior sinérgico.'],
            ['saitama', 'terrible-tornado', 11, 'OPM: Saitama one-punch + Tornado AoE psíquico.'],
            ['shalltear-bloodfallen', 'ainz-ooal-gown', 12, 'Overlord: vampira + mestre dominador.'],
            ['marcille', 'laios', 10, 'Delicious in Dungeon: Marcille mage + Laios warrior.'],
            ['rimuru', 'shuna', 10, 'TenSura Tempest: Rimuru evolutivo + Shuna support.'],
            ['dracula', 'shemira', 10, 'Cross-fac dark: Dracula burst + Shemira drain.'],
            ['joan-of-arc', 'lucius', 10, 'Joan support paladin + Lucius escudo = frontline sagrada.'],

            // ========= Expansão v3 — Hypogean core alargado =========
            ['harvey', 'baden', 10, 'Harvey tank Hypogean + Baden burst.'],
            ['lavatune', 'ezizh', 10, 'Lavatune buff Hypogean + Ezizh medo em cadeia.'],
            ['knox', 'mehira', 10, 'Knox support + Mehira charm: controle Hypogean.'],
            ['lucilla', 'ezizh', 9, 'Lucilla support + Ezizh medo — sustain Hypogean.'],
            ['mezoth', 'ezizh', 10, 'Mezoth tank agarra + Ezizh CC no backline.'],
            ['envydiel-venomous-desire', 'baden', 10, 'Awakened Envy + Baden: burst Hypogean escalado.'],
            ['framton', 'mezoth', 9, 'Framton warrior + Mezoth tank: frontline Hypogean.'],
            ['harvey', 'shemira', 10, 'Cross-fac: tank Hypogean segura, Shemira drena.'],
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
