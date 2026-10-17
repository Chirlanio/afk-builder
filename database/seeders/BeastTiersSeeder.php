<?php

namespace Database\Seeders;

use App\Models\Beast;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Tier list de Beasts (Elite + Rare + Fabled) curada do afk.guide.
 * Captura humana via extensão Claude Chrome — autorizada pelo usuário.
 *
 * Cria beasts faltantes (Count Snickerfang, Joyous Roamer) com tier_note
 * mas sem buffs (cadastro manual via /admin/beasts).
 */
class BeastTiersSeeder extends Seeder
{
    public function run(): void
    {
        $entries = [
            ['name' => 'Slumber Seal',       'tier' => 'S+', 'note' => 'Maior dano do jogo, pode carregar o time.'],
            ['name' => 'Flutterplume Owl',   'tier' => 'S',  'note' => 'Flexível: buffa aliados, debuffa inimigos, CC immunity e freeze.'],
            ['name' => 'Pandy Dumpty',       'tier' => 'S',  'note' => 'Aumenta Crit Rating e Crit Damage Amplification dos aliados.'],
            ['name' => 'Treatspector',       'tier' => 'S',  'note' => 'Level 18 skill: heróis sobrevivem dano fatal.'],
            ['name' => 'Nightfall Pegasus',  'tier' => 'S',  'note' => 'Fabled Beast muito forte.'],
            ['name' => 'Talismane',          'tier' => 'A',  'note' => 'Buffs enormes em um herói (dois com level 18).'],
            ['name' => 'Rock Crown Lizard',  'tier' => 'A',  'note' => 'Defensiva: sobrevive dano fatal, CC + redução de ATK inimigo.'],
            ['name' => 'Ice Crown',          'tier' => 'A',  'note' => 'Forte PvP e PvE: congela inimigos (precisa nível 12).'],
            ['name' => 'Fox Fatale',         'tier' => 'A',  'note' => 'Charm de inimigos (precisa nível 12).'],
            ['name' => 'Aureus',             'tier' => 'A',  'note' => 'Buffa carry, debuffa time inimigo.'],
            ['name' => 'Trickery Chest',     'tier' => 'A',  'note' => 'Sobrevive dano fatal (nível 18), dá energia e drena energia inimiga.'],
            ['name' => 'Count Snickerfang',  'tier' => 'B',  'note' => 'Fabled Beast — niche para F2P.'],
            ['name' => 'Shroom Spooder',     'tier' => 'B',  'note' => 'Debuffs úteis no time inimigo.'],
            ['name' => 'Pearlcrest Ibis',    'tier' => 'B',  'note' => 'Defensiva flexível, level 18 evita dano fatal.'],
            ['name' => 'Winged Lion',        'tier' => 'B',  'note' => 'Extra energia, buff ATK e redução de dano ao herói mais forte.'],
            ['name' => 'Savage Souffle',     'tier' => 'B',  'note' => 'Energia extra (nível 0), CC forte (nível 6 e 9).'],
            ['name' => 'Phantasmoth',        'tier' => 'B',  'note' => 'Buffa carry, especialmente heróis de ataque normal forte.'],
            ['name' => 'Radish Rotunda',     'tier' => 'C',  'note' => 'Defensiva, especialmente com bons healers.'],
            ['name' => 'Bellbellow',         'tier' => 'C',  'note' => 'Buffs defensivos, potencial real apenas no nível 18.'],
            ['name' => 'Blade Ridge',        'tier' => 'C',  'note' => 'Contra bosses, reduz resistência mágica e física.'],
            ['name' => 'Fire Breather',      'tier' => 'C',  'note' => 'Nível 18 dá buff de dano e CC immunity ao herói mais forte.'],
            ['name' => 'Tufty Ears',         'tier' => 'D',  'note' => 'Rara; level 18 remove debuffs dos aliados.'],
            ['name' => 'Polar Beast',        'tier' => 'D',  'note' => 'Debuffs decentes contra inimigos, útil com Thoran.'],
            ['name' => 'Joyous Roamer',      'tier' => 'D',  'note' => 'Fraco em comparação.'],
            ['name' => 'Feline Vesperio',    'tier' => 'D',  'note' => 'Nicho, obsoleto.'],
            ['name' => 'Grassy Orb',         'tier' => 'F',  'note' => 'Melhor rara ofensiva até ter elites no nível 6.'],
            ['name' => 'Dreary Ball',        'tier' => 'F',  'note' => 'Rara básica.'],
        ];

        $sourceUrl = 'https://afk.guide/afk-arena-beast-tier-listpatch-1-101/';

        foreach ($entries as $entry) {
            $slug = Str::slug($entry['name']);
            $beast = Beast::where('slug', $slug)->first();

            if ($beast) {
                $beast->update([
                    'tier' => $entry['tier'],
                    'tier_note' => $entry['note'],
                ]);
            } else {
                Beast::create([
                    'slug' => $slug,
                    'name' => $entry['name'],
                    'tier' => $entry['tier'],
                    'tier_note' => $entry['note'],
                    'source_url' => $sourceUrl,
                    // Buffs (buffs_strength/intelligence/agility/team) ficam null —
                    // preenchidos por `beasts:seed-buffs` ou pelo /admin/beasts.
                    'is_active' => true,
                ]);
            }
        }
    }
}
