<?php

namespace App\Console\Commands;

use App\Models\HeroFurnitureAbility;
use App\Services\Optimization\FurnitureEffectClassifier;
use Illuminate\Console\Command;

class ClassifyFurnitureEffectsCommand extends Command
{
    protected $signature = 'furniture:classify-effects {--force : Reclassifica mesmo abilities que já têm effects} {--slug= : Limita a um hero slug específico}';

    protected $description = 'Roda o FurnitureEffectClassifier sobre f3_description/f9_description e popula a coluna effects';

    public function handle(FurnitureEffectClassifier $classifier): int
    {
        $query = HeroFurnitureAbility::query()->with('hero:id,slug,name');

        if ($slug = $this->option('slug')) {
            $query->whereHas('hero', fn ($q) => $q->where('slug', $slug));
        }

        if (! $this->option('force')) {
            $query->whereNull('effects');
        }

        $abilities = $query->get();

        if ($abilities->isEmpty()) {
            $this->info('Nada a classificar (use --force para reclassificar).');

            return self::SUCCESS;
        }

        $this->info("Classificando {$abilities->count()} furniture abilities...");

        $stats = ['classified' => 0, 'f3_with_tags' => 0, 'f9_with_tags' => 0, 'empty' => 0];
        $tagCounts = array_fill_keys(FurnitureEffectClassifier::ALL_TAGS, 0);

        foreach ($abilities as $ability) {
            $f3Tags = $classifier->classify($ability->f3_description);
            $f9Tags = $classifier->classify($ability->f9_description);

            $effects = [];
            if (! empty($f3Tags)) {
                $effects['f3'] = $f3Tags;
                $stats['f3_with_tags']++;
                foreach ($f3Tags as $t) {
                    $tagCounts[$t]++;
                }
            }
            if (! empty($f9Tags)) {
                $effects['f9'] = $f9Tags;
                $stats['f9_with_tags']++;
                foreach ($f9Tags as $t) {
                    $tagCounts[$t]++;
                }
            }

            if (empty($effects)) {
                $stats['empty']++;
                $ability->update(['effects' => null]);
            } else {
                $ability->update(['effects' => $effects]);
                $stats['classified']++;
            }
        }

        $this->newLine();
        $this->info("Classificadas: {$stats['classified']} | sem tags: {$stats['empty']}");
        $this->line("  F3 com tags: {$stats['f3_with_tags']} | F9 com tags: {$stats['f9_with_tags']}");
        $this->newLine();
        $this->line('Distribuição de tags:');
        foreach ($tagCounts as $tag => $count) {
            if ($count > 0) {
                $this->line(sprintf('  %-22s %d', $tag, $count));
            }
        }

        return self::SUCCESS;
    }
}
