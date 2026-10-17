<?php

namespace App\Console\Commands;

use App\Models\Guide;
use App\Models\Hero;
use App\Models\HeroAbility;
use App\Models\HeroFurnitureAbility;
use Composer\CaBundle\CaBundle;
use Illuminate\Console\Command;
use Stichoza\GoogleTranslate\GoogleTranslate;
use Throwable;

class TranslateContentCommand extends Command
{
    protected $signature = 'content:translate
        {--type=all : O que traduzir: lore | abilities | furniture | guides | all}
        {--limit= : Máximo de itens por tipo}
        {--slug= : Apenas o herói com este slug (afeta lore + abilities)}
        {--force : Re-traduz mesmo se _pt já existe}';

    protected $description = 'Traduz conteúdo de heróis e guias EN→PT-BR via Google Translate (sem API key).';

    private const FROM = 'en';

    private const TO = 'pt';

    private GoogleTranslate $translator;

    public function handle(): int
    {
        $this->translator = new GoogleTranslate(self::TO, self::FROM);
        // Confia no CA bundle do Composer (WAMP)
        $this->translator->setOptions([
            'verify' => CaBundle::getSystemCaRootBundlePath(),
        ]);

        $type = $this->option('type');
        $force = (bool) $this->option('force');
        $limit = $this->option('limit') !== null ? (int) $this->option('limit') : null;
        $slug = $this->option('slug');

        $totals = [];

        if ($type === 'all' || $type === 'lore') {
            $totals['lore'] = $this->translateLore($force, $limit, $slug);
        }
        if ($type === 'all' || $type === 'abilities') {
            $totals['abilities'] = $this->translateAbilities($force, $limit, $slug);
        }
        if ($type === 'all' || $type === 'furniture') {
            $totals['furniture'] = $this->translateFurniture($force, $limit);
        }
        if ($type === 'all' || $type === 'guides') {
            $totals['guides'] = $this->translateGuides($force, $limit);
        }

        $this->newLine();
        $this->info('Resumo:');
        foreach ($totals as $kind => $n) {
            $this->line("  • {$kind}: {$n} itens traduzidos");
        }

        return self::SUCCESS;
    }

    private function translateLore(bool $force, ?int $limit, ?string $slug): int
    {
        $q = Hero::query()->whereNotNull('lore');
        if ($slug) {
            $q->where('slug', $slug);
        }
        if (! $force) {
            $q->whereNull('lore_pt');
        }
        if ($limit) {
            $q->limit($limit);
        }

        $heroes = $q->get();
        $count = 0;

        foreach ($heroes as $i => $hero) {
            $translated = $this->safeTranslate($hero->lore, "lore[{$hero->slug}]");
            if ($translated !== null) {
                $hero->update(['lore_pt' => $translated]);
                $count++;
                $this->line("  <fg=green>+</> [{$i}/".count($heroes)."] lore: {$hero->slug}");
            }
        }

        return $count;
    }

    private function translateAbilities(bool $force, ?int $limit, ?string $slug): int
    {
        $q = HeroAbility::query();
        if ($slug) {
            $q->whereHas('hero', fn ($sub) => $sub->where('slug', $slug));
        }
        if (! $force) {
            $q->where(function ($w) {
                $w->whereNull('name_pt')->orWhereNull('description_pt');
            });
        }
        if ($limit) {
            $q->limit($limit);
        }

        $abilities = $q->with('hero:id,slug')->get();
        $count = 0;

        foreach ($abilities as $i => $a) {
            $updates = [];
            if ($force || $a->name_pt === null) {
                $name = $this->safeTranslate($a->name, 'ability.name');
                if ($name !== null) {
                    $updates['name_pt'] = $name;
                }
            }
            if ($a->description && ($force || $a->description_pt === null)) {
                $desc = $this->safeTranslate($a->description, "ability.desc[{$a->id}]");
                if ($desc !== null) {
                    $updates['description_pt'] = $desc;
                }
            }
            if (! empty($updates)) {
                $a->update($updates);
                $count++;
                $heroSlug = $a->hero?->slug ?? "h{$a->hero_id}";
                $this->line("  <fg=green>+</> [{$i}/".count($abilities)."] ability: {$heroSlug} » {$a->name}");
            }
        }

        return $count;
    }

    private function translateFurniture(bool $force, ?int $limit): int
    {
        $q = HeroFurnitureAbility::query();
        if (! $force) {
            $q->where(function ($w) {
                $w->whereNull('name_pt')
                    ->orWhereNull('f3_description_pt')
                    ->orWhereNull('f9_description_pt');
            });
        }
        if ($limit) {
            $q->limit($limit);
        }

        $items = $q->with('hero:id,slug')->get();
        $count = 0;

        foreach ($items as $i => $item) {
            $updates = [];
            if ($force || $item->name_pt === null) {
                $n = $this->safeTranslate($item->name, 'furniture.name');
                if ($n !== null) {
                    $updates['name_pt'] = $n;
                }
            }
            if ($item->f3_description && ($force || $item->f3_description_pt === null)) {
                $d = $this->safeTranslate($item->f3_description, 'furniture.f3');
                if ($d !== null) {
                    $updates['f3_description_pt'] = $d;
                }
            }
            if ($item->f9_description && ($force || $item->f9_description_pt === null)) {
                $d = $this->safeTranslate($item->f9_description, 'furniture.f9');
                if ($d !== null) {
                    $updates['f9_description_pt'] = $d;
                }
            }
            if (! empty($updates)) {
                $item->update($updates);
                $count++;
                $heroSlug = $item->hero?->slug ?? "h{$item->hero_id}";
                $this->line("  <fg=green>+</> [{$i}/".count($items)."] furniture: {$heroSlug}");
            }
        }

        return $count;
    }

    private function translateGuides(bool $force, ?int $limit): int
    {
        $q = Guide::query();
        if (! $force) {
            $q->where(function ($w) {
                $w->whereNull('title_pt')
                    ->orWhereNull('summary_pt')
                    ->orWhereNull('content_pt');
            });
        }
        if ($limit) {
            $q->limit($limit);
        }

        $guides = $q->get();
        $count = 0;

        foreach ($guides as $i => $g) {
            $updates = [];
            if ($force || $g->title_pt === null) {
                $t = $this->safeTranslate($g->title, 'guide.title');
                if ($t !== null) {
                    $updates['title_pt'] = $t;
                }
            }
            if ($g->summary && ($force || $g->summary_pt === null)) {
                $s = $this->safeTranslate($g->summary, 'guide.summary');
                if ($s !== null) {
                    $updates['summary_pt'] = $s;
                }
            }
            if ($g->content && ($force || $g->content_pt === null)) {
                $c = $this->safeTranslate($g->content, "guide.content[{$g->slug}]");
                if ($c !== null) {
                    $updates['content_pt'] = $c;
                }
            }
            if (! empty($updates)) {
                $g->update($updates);
                $count++;
                $this->line("  <fg=green>+</> [{$i}/".count($guides)."] guide: {$g->slug}");
            }
        }

        return $count;
    }

    private function safeTranslate(string $input, string $contextLabel): ?string
    {
        if (trim($input) === '') {
            return null;
        }

        try {
            $result = $this->translator->translate($input);
            usleep(200_000); // throttle 200ms entre calls

            return $result;
        } catch (Throwable $e) {
            $this->line("  <fg=red>!</> falha {$contextLabel}: {$e->getMessage()}");

            return null;
        }
    }
}
