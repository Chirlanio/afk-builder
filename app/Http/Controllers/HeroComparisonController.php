<?php

namespace App\Http\Controllers;

use App\Models\UserHero;
use App\Models\UserResource;
use App\Services\Optimization\InvestmentPlanner;
use App\Services\Optimization\PowerCalculator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HeroComparisonController extends Controller
{
    public const MIN_HEROES = 2;
    public const MAX_HEROES = 4;

    public function index(Request $request, PowerCalculator $power, InvestmentPlanner $planner): Response
    {
        $user = $request->user();

        $data = $request->validate([
            'heroes' => ['nullable', 'array', 'max:'.self::MAX_HEROES],
            'heroes.*' => ['string', 'regex:/^[a-z0-9-]+$/'],
        ]);

        $slugs = collect($data['heroes'] ?? [])->unique()->values()->all();

        $userHeroes = empty($slugs)
            ? collect()
            : $user->userHeroes()
                ->whereHas('hero', fn ($q) => $q->whereIn('slug', $slugs))
                ->with([
                    'hero:id,slug,name,portrait_url,faction_id,hero_class_id,tier_id,type',
                    'hero.faction:id,code,name_pt',
                    'hero.heroClass:id,code,name_pt',
                    'hero.tier:id,code,name_pt',
                    'hero.stats',
                    'artifact:id,name,attributes',
                    'collection:id,name,hp_bonus,attack_bonus,defense_bonus',
                ])
                ->get();

        // Carrega recursos pra alimentar planForHero (sem criar registro inútil)
        $resources = $user->resources;

        // Mantém a ordem que veio na query string (pra preservar a escolha do user)
        $byUserHero = $userHeroes->keyBy(fn (UserHero $uh) => $uh->hero?->slug);

        $heroes = [];
        foreach ($slugs as $slug) {
            $uh = $byUserHero->get($slug);
            if ($uh === null) {
                continue;
            }
            $heroes[] = $this->buildHeroPayload($user, $uh, $power, $planner, $resources);
        }

        // Lista pro autocomplete: todo o roster, pra adicionar mais cards
        $rosterOptions = $user->userHeroes()
            ->with('hero:id,slug,name,faction_id,hero_class_id,tier_id')
            ->with('hero.faction:id,code')
            ->with('hero.heroClass:id,code')
            ->with('hero.tier:id,code')
            ->get()
            ->map(fn (UserHero $uh) => [
                'slug' => $uh->hero?->slug,
                'name' => $uh->hero?->name,
                'faction_code' => $uh->hero?->faction?->code,
                'class_code' => $uh->hero?->heroClass?->code,
                'tier_code' => $uh->hero?->tier?->code,
            ])
            ->filter(fn ($r) => $r['slug'] !== null)
            ->values()
            ->all();

        return Inertia::render('roster/compare', [
            'heroes' => $heroes,
            'rosterOptions' => $rosterOptions,
            'minHeroes' => self::MIN_HEROES,
            'maxHeroes' => self::MAX_HEROES,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildHeroPayload(
        \App\Models\User $user,
        UserHero $uh,
        PowerCalculator $power,
        InvestmentPlanner $planner,
        ?UserResource $resources,
    ): array {
        $totalPower = (int) round($power->forUserHero($uh, $uh->hero));
        $statContrib = (int) round($power->statContribution($uh->hero?->stats));
        $equipContrib = (int) round($power->totalEquipmentContribution($uh));
        $artifactContrib = (int) round($power->artifactContribution($uh->artifact ?? null, $uh->artifact_star_level));
        $collectionContrib = (int) round($power->collectionContribution($uh->collection ?? null));

        // Próximas 3 sugestões pelo planner do hero específico
        $upgrades = collect($planner->planForHero($user, $uh, null, $resources))
            ->take(3)
            ->map(fn ($s) => [
                'kind' => $s['kind'],
                'from' => $s['from'],
                'to' => $s['to'],
                'power_gain' => $s['power_gain'],
                'cost' => $s['cost'],
                'score' => $s['score'],
                'reasons' => $s['reasons'],
                'resource_cost' => $s['resource_cost'],
                'affordable' => $s['affordable'],
            ])
            ->all();

        return [
            'user_hero_id' => $uh->id,
            'hero_id' => $uh->hero_id,
            'slug' => $uh->hero?->slug,
            'name' => $uh->hero?->name,
            'portrait_url' => $uh->hero?->portrait_url,
            'faction_code' => $uh->hero?->faction?->code,
            'faction_name_pt' => $uh->hero?->faction?->name_pt,
            'class_code' => $uh->hero?->heroClass?->code,
            'tier_code' => $uh->hero?->tier?->code,
            'tier_name_pt' => $uh->hero?->tier?->name_pt,
            'type' => $uh->hero?->type,
            // Estado atual
            'level' => (int) $uh->level,
            'ascension_value' => (int) $uh->ascension_value,
            'ascension_code' => $uh->ascension_code,
            'signature_item' => (int) $uh->signature_item,
            'furniture' => (int) $uh->furniture,
            'engraving' => (int) $uh->engraving,
            // Equipamento
            'equipment' => [
                'weapon_tier' => $uh->weapon_tier,
                'weapon_level' => $uh->weapon_level,
                'helmet_tier' => $uh->helmet_tier,
                'helmet_level' => $uh->helmet_level,
                'chest_tier' => $uh->chest_tier,
                'chest_level' => $uh->chest_level,
                'boots_tier' => $uh->boots_tier,
                'boots_level' => $uh->boots_level,
            ],
            // Artefato / Coleção
            'artifact' => $uh->artifact ? [
                'name' => $uh->artifact->name,
                'stars' => (int) ($uh->artifact_star_level ?? 0),
            ] : null,
            'collection' => $uh->collection ? [
                'name' => $uh->collection->name,
            ] : null,
            // Power breakdown
            'power' => [
                'total' => $totalPower,
                'stat_contribution' => $statContrib,
                'equipment_contribution' => $equipContrib,
                'artifact_contribution' => $artifactContrib,
                'collection_contribution' => $collectionContrib,
            ],
            'upgrades' => $upgrades,
        ];
    }
}
