<?php

namespace App\Http\Controllers;

use App\Models\Artifact;
use App\Models\Collection;
use App\Models\Equipment;
use App\Models\Faction;
use App\Models\Hero;
use App\Models\HeroClass;
use App\Models\HeroSynergy;
use App\Models\SignatureItemCost;
use App\Models\Tier;
use App\Services\ActiveGameAccount;
use App\Services\Optimization\PowerCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HeroController extends Controller
{
    public function __construct(private readonly ActiveGameAccount $active) {}

    public function index(Request $request): Response
    {
        $account = $this->active->get();

        $heroes = Hero::query()
            ->where('is_active', true)
            ->where('pending_review', false)
            ->orderBy('name')
            ->with('features:id,hero_id,tag,note')
            ->get([
                'id', 'slug', 'name', 'title',
                'faction_id', 'hero_class_id', 'tier_id',
                'gender', 'type', 'role_label', 'portrait_url',
            ])
            ->map(function (Hero $hero) {
                return array_merge($hero->toArray(), [
                    'is_awakened' => $hero->isAwakened(),
                ]);
            });

        $factions = Faction::orderBy('display_order')
            ->get(['id', 'code', 'name', 'name_pt', 'color_hex', 'display_order']);

        $heroClasses = HeroClass::orderBy('display_order')
            ->get(['id', 'code', 'name', 'name_pt', 'display_order']);

        $tiers = Tier::orderBy('display_order')
            ->get(['id', 'code', 'name', 'name_pt', 'max_level', 'display_order']);

        // Fase 49: payload do roster inclui campos de equipamento pra alimentar os filtros avançados
        // (artifact/collection presença + equipment tiers/levels). Cost-benefit aceitável: ~11 cols
        // extras × N heroes do user — leve, indexado, e evita N+1 quando user explora o catálogo.
        $roster = $account
            ->userHeroes()
            ->get([
                'id', 'hero_id', 'level', 'ascension_code', 'ascension_value',
                'signature_item', 'furniture', 'engraving', 'notes',
                'artifact_id', 'collection_id',
                'weapon_equipment_id', 'weapon_equipment_level',
                'helmet_equipment_id', 'helmet_equipment_level',
                'chest_equipment_id', 'chest_equipment_level',
                'boots_equipment_id', 'boots_equipment_level',
            ])
            ->keyBy('hero_id');

        // Fase 37: favoritos da conta ativa
        $favoriteHeroIds = $account
            ->favoriteHeroes()
            ->pluck('heroes.id')
            ->all();

        return Inertia::render('heroes/index', [
            'heroes' => $heroes,
            'factions' => $factions,
            'heroClasses' => $heroClasses,
            'tiers' => $tiers,
            'roster' => $roster,
            'favoriteHeroIds' => $favoriteHeroIds,
        ]);
    }

    /**
     * Permalink legacy: redireciona para /heroes?open={slug} que abre a modal unificada no índice.
     */
    public function show(string $slug): RedirectResponse
    {
        // Mantém URL antiga funcionando — o detalhe completo agora é exibido em modal no índice.
        return redirect()->route('heroes.index', ['open' => $slug]);
    }

    /**
     * Retorna JSON com os dados completos do herói para a HeroDetailModal carregar via axios.
     */
    public function details(Request $request, string $slug, PowerCalculator $power): JsonResponse
    {
        $hero = Hero::query()
            ->where('slug', $slug)
            ->where('is_active', true)
            ->with([
                'faction:id,code,name_pt,color_hex',
                'heroClass:id,code,name_pt',
                'tier:id,code,name_pt,max_level',
                'abilities' => fn ($q) => $q->orderBy('display_order'),
                'furnitureAbility',
                'stats',
                'ratings:id,hero_id,mode,stage,tier,source,source_url',
                'engravings:id,hero_id,level,label,description,source,source_url',
                'features:id,hero_id,tag,note,source_url',
            ])
            ->firstOrFail();

        $heroPayload = array_merge($hero->toArray(), [
            'is_awakened' => $hero->isAwakened(),
            'effective_capabilities' => $hero->effectiveCapabilities(),
        ]);

        $userHero = $this->active->get()
            ->userHeroes()
            ->with([
                'artifact:id,slug,name,kind,hero_class_id,description,portrait_url,max_stars',
                'collection:id,slug,name,description,hp_bonus,attack_bonus,defense_bonus,portrait_url',
                'weaponEquipment:id,name_pt,slot,quality_tier,faction_id,max_upgrade_level',
                'helmetEquipment:id,name_pt,slot,quality_tier,faction_id,max_upgrade_level',
                'chestEquipment:id,name_pt,slot,quality_tier,faction_id,max_upgrade_level',
                'bootsEquipment:id,name_pt,slot,quality_tier,faction_id,max_upgrade_level',
            ])
            ->where('hero_id', $hero->id)
            ->first([
                'id', 'hero_id', 'level', 'ascension_code', 'ascension_value',
                'signature_item', 'furniture', 'engraving',
                'weapon_equipment_id', 'weapon_equipment_level',
                'helmet_equipment_id', 'helmet_equipment_level',
                'chest_equipment_id', 'chest_equipment_level',
                'boots_equipment_id', 'boots_equipment_level',
                'artifact_id', 'artifact_star_level', 'collection_id', 'notes',
            ]);

        $userHeroPayload = null;
        if ($userHero) {
            $userHeroPayload = $userHero->toArray();
            $userHeroPayload['power_rating'] = (int) round($power->forUserHero($userHero, $hero));
        }

        $availableCollections = Collection::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'description', 'hp_bonus', 'attack_bonus', 'defense_bonus', 'portrait_url']);

        $availableArtifacts = Artifact::query()
            ->where('is_active', true)
            ->where(function ($q) use ($hero) {
                $q->where('kind', Artifact::KIND_DURA)
                    ->orWhere(function ($q2) use ($hero) {
                        $q2->where('kind', Artifact::KIND_CLASS)
                            ->where('hero_class_id', $hero->hero_class_id);
                    });
            })
            ->orderByRaw("CASE kind WHEN 'dura' THEN 0 ELSE 1 END")
            ->orderBy('name')
            ->get(['id', 'slug', 'name', 'kind', 'hero_class_id', 'description', 'attributes', 'powers', 'obtained_in', 'portrait_url', 'max_stars']);

        $synergyPartnerIds = HeroSynergy::query()
            ->where('hero_a_id', $hero->id)
            ->orWhere('hero_b_id', $hero->id)
            ->get(['hero_a_id', 'hero_b_id', 'weight', 'note'])
            ->map(fn ($s) => [
                'hero_id' => $s->hero_a_id === $hero->id ? $s->hero_b_id : $s->hero_a_id,
                'weight' => $s->weight,
                'note' => $s->note,
            ]);

        $partnerHeroes = Hero::query()
            ->whereIn('id', $synergyPartnerIds->pluck('hero_id'))
            ->where('is_active', true)
            ->with(['faction:id,code', 'heroClass:id,code', 'tier:id,code'])
            ->get(['id', 'slug', 'name', 'faction_id', 'hero_class_id', 'tier_id', 'portrait_url'])
            ->keyBy('id');

        $synergies = $synergyPartnerIds
            ->map(function (array $s) use ($partnerHeroes) {
                $partner = $partnerHeroes->get($s['hero_id']);
                if (! $partner) {
                    return null;
                }

                return [
                    'partner' => array_merge($partner->toArray(), [
                        'is_awakened' => $partner->isAwakened(),
                    ]),
                    'weight' => $s['weight'],
                    'note' => $s['note'],
                ];
            })
            ->filter()
            ->sortByDesc('weight')
            ->values();

        $signatureItemSummary = SignatureItemCost::summary(
            $userHero?->signature_item ?? 0,
            $hero->faction?->code,
        );

        $isFavorited = $this->active->get()
            ->favoriteHeroes()
            ->where('heroes.id', $hero->id)
            ->exists();

        // Equipamentos Míticos disponíveis pra esse hero. Dois filtros AND:
        //   1. faction_id: hero só vê items da sua própria faction (no jogo cada
        //      faction tem seu próprio loot table, e o bônus de 30% só vale com match)
        //   2. hero_type (strength/agility/intelligence): set apropriado pra
        //      arquétipo do hero — mages vão de Místico, warriors de Gaius, etc.
        $availableEquipments = Equipment::query()
            ->where('is_active', true)
            ->where('faction_id', $hero->faction_id)
            ->when($hero->type !== null, fn ($q) => $q->where('hero_type', $hero->type))
            ->with('faction:id,code,name_pt')
            ->orderBy('set_code')
            ->orderBy('slot')
            ->orderBy('quality_tier')
            ->get(['id', 'slug', 'name', 'name_pt', 'set_code', 'slot', 'hero_type', 'faction_id', 'quality_tier', 'power_rating', 'max_upgrade_level'])
            ->map(fn (Equipment $e) => [
                'id' => $e->id,
                'slug' => $e->slug,
                'name_pt' => $e->name_pt,
                'set_code' => $e->set_code,
                'slot' => $e->slot,
                'hero_type' => $e->hero_type,
                'faction_code' => $e->faction?->code,
                'faction_name_pt' => $e->faction?->name_pt,
                'quality_tier' => $e->quality_tier,
                'power_rating' => $e->power_rating,
                'max_upgrade_level' => $e->max_upgrade_level,
                'faction_matches' => $hero->faction_id === $e->faction_id,
            ]);

        return response()->json([
            'hero' => $heroPayload,
            'userHero' => $userHeroPayload,
            'synergies' => $synergies,
            'availableArtifacts' => $availableArtifacts,
            'availableCollections' => $availableCollections,
            'availableEquipments' => $availableEquipments,
            'ratings' => $hero->ratings,
            'engravings' => $hero->engravings,
            'features' => $hero->features,
            'signatureItem' => $signatureItemSummary,
            'isFavorited' => $isFavorited,
            // Página de Counters com este herói como Equipe inimiga de um só: quem o countera.
            // Herói pendente de revisão não entra numa Equipe inimiga, então fica sem link.
            'countersUrl' => $hero->pending_review ? null : route('counters.index', ['enemy' => [1 => $hero->id]], false),
        ]);
    }
}
