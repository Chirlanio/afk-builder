<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BackupController extends Controller
{
    /**
     * Versão do schema do backup. Bump quando o formato mudar de modo
     * que um import precise saber qual layout esperar.
     */
    public const SCHEMA_VERSION = 1;

    public function show(Request $request): Response
    {
        $user = $request->user();

        $summary = [
            'roster_count' => $user->userHeroes()->count(),
            'formations_count' => $user->formations()->count(),
            'tier_lists_count' => $user->tierLists()->count(),
            'formation_tags_count' => $user->formationTags()->count(),
            'favorites_count' => $user->favoriteHeroes()->count(),
            'scoring_weight_presets_count' => $user->scoringWeightPresets()->count(),
        ];

        return Inertia::render('settings/backup', [
            'summary' => $summary,
            'schema_version' => self::SCHEMA_VERSION,
        ]);
    }

    /**
     * Gera o JSON com todos os dados do user. Stream pra não consumir muita memória
     * em backups grandes (rosters com centenas de heroes + dezenas de formações).
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
        return [
            'schema_version' => self::SCHEMA_VERSION,
            'exported_at' => now()->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale,
            ],
            'roster' => $this->buildRoster($user),
            'formations' => $this->buildFormations($user),
            'tier_lists' => $this->buildTierLists($user),
            'formation_tags' => $this->buildFormationTags($user),
            'favorite_hero_slugs' => $this->buildFavoriteSlugs($user),
            'scoring_weight_presets' => $this->buildScoringWeightPresets($user),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildRoster(User $user): array
    {
        return $user->userHeroes()
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
                'weapon_tier' => $uh->weapon_tier,
                'weapon_level' => $uh->weapon_level,
                'helmet_tier' => $uh->helmet_tier,
                'helmet_level' => $uh->helmet_level,
                'chest_tier' => $uh->chest_tier,
                'chest_level' => $uh->chest_level,
                'boots_tier' => $uh->boots_tier,
                'boots_level' => $uh->boots_level,
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
    private function buildFormations(User $user): array
    {
        return $user->formations()
            ->with([
                'challengeType:id,code',
                'beast:id,slug',
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
                'beast_slug' => $f->beast?->slug,
                'scoring_weights_override' => $f->scoring_weights_override,
                'tag_names' => $f->tags->pluck('name')->all(),
                'teams' => $f->teams->map(fn ($t) => [
                    'position' => $t->position,
                    'name' => $t->name,
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
    private function buildTierLists(User $user): array
    {
        return $user->tierLists()
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
    private function buildFormationTags(User $user): array
    {
        return $user->formationTags()
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
    private function buildFavoriteSlugs(User $user): array
    {
        return $user->favoriteHeroes()
            ->pluck('heroes.slug')
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildScoringWeightPresets(User $user): array
    {
        return $user->scoringWeightPresets()
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
}
