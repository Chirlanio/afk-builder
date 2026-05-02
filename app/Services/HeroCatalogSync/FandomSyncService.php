<?php

namespace App\Services\HeroCatalogSync;

use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\SyncLog;
use App\Models\Tier;
use Illuminate\Support\Str;
use Throwable;

class FandomSyncService
{
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
                    if (! $factionId) $missing[] = "faction='{$parsed['faction_code']}'";
                    if (! $classId) $missing[] = "class='{$parsed['class_code']}'";
                    if (! $tierId) $missing[] = "tier='{$parsed['tier_code']}'";
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

                $catalogFields = [
                    'name' => $parsed['name'],
                    'title' => $parsed['title'],
                    'faction_id' => $factionId,
                    'hero_class_id' => $classId,
                    'tier_id' => $tierId,
                    'gender' => $parsed['gender'],
                    'role_label' => $parsed['role_label'],
                    'portrait_url' => $portraitUrl,
                    'source_url' => $sourceUrl,
                ];

                if ($existing) {
                    if (! $options->dryRun) {
                        $existing->update($catalogFields);
                    }
                    $updated++;
                    $this->reportProgress($options, $seen, $total, $title, 'updated');
                } else {
                    if (! $options->dryRun) {
                        Hero::create([
                            'slug' => $slug,
                            ...$catalogFields,
                            'is_active' => $options->initial,
                            'pending_review' => ! $options->initial,
                        ]);
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
}
