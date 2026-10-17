<?php

namespace Database\Seeders;

use App\Models\Hero;
use App\Models\HeroFeature;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Heroes destaque por fase / modo. Curado do afk.guide via dump capturado
 * com a extensão Claude Chrome (autorizado pelo usuário).
 *
 * Cada entrada mantém um pequeno `note` quando o dump original tinha
 * requisito específico (ex: "Daimon (SI Lv20)" → tag=early_carry note="SI Lv20").
 */
class HeroFeaturesSeeder extends Seeder
{
    public function run(): void
    {
        $sourceUrl = 'https://afk.guide/afk-arena-tier-list/';

        $featureGroups = [
            HeroFeature::TAG_EARLY_CARRY => [
                'Daimon (SI Lv20)',
                'Raku (SI Lv30)',
                'Scarlet (Ascended)',
                'Ainz (Ascended)',
                'Eironn',
                'Kren',
            ],
            HeroFeature::TAG_EARLY_SUPPORT => [
                'Joan of Arc',
                'Palmer',
                'Nevanthi',
                'Ferael',
                'Rosaline',
                'Lyca',
                'Tasi',
                'Silas',
                'Merlin',
                'Rowan (SI Lv30)',
            ],
            HeroFeature::TAG_EARLY_TANK => [
                'Grezhul (Furniture Lv9)',
                'Mishka (Furniture Lv9)',
                'Arthur',
            ],
            HeroFeature::TAG_EARLY_UTILITY => [
                'Lorsan (Elite)',
                'Brutus (Elite)',
                'Mortas (Elite)',
            ],
            HeroFeature::TAG_PVP_TOP => [
                'Ezizh',
                'Mehira',
                'Talene',
                'Twins (Elijah & Lailah)',
                'Oden',
                'Lucretia',
                'Flora',
                'Queen',
                'Ainz',
            ],
            HeroFeature::TAG_TWISTED_REALM_TOP => [
                'Scarlet',
                'Awakened Solise',
                'Twins',
                'Mortas',
                'Grezhul',
                'Warek',
            ],
        ];

        $created = 0;
        $missing = [];

        foreach ($featureGroups as $tag => $entries) {
            foreach ($entries as $raw) {
                [$name, $note] = $this->splitNameAndNote($raw);
                $hero = $this->resolveHero($name);

                if (! $hero) {
                    $missing[] = $name;

                    continue;
                }

                HeroFeature::updateOrCreate(
                    ['hero_id' => $hero->id, 'tag' => $tag, 'source' => 'afk.guide'],
                    ['note' => $note, 'source_url' => $sourceUrl],
                );
                $created++;
            }
        }

        if ($missing !== []) {
            $this->command?->warn('Heróis sem cadastro no DB: '.implode(', ', $missing));
        }
        $this->command?->info("Features persistidas: {$created}");
    }

    /**
     * "Daimon (SI Lv20)" → ['Daimon', 'SI Lv20']
     * "Eironn" → ['Eironn', null]
     *
     * @return array{0: string, 1: ?string}
     */
    private function splitNameAndNote(string $raw): array
    {
        if (preg_match('/^(.+?)\s*\((.+)\)\s*$/', trim($raw), $m)) {
            return [trim($m[1]), trim($m[2])];
        }

        return [trim($raw), null];
    }

    private function resolveHero(string $name): ?Hero
    {
        // Caso especial: "Twins (Elijah & Lailah)" → name é "Twins" mas o slug é elijah-lailah
        $aliases = [
            'twins' => 'elijah-lailah',
            'awakened solise' => 'solise',
            'awakened lyca' => 'lyca',
            'awakened belinda' => 'belinda',
            'awakened thane' => 'thane',
            'awakened brutus' => 'brutus',
            'awakened ezizh' => 'ezizh',
            'awakened baden' => 'baden',
            'awakened talene' => 'talene',
            'awakened thoran' => 'thoran',
            'awakened safiya' => 'safiya',
            'awakened lucius' => 'lucius',
            'awakened estrilda' => 'estrilda',
            'awakened pippa' => 'pippa',
            'awakened skriath' => 'skriath',
            'awakened fawkes' => 'fawkes',
            'awakened eironn' => 'eironn',
            'awakened antandra' => 'antandra',
            'ainz' => 'ainz-ooal-gown',
        ];

        $key = strtolower($name);
        if (isset($aliases[$key])) {
            return Hero::where('slug', $aliases[$key])->first();
        }

        $slug = Str::slug($name);
        if ($slug === '') {
            return null;
        }
        $hero = Hero::where('slug', $slug)->first();
        if ($hero) {
            return $hero;
        }

        return Hero::whereRaw('LOWER(name) = ?', [strtolower($name)])->first();
    }
}
