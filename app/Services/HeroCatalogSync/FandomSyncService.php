<?php

namespace App\Services\HeroCatalogSync;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroAbility;
use App\Models\HeroClass;
use App\Models\SyncLog;
use App\Models\Tier;
use Illuminate\Support\Str;
use Throwable;

class FandomSyncService
{
    /**
     * Campos que a wiki deixa em branco no infobox, por título da página.
     * Só preenche o que veio vazio — o que a wiki trouxer prevalece.
     * Fonte: a descrição do próprio herói na página (2026-10-18). Ankhira e
     * Gourgue ficam de fora: a wiki não dá pistas da classe deles.
     */
    private const INFOBOX_FIXES = [
        'Angelo' => ['class_code' => 'support'],            // "sustained healing for his team"
        'Arden' => ['class_code' => 'support'],             // controle + escudo mágico nos aliados
        'Golus' => ['class_code' => 'tank'],                // "durable strength-based tank hero"
        'Bronn' => ['tier_code' => 'ascended'],             // "warrior" no infobox; sem rarity (herói novo)
        'Draconis Archer' => ['class_code' => 'ranger'],
        'Draconis Warrior' => ['class_code' => 'warrior'],
        'Dreaf' => ['class_code' => 'mage'],                // dano em área por veneno, inteligência
        'Eletha' => ['class_code' => 'warrior'],            // "strength-based fighter"
    ];

    public function __construct(
        private readonly FandomWikiClient $client,
        private readonly WikitextHeroParser $parser,
    ) {}

    public function syncAll(SyncOptions $options): SyncResult
    {
        $log = $options->dryRun
            ? null
            : SyncLog::create([
                'source' => 'fandom',
                'status' => 'running',
                'started_at' => now(),
            ]);

        try {
            $titles = $this->client->listHeroPageTitles();

            if ($options->limit !== null) {
                $titles = array_slice($titles, 0, $options->limit);
            }

            $result = $this->syncTitles($titles, $options);

            if ($log) {
                $log->update([
                    'status' => count($result->errors) > 0 ? 'partial' : 'success',
                    'finished_at' => now(),
                    'heroes_seen' => $result->seen,
                    'heroes_created' => $result->created,
                    'heroes_updated' => $result->updated,
                    'new_hero_slugs' => $result->newHeroSlugs,
                    'error_message' => count($result->errors) > 0
                        ? substr(implode("\n", array_slice($result->errors, 0, 50)), 0, 65000)
                        : null,
                ]);
            }

            return $result;
        } catch (Throwable $e) {
            if ($log) {
                $log->update([
                    'status' => 'failed',
                    'finished_at' => now(),
                    'error_message' => $e->getMessage(),
                ]);
            }
            throw $e;
        }
    }

    public function syncSingleTitle(string $title, SyncOptions $options): SyncResult
    {
        return $this->syncTitles([$title], $options);
    }

