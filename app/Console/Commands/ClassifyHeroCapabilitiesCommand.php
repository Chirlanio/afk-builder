<?php

namespace App\Console\Commands;

use App\Models\Hero;
use App\Services\Optimization\HeroCapabilityClassifier;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ClassifyHeroCapabilitiesCommand extends Command
{
    protected $signature = 'heroes:classify-capabilities {--slug= : Limita a um hero slug específico}';

    protected $description = 'Gera o perfil de capacidades dos heróis (cura, controle, energia...) a partir das habilidades e do papel da wiki';

    public function handle(HeroCapabilityClassifier $classifier): int
    {
        $query = Hero::query()->with(['abilities', 'heroClass:id,code']);

        if ($slug = $this->option('slug')) {
            $query->where('slug', $slug);
        }

        $heroes = $query->get();
        if ($heroes->isEmpty()) {
            $this->info('Nenhum herói encontrado.');

            return self::SUCCESS;
        }

        $this->info("Classificando {$heroes->count()} heróis...");

        $tagCounts = array_fill_keys(HeroCapabilityClassifier::ALL, 0);
        $withoutProfile = [];

        // Idempotente: sempre regenera `capabilities` e `hero_abilities.effects`.
        // Correções do admin ficam em `capabilities_manual` e não são tocadas.
        DB::transaction(function () use ($heroes, $classifier, &$tagCounts, &$withoutProfile) {
            foreach ($heroes as $hero) {
                foreach ($hero->abilities as $ability) {
                    $tags = $classifier->classifyText($ability->description);
                    $ability->update(['effects' => empty($tags) ? null : $tags]);
                }

                $profile = $classifier->profile(
                    $hero->abilities->pluck('description')->all(),
                    $hero->role_label,
                    $hero->heroClass?->code,
                );
                $hero->update(['capabilities' => empty($profile) ? null : $profile]);

                if (count($profile) <= 1) {
                    $withoutProfile[] = $hero->slug;
                }
                foreach (array_keys($profile) as $tag) {
                    $tagCounts[$tag]++;
                }
            }
        });

        $this->newLine();
        $this->line('Heróis por capacidade:');
        foreach ($tagCounts as $tag => $count) {
            $this->line(sprintf('  %-17s %d', $tag, $count));
        }

        if (! empty($withoutProfile)) {
            $this->newLine();
            $this->warn('Com 0-1 capacidade (revisar no admin): '.implode(', ', $withoutProfile));
        }

        return self::SUCCESS;
    }
}
