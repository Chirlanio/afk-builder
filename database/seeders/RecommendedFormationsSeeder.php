<?php

namespace Database\Seeders;

use App\Models\RecommendedFormation;
use Illuminate\Database\Seeder;

class RecommendedFormationsSeeder extends Seeder
{
    public function run(): void
    {
        $comps = [
            [
                'slug' => 'god-comp',
                'name' => 'God Comp',
                'mode' => RecommendedFormation::MODE_PVP,
                'description' => "Comp clássico focado em ciclar definitivas via geração rápida de energia. Talene + Elijah & Lailah + Rowan formam o core; Tasi ou Ezizh fecham a equipe dependendo do oponente.",
                'hero_slugs' => ['talene', 'elijah-lailah', 'rowan', 'tasi', 'ezizh'],
                'source_url' => 'https://afk.guide/best-team/',
                'source_label' => 'afk.guide / Best Teams',
                'sort_order' => 10,
            ],
            [
                'slug' => 'green-sand-wolf',
                'name' => 'Green Sand Wolf',
                'mode' => RecommendedFormation::MODE_PVE,
                'description' => "Comp PvE para superar 90-100 níveis de déficit. Skriath puxa o backline inimigo, Eironn aplica vortex de 5, Tidus dá fear AoE. Lyca e Safiya completam o burst.",
                'hero_slugs' => ['tidus', 'lyca', 'skriath', 'eironn', 'safiya'],
                'source_url' => 'https://afk.guide/best-team/',
                'source_label' => 'afk.guide / Best Teams',
                'sort_order' => 20,
            ],
        ];

        foreach ($comps as $comp) {
            RecommendedFormation::updateOrCreate(['slug' => $comp['slug']], $comp);
        }
    }
}