    /**
     * @param  array<int, string>  $titles
     */
    private function syncTitles(array $titles, SyncOptions $options): SyncResult
    {
        $factionMap = Faction::pluck('id', 'code')->all();
        $classMap = HeroClass::pluck('id', 'code')->all();
        $tierMap = Tier::pluck('id', 'code')->all();

        $seen = 0;
        $created = 0;
        $updated = 0;
        $newSlugs = [];
        $skipped = [];
        $errors = [];
        $total = count($titles);

        foreach ($titles as $title) {
            $seen++;

            try {
                $wikitext = $this->client->fetchWikitext($title);

                if ($wikitext === null) {
                    $skipped[] = "{$title}: no wikitext";
                    $this->reportProgress($options, $seen, $total, $title, 'skipped');

                    continue;
                }

                $parsed = $this->parser->parse($wikitext);

                if ($parsed !== null) {
                    foreach (self::INFOBOX_FIXES[$title] ?? [] as $field => $value) {
                        $parsed[$field] ??= $value;
                    }
                }

                if ($parsed === null) {
                    $skipped[] = "{$title}: not a hero page";
                    $this->reportProgress($options, $seen, $total, $title, 'skipped');

                    continue;
                }

                $factionId = $parsed['faction_code'] ? ($factionMap[$parsed['faction_code']] ?? null) : null;
                $classId = $parsed['class_code'] ? ($classMap[$parsed['class_code']] ?? null) : null;
                $tierId = $parsed['tier_code'] ? ($tierMap[$parsed['tier_code']] ?? null) : null;

                if (! $factionId || ! $classId || ! $tierId) {
                    $missing = [];
                    if (! $factionId) {
                        $missing[] = "faction='{$parsed['faction_code']}'";
                    }
                    if (! $classId) {
                        $missing[] = "class='{$parsed['class_code']}'";
                    }
                    if (! $tierId) {
                        $missing[] = "tier='{$parsed['tier_code']}'";
                    }
                    $skipped[] = "{$title}: missing mapping for ".implode(', ', $missing);
                    $this->reportProgress($options, $seen, $total, $title, 'skipped');

                    continue;
                }

                $portraitUrl = $parsed['image_filename']
                    ? 'https://afk-arena.fandom.com/wiki/Special:Filepath/'.rawurlencode($parsed['image_filename'])
                    : null;

                $sourceUrl = 'https://afk-arena.fandom.com/wiki/'.rawurlencode(str_replace(' ', '_', $title));

                $slug = Str::slug($title);

                if ($slug === '') {
                    $skipped[] = "{$title}: could not derive slug";
                    $this->reportProgress($options, $seen, $total, $title, 'skipped');

                    continue;
                }

                $existing = Hero::where('slug', $slug)->first();

                $lore = $this->parser->parseLore($wikitext);
                $abilities = $this->parser->parseAbilities($wikitext);

                $catalogFields = [
                    'name' => $parsed['name'],
                    'title' => $parsed['title'],
                    'faction_id' => $factionId,
                    'hero_class_id' => $classId,
                    'tier_id' => $tierId,
                    'gender' => $parsed['gender'],
                    'type' => $parsed['type'],
                    'role_label' => $parsed['role_label'],
                    'portrait_url' => $portraitUrl,
                    'source_url' => $sourceUrl,
                    'lore' => $lore,
                ];

                if ($existing) {
                    if (! $options->dryRun) {
                        $existing->update($catalogFields);
                        $this->syncAbilities($existing, $abilities);
                    }
                    $updated++;
                    $this->reportProgress($options, $seen, $total, $title, 'updated');
                } else {
                    if (! $options->dryRun) {
                        $hero = Hero::create([
                            'slug' => $slug,
                            ...$catalogFields,
                            'is_active' => $options->initial,
                            'pending_review' => ! $options->initial,
                        ]);
                        $this->syncAbilities($hero, $abilities);
                    }
                    $created++;
                    $newSlugs[] = $slug;
                    $this->reportProgress($options, $seen, $total, $title, 'created');
                }
            } catch (Throwable $e) {
                $errors[] = "{$title}: {$e->getMessage()}";
                $this->reportProgress($options, $seen, $total, $title, 'error');
            }
        }

        return new SyncResult($seen, $created, $updated, $newSlugs, $skipped, $errors, $options->dryRun);
    }

    private function reportProgress(SyncOptions $options, int $current, int $total, string $title, string $action): void
    {
        if ($options->onProgress) {
            ($options->onProgress)($current, $total, $title, $action);
        }
    }

    /**
     * @param  array<int, array{name: string, ascension_unlock: string|null, description: string|null, display_order: int, type: string}>  $abilities
     */
    public function syncAbilities(Hero $hero, array $abilities): void
    {
        $hero->abilities()->delete();

        foreach ($abilities as $a) {
            HeroAbility::create([
                'hero_id' => $hero->id,
                'name' => $a['name'],
                'type' => $a['type'],
                'display_order' => $a['display_order'],
                'ascension_unlock' => $a['ascension_unlock'],
                'description' => $a['description'],
            ]);
        }
    }
}
