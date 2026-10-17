<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\Beast;
use App\Models\ChallengeType;
use App\Models\FormationTag;
use App\Models\GameAccount;
use App\Models\Hero;
use App\Models\ScoringWeightPreset;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    /**
     * Versão do schema do backup. Bump quando o formato mudar de modo
     * que um import precise saber qual layout esperar.
     *
     * v1 — single-account (dados soltos no topo).
     * v2 — multi-account (dados aninhados em `accounts[]`); mantém retro-
     *      compat com v1 no import.
     */
    public const SCHEMA_VERSION = 2;

    public function show(Request $request): Response
    {
        $user = $request->user();

        $accounts = $user->gameAccounts()->with([])->get()->map(fn (GameAccount $a) => [
            'id' => $a->id,
            'name' => $a->name,
            'is_default' => $a->is_default,
            'roster_count' => $a->userHeroes()->count(),
            'formations_count' => $a->formations()->count(),
            'tier_lists_count' => $a->tierLists()->count(),
            'formation_tags_count' => $a->formationTags()->count(),
            'favorites_count' => $a->favoriteHeroes()->count(),
            'scoring_weight_presets_count' => $a->scoringWeightPresets()->count(),
        ])->values()->all();

        return Inertia::render('settings/backup', [
            'accounts' => $accounts,
            'schema_version' => self::SCHEMA_VERSION,
        ]);
    }

    /**
     * Gera o JSON com todos os dados do user (todas as contas). Stream
     * pra não consumir muita memória em backups grandes.
     */
    public function download(Request $request): StreamedResponse
    {
        $user = $request->user();
        $payload = $this->buildPayload($user);

        $filename = sprintf('afk-builder-backup-%s-%s.json', $user->id, now()->format('Y-m-d-His'));

        return response()->streamDownload(function () use ($payload) {
            echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $filename, [
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildPayload(User $user): array
    {
        $accounts = $user->gameAccounts()
            ->orderByDesc('is_default')
            ->orderBy('id')
            ->get();

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'exported_at' => now()->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale,
            ],
            'accounts' => $accounts->map(fn (GameAccount $a) => $this->buildAccountPayload($a))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function buildAccountPayload(GameAccount $account): array
    {
        return [
            'name' => $account->name,
            'server' => $account->server,
            'is_default' => $account->is_default,
            'avatar_hero_slug' => $account->avatarHero?->slug,
            'roster' => $this->buildRoster($account),
            'formations' => $this->buildFormations($account),
            'tier_lists' => $this->buildTierLists($account),
            'formation_tags' => $this->buildFormationTags($account),
            'favorite_hero_slugs' => $this->buildFavoriteSlugs($account),
            'scoring_weight_presets' => $this->buildScoringWeightPresets($account),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildRoster(GameAccount $account): array
    {
        return $account->userHeroes()
            ->with('hero:id,slug')
            ->get()
            ->map(fn ($uh) => [
                'hero_slug' => $uh->hero?->slug,
                'level' => $uh->level,
                'ascension_code' => $uh->ascension_code,
                'ascension_value' => $uh->ascension_value,
                'signature_item' => $uh->signature_item,
                'furniture' => $uh->furniture,
                'engraving' => $uh->engraving,
                'weapon_equipment_id' => $uh->weapon_equipment_id,
                'weapon_equipment_level' => $uh->weapon_equipment_level,
                'helmet_equipment_id' => $uh->helmet_equipment_id,
                'helmet_equipment_level' => $uh->helmet_equipment_level,
                'chest_equipment_id' => $uh->chest_equipment_id,
                'chest_equipment_level' => $uh->chest_equipment_level,
                'boots_equipment_id' => $uh->boots_equipment_id,
                'boots_equipment_level' => $uh->boots_equipment_level,
                'artifact_id' => $uh->artifact_id,
                'artifact_star_level' => $uh->artifact_star_level,
                'collection_id' => $uh->collection_id,
                'notes' => $uh->notes,
                'hp' => $uh->hp,
                'attack' => $uh->attack,
                'defense' => $uh->defense,
                'crit_rate' => $uh->crit_rate,
                'crit_amp' => $uh->crit_amp,
                'accuracy' => $uh->accuracy,
                'dodge' => $uh->dodge,
                'haste' => $uh->haste,
            ])
            ->filter(fn ($r) => $r['hero_slug'] !== null)
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildFormations(GameAccount $account): array
    {
        return $account->formations()
            ->with([
                'challengeType:id,code',
                'teams.beast:id,slug',
                'teams.teamHeroes.hero:id,slug',
                'tags:id,name,color',
            ])
            ->get()
            ->map(fn ($f) => [
                'name' => $f->name,
                'challenge_type_code' => $f->challengeType?->code,
                'mode' => $f->mode,
                'teams_count' => $f->teams_count,
                'notes' => $f->notes,
                'scoring_weights_override' => $f->scoring_weights_override,
                'tag_names' => $f->tags->pluck('name')->all(),
                'teams' => $f->teams->map(fn ($t) => [
                    'position' => $t->position,
                    'name' => $t->name,
                    'beast_slug' => $t->beast?->slug,
                    'beast_level' => $t->beast_level,
                    'heroes' => $t->teamHeroes->map(fn ($th) => [
                        'slot' => $th->slot,
                        'hero_slug' => $th->hero?->slug,
                        'row' => $th->row,
                    ])->filter(fn ($h) => $h['hero_slug'] !== null)->values()->all(),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildTierLists(GameAccount $account): array
    {
        return $account->tierLists()
            ->with(['rows.items.hero:id,slug'])
            ->get()
            ->map(fn ($tl) => [
                'name' => $tl->name,
                'description' => $tl->description,
                'rows' => $tl->rows->map(fn ($row) => [
                    'label' => $row->label,
                    'color' => $row->color,
                    'position' => $row->position,
                    'items' => $row->items->map(fn ($item) => [
                        'hero_slug' => $item->hero?->slug,
                        'position' => $item->position,
                    ])->filter(fn ($i) => $i['hero_slug'] !== null)->values()->all(),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildFormationTags(GameAccount $account): array
    {
        return $account->formationTags()
            ->orderBy('name')
            ->get(['name', 'color', 'challenge_type_id'])
            ->map(fn ($t) => [
                'name' => $t->name,
                'color' => $t->color,
                'challenge_type_id' => $t->challenge_type_id,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<int, string>
     */
    private function buildFavoriteSlugs(GameAccount $account): array
    {
        return $account->favoriteHeroes()
            ->pluck('heroes.slug')
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildScoringWeightPresets(GameAccount $account): array
    {
        return $account->scoringWeightPresets()
            ->orderBy('name')
            ->get(['name', 'challenge_type_id', 'weights'])
            ->map(fn ($p) => [
                'name' => $p->name,
                'challenge_type_id' => $p->challenge_type_id,
                'weights' => $p->weights,
            ])
            ->values()
            ->all();
    }

    /**
     * Importa o JSON gerado por download() de volta.
     *
     * Estratégias:
     * - `replace`: apaga TODAS as contas do user e cria novas do payload
     * - `merge`: preserva contas existentes; cria novas com nome sufixado se colidir
     *
     * Retro-compat: v1 (single-account) é aceito. Trata como se fosse v2
     * com uma única conta "Conta Importada".
     */
    public function import(Request $request): RedirectResponse
    {
        $request->validate([
            'file' => ['required', 'file', 'mimetypes:application/json,text/plain', 'max:5120'], // 5MB
            'strategy' => ['required', 'string', 'in:replace,merge'],
        ]);

        $contents = $request->file('file')->get();
        $data = json_decode($contents, true);

        if (! is_array($data)) {
            return back()->withErrors(['file' => 'Arquivo não é JSON válido.']);
        }

        $version = (int) ($data['schema_version'] ?? 0);
        if (! in_array($version, [1, 2], true)) {
            return back()->withErrors([
                'file' => "Schema version não suportada: {$version}. Aceito: 1, 2.",
            ]);
        }

        $accountsPayload = $this->normalizeAccountsPayload($data, $version);
        if ($accountsPayload === null) {
            return back()->withErrors(['file' => 'Estrutura do JSON inválida.']);
        }

        $user = $request->user();
        $strategy = $request->input('strategy');

        $summary = DB::transaction(function () use ($user, $accountsPayload, $strategy) {
            if ($strategy === 'replace') {
                $user->gameAccounts()->delete();
            }

            $existingNames = $strategy === 'merge'
                ? $user->gameAccounts()->pluck('name')->all()
                : [];

            $totals = ['accounts' => 0, 'roster' => ['imported' => 0, 'skipped' => 0], 'formations' => ['imported' => 0, 'skipped' => 0], 'tier_lists' => ['imported' => 0, 'skipped' => 0], 'formation_tags' => ['imported' => 0, 'skipped' => 0], 'favorites' => ['imported' => 0, 'skipped' => 0], 'scoring_weight_presets' => ['imported' => 0, 'skipped' => 0]];

            foreach ($accountsPayload as $accountData) {
                $name = $this->resolveUniqueAccountName($accountData['name'], $existingNames);
                $existingNames[] = $name;

                $account = $user->gameAccounts()->create([
                    'name' => $name,
                    'server' => $accountData['server'] ?? null,
                    'is_default' => ! empty($accountData['is_default']),
                ]);
                $totals['accounts']++;

                // Todos os imports abaixo são "replace" internamente na conta recém-criada
                // (é uma conta nova/limpa em ambos strategies replace e merge).
                $totals['roster']['imported'] += $this->importRoster($account, $accountData['roster'] ?? [], 'replace')['imported'];
                $totals['formation_tags']['imported'] += $this->importFormationTags($account, $accountData['formation_tags'] ?? [], 'replace')['imported'];
                $totals['formations']['imported'] += $this->importFormations($account, $accountData['formations'] ?? [], 'replace')['imported'];
                $totals['tier_lists']['imported'] += $this->importTierLists($account, $accountData['tier_lists'] ?? [], 'replace')['imported'];
                $totals['favorites']['imported'] += $this->importFavorites($account, $accountData['favorite_hero_slugs'] ?? [], 'replace')['imported'];
                $totals['scoring_weight_presets']['imported'] += $this->importScoringWeightPresets($account, $accountData['scoring_weight_presets'] ?? [], 'replace')['imported'];
            }

            return $totals;
        });

        $msg = sprintf(
            'Backup importado (%s, v%d): %d conta(s), %d heroes, %d formações, %d tier lists, %d tags, %d favoritos, %d presets.',
            $strategy,
            $version,
            $summary['accounts'],
            $summary['roster']['imported'],
            $summary['formations']['imported'],
            $summary['tier_lists']['imported'],
            $summary['formation_tags']['imported'],
            $summary['favorites']['imported'],
            $summary['scoring_weight_presets']['imported'],
        );

        return back()->with('status', $msg);
    }

    /**
     * Normaliza o payload pro shape v2 (accounts[]). Backup v1 vira 1
     * account "Conta Importada" com dados soltos.
     *
     * @return array<int, array<string, mixed>>|null
     */
    private function normalizeAccountsPayload(array $data, int $version): ?array
    {
        if ($version === 2) {
            if (! isset($data['accounts']) || ! is_array($data['accounts'])) {
                return null;
            }

            return $data['accounts'];
        }

        // v1 — wrap num único account
        return [[
            'name' => 'Conta Importada',
            'server' => null,
            'is_default' => false,
            'roster' => $data['roster'] ?? [],
            'formations' => $data['formations'] ?? [],
            'tier_lists' => $data['tier_lists'] ?? [],
            'formation_tags' => $data['formation_tags'] ?? [],
            'favorite_hero_slugs' => $data['favorite_hero_slugs'] ?? [],
            'scoring_weight_presets' => $data['scoring_weight_presets'] ?? [],
        ]];
    }

    /**
     * Sufixa " (importada)", " (importada 2)", etc até achar um nome que
     * não colide com contas existentes do user.
     *
     * @param  array<int, string>  $existingNames
     */
    private function resolveUniqueAccountName(string $desired, array $existingNames): string
    {
        if (! in_array($desired, $existingNames, true)) {
            return $desired;
        }

        $suffixed = "{$desired} (importada)";
        if (! in_array($suffixed, $existingNames, true)) {
            return $suffixed;
        }

        $i = 2;
        while (in_array("{$desired} (importada {$i})", $existingNames, true)) {
            $i++;
        }

        return "{$desired} (importada {$i})";
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{imported: int, skipped: int}
     */
    private function importRoster(GameAccount $account, array $rows, string $strategy): array
    {
        $imported = 0;
        $skipped = 0;
        $heroIdsBySlug = Hero::whereIn('slug', array_column($rows, 'hero_slug'))->pluck('id', 'slug');
        $existing = $strategy === 'merge'
            ? $account->userHeroes()->pluck('hero_id')->all()
            : [];

        foreach ($rows as $row) {
            $heroId = $heroIdsBySlug[$row['hero_slug'] ?? ''] ?? null;
            if (! $heroId) {
                $skipped++;

                continue;
            }
            if ($strategy === 'merge' && in_array($heroId, $existing, true)) {
                $skipped++;

                continue;
            }
            $account->userHeroes()->create([
                'hero_id' => $heroId,
                'level' => $row['level'] ?? 1,
                'ascension_code' => $row['ascension_code'] ?? null,
                'ascension_value' => $row['ascension_value'] ?? 0,
                'signature_item' => $row['signature_item'] ?? 0,
                'furniture' => $row['furniture'] ?? 0,
                'engraving' => $row['engraving'] ?? 0,
                'weapon_equipment_id' => $row['weapon_equipment_id'] ?? null,
                'weapon_equipment_level' => $row['weapon_equipment_level'] ?? 0,
                'helmet_equipment_id' => $row['helmet_equipment_id'] ?? null,
                'helmet_equipment_level' => $row['helmet_equipment_level'] ?? 0,
                'chest_equipment_id' => $row['chest_equipment_id'] ?? null,
                'chest_equipment_level' => $row['chest_equipment_level'] ?? 0,
                'boots_equipment_id' => $row['boots_equipment_id'] ?? null,
                'boots_equipment_level' => $row['boots_equipment_level'] ?? 0,
                'artifact_id' => $row['artifact_id'] ?? null,
                'artifact_star_level' => $row['artifact_star_level'] ?? null,
                'collection_id' => $row['collection_id'] ?? null,
                'notes' => $row['notes'] ?? null,
                'hp' => $row['hp'] ?? null,
                'attack' => $row['attack'] ?? null,
                'defense' => $row['defense'] ?? null,
                'crit_rate' => $row['crit_rate'] ?? null,
                'crit_amp' => $row['crit_amp'] ?? null,
                'accuracy' => $row['accuracy'] ?? null,
                'dodge' => $row['dodge'] ?? null,
                'haste' => $row['haste'] ?? null,
            ]);
            $imported++;
        }

        return compact('imported', 'skipped');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{imported: int, skipped: int}
     */
    private function importFormationTags(GameAccount $account, array $rows, string $strategy): array
    {
        $imported = 0;
        $skipped = 0;
        $existingNames = $strategy === 'merge'
            ? $account->formationTags()->pluck('name')->all()
            : [];

        foreach ($rows as $row) {
            $name = $row['name'] ?? null;
            if (! $name) {
                $skipped++;

                continue;
            }
            if ($strategy === 'merge' && in_array($name, $existingNames, true)) {
                $skipped++;

                continue;
            }
            $account->formationTags()->create([
                'name' => $name,
                'color' => in_array($row['color'] ?? '', FormationTag::COLORS, true)
                    ? $row['color']
                    : FormationTag::DEFAULT_COLOR,
                'challenge_type_id' => $row['challenge_type_id'] ?? null,
            ]);
            $imported++;
        }

        return compact('imported', 'skipped');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{imported: int, skipped: int}
     */
    private function importFormations(GameAccount $account, array $rows, string $strategy): array
    {
        $imported = 0;
        $skipped = 0;

        $allHeroSlugs = collect($rows)
            ->flatMap(fn ($f) => collect($f['teams'] ?? [])->flatMap(fn ($t) => collect($t['heroes'] ?? [])->pluck('hero_slug')))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $heroIdsBySlug = Hero::whereIn('slug', $allHeroSlugs)->pluck('id', 'slug');
        $challengeIdsByCode = ChallengeType::pluck('id', 'code');
        $beastIdsBySlug = Beast::pluck('id', 'slug');
        $tagsByName = $account->formationTags()->pluck('id', 'name');

        foreach ($rows as $row) {
            $challengeId = $challengeIdsByCode[$row['challenge_type_code'] ?? ''] ?? null;
            if (! $challengeId) {
                $skipped++;

                continue;
            }
            $formation = $account->formations()->create([
                'challenge_type_id' => $challengeId,
                'name' => $row['name'] ?? 'Importada',
                'mode' => $row['mode'] ?? 'target',
                'teams_count' => $row['teams_count'] ?? 1,
                'notes' => $row['notes'] ?? null,
                'scoring_weights_override' => $row['scoring_weights_override'] ?? null,
            ]);

            // Tags (best-effort: tags com name não encontrado são ignoradas)
            $tagIds = collect($row['tag_names'] ?? [])
                ->map(fn ($n) => $tagsByName[$n] ?? null)
                ->filter()
                ->all();
            if (! empty($tagIds)) {
                $formation->tags()->sync($tagIds);
            }

            // Fase 48: backfill backward-compat — backups antigos com beast_slug no top-level
            // são aplicados a todos os teams (mesma lógica da migration).
            $legacyBeastId = $beastIdsBySlug[$row['beast_slug'] ?? ''] ?? null;

            foreach ($row['teams'] ?? [] as $teamRow) {
                // Fase 48: beast_slug agora vem por team; fallback no legacy top-level se ausente.
                $teamBeastId = $beastIdsBySlug[$teamRow['beast_slug'] ?? ''] ?? $legacyBeastId;

                $team = $formation->teams()->create([
                    'position' => $teamRow['position'] ?? 1,
                    'name' => $teamRow['name'] ?? null,
                    'beast_id' => $teamBeastId,
                    'beast_level' => $teamRow['beast_level'] ?? Beast::MAX_LEVEL,
                ]);
                foreach ($teamRow['heroes'] ?? [] as $heroRow) {
                    $heroId = $heroIdsBySlug[$heroRow['hero_slug'] ?? ''] ?? null;
                    if (! $heroId) {
                        continue;
                    }
                    $team->teamHeroes()->create([
                        'hero_id' => $heroId,
                        'slot' => $heroRow['slot'] ?? 1,
                        'row' => $heroRow['row'] ?? null,
                    ]);
                }
            }
            $imported++;
        }

        return compact('imported', 'skipped');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{imported: int, skipped: int}
     */
    private function importTierLists(GameAccount $account, array $rows, string $strategy): array
    {
        $imported = 0;
        $skipped = 0;

        $allHeroSlugs = collect($rows)
            ->flatMap(fn ($tl) => collect($tl['rows'] ?? [])->flatMap(fn ($r) => collect($r['items'] ?? [])->pluck('hero_slug')))
            ->filter()
            ->unique()
            ->values()
            ->all();
        $heroIdsBySlug = Hero::whereIn('slug', $allHeroSlugs)->pluck('id', 'slug');

        foreach ($rows as $row) {
            $name = $row['name'] ?? null;
            if (! $name) {
                $skipped++;

                continue;
            }
            $tierList = $account->tierLists()->create([
                'name' => $name,
                'description' => $row['description'] ?? null,
            ]);

            foreach ($row['rows'] ?? [] as $tlRow) {
                $createdRow = $tierList->rows()->create([
                    'label' => $tlRow['label'] ?? '?',
                    'color' => $tlRow['color'] ?? 'amber',
                    'position' => $tlRow['position'] ?? 1,
                ]);
                foreach ($tlRow['items'] ?? [] as $item) {
                    $heroId = $heroIdsBySlug[$item['hero_slug'] ?? ''] ?? null;
                    if (! $heroId) {
                        continue;
                    }
                    $createdRow->items()->create([
                        'hero_id' => $heroId,
                        'position' => $item['position'] ?? 1,
                    ]);
                }
            }
            $imported++;
        }

        return compact('imported', 'skipped');
    }

    /**
     * @param  array<int, string>  $slugs
     * @return array{imported: int, skipped: int}
     */
    private function importFavorites(GameAccount $account, array $slugs, string $strategy): array
    {
        $heroIds = Hero::whereIn('slug', $slugs)->pluck('id')->all();
        $skipped = count($slugs) - count($heroIds);

        if ($strategy === 'replace') {
            $account->favoriteHeroes()->sync($heroIds);
        } else {
            $account->favoriteHeroes()->syncWithoutDetaching($heroIds);
        }

        return ['imported' => count($heroIds), 'skipped' => $skipped];
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{imported: int, skipped: int}
     */
    private function importScoringWeightPresets(GameAccount $account, array $rows, string $strategy): array
    {
        $imported = 0;
        $skipped = 0;
        $existingNames = $strategy === 'merge'
            ? $account->scoringWeightPresets()->pluck('name')->all()
            : [];

        foreach ($rows as $row) {
            $name = $row['name'] ?? null;
            if (! $name || ! is_array($row['weights'] ?? null)) {
                $skipped++;

                continue;
            }
            if ($strategy === 'merge' && in_array($name, $existingNames, true)) {
                $skipped++;

                continue;
            }

            // Sanitiza weights: só chaves válidas + valores no range
            $cleanWeights = collect($row['weights'])
                ->only(ScoringWeightPreset::WEIGHT_KEYS)
                ->map(fn ($v) => max(0.0, min(3.0, (float) $v)))
                ->all();

            $account->scoringWeightPresets()->create([
                'name' => $name,
                'challenge_type_id' => $row['challenge_type_id'] ?? null,
                'weights' => $cleanWeights,
            ]);
            $imported++;
        }

        return compact('imported', 'skipped');
    }
}
