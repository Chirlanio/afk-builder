<?php

namespace App\Http\Controllers;

use App\Models\Faction;
use App\Models\GameAccount;
use App\Models\HeroClass;
use App\Models\Tier;
use App\Models\UserHero;
use App\Models\UserResonatingCrystal;
use App\Services\ActiveGameAccount;
use App\Services\Optimization\PowerCalculator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RosterAnalyticsController extends Controller
{
    public function __construct(private readonly ActiveGameAccount $active) {}

    /**
     * Quantos heroes "fortemente investidos" (Ascended ou superior) são esperados
     * por facção pra considerar uma facção "saudável". Abaixo disso → gap.
     */
    private const MIN_INVESTED_PER_FACTION = 3;

    /** Considera um hero "investido" se atinge esses thresholds (qualquer um). */
    private const INVESTED_ASCENSION_VALUE = 10;   // Mythic+ (Ascended)

    private const INVESTED_SI = 30;

    private const INVESTED_FURNITURE = 9;

    public function index(Request $request, PowerCalculator $power): Response
    {
        $account = $this->active->get();

        $userHeroes = $account->userHeroes()
            ->with([
                'hero:id,slug,name,portrait_url,faction_id,hero_class_id,tier_id',
                'hero.faction:id,code,name_pt,display_order',
                'hero.heroClass:id,code,name_pt,display_order',
                'hero.tier:id,code,name_pt,display_order',
                'hero.stats',
                'artifact:id,attributes',
                'collection:id,hp_bonus,attack_bonus,defense_bonus',
            ])
            ->get();

        // Map hero_id → power calculado (usado em várias agregações)
        $powerByHeroId = [];
        foreach ($userHeroes as $uh) {
            $powerByHeroId[$uh->hero_id] = $power->forUserHero($uh, $uh->hero);
        }

        $rosterSize = $userHeroes->count();

        $summary = $this->computeSummary($userHeroes, $powerByHeroId);
        $byFaction = $this->groupByFaction($userHeroes, $powerByHeroId);
        $byClass = $this->groupByClass($userHeroes, $powerByHeroId);
        $byTier = $this->groupByTier($userHeroes, $powerByHeroId);
        $top10 = $this->top10Heroes($userHeroes, $powerByHeroId);
        $gaps = $this->detectGaps($userHeroes, $byFaction, $byClass);
        $pentagram = $this->pentagramSummary($userHeroes, $account);

        return Inertia::render('roster/analytics', [
            'rosterSize' => $rosterSize,
            'summary' => $summary,
            'byFaction' => $byFaction,
            'byClass' => $byClass,
            'byTier' => $byTier,
            'top10' => $top10,
            'gaps' => $gaps,
            'pentagram' => $pentagram,
        ]);
    }

    /**
     * Sumariza o pentagrama do Cristal Ressonante: top 5 do roster + nível
     * compartilhado (5º maior) + teto destravado.
     *
     * @param  iterable<UserHero>  $userHeroes
     * @return array<string, mixed>
     */
    private function pentagramSummary(iterable $userHeroes, GameAccount $account): array
    {
        $sorted = collect($userHeroes)
            ->sortByDesc('level')
            ->take(5)
            ->values();

        $heroes = $sorted->map(fn (UserHero $uh) => [
            'hero_id' => $uh->hero_id,
            'slug' => $uh->hero?->slug,
            'name' => $uh->hero?->name,
            'portrait_url' => $uh->hero?->portrait_url,
            'faction_code' => $uh->hero?->faction?->code,
            'level' => (int) $uh->level,
            'tier_code' => $uh->hero?->tier?->code,
        ])->all();

        $fifthLevel = $sorted->count() >= 5 ? (int) $sorted->last()->level : null;

        return [
            'heroes' => $heroes,
            'shared_level' => $fifthLevel,
            'is_full' => $sorted->count() >= 5,
            'is_at_240' => $fifthLevel !== null && $fifthLevel >= UserResonatingCrystal::MIN_LEVEL,
            'level_cap' => UserResonatingCrystal::levelCapForAccount($account),
        ];
    }

    /**
     * @param  iterable<UserHero>  $userHeroes
     * @param  array<int, float>  $powerByHeroId
     * @return array<string, int|float>
     */
    private function computeSummary(iterable $userHeroes, array $powerByHeroId): array
    {
        $count = 0;
        $sumPower = 0.0;
        $sumLevel = 0;
        $sumSi = 0;
        $sumFurniture = 0;
        $sumEngraving = 0;
        $sumAscension = 0;
        $maxPower = 0.0;
        $invested = 0;

        foreach ($userHeroes as $uh) {
            $count++;
            $p = $powerByHeroId[$uh->hero_id] ?? 0.0;
            $sumPower += $p;
            $maxPower = max($maxPower, $p);
            $sumLevel += (int) $uh->level;
            $sumSi += (int) $uh->signature_item;
            $sumFurniture += (int) $uh->furniture;
            $sumEngraving += (int) $uh->engraving;
            $sumAscension += (int) $uh->ascension_value;
            if ($this->isInvested($uh)) {
                $invested++;
            }
        }

        $avg = fn (int|float $sum) => $count === 0 ? 0.0 : $sum / $count;

        return [
            'total_power' => (int) round($sumPower),
            'avg_power' => (int) round($avg($sumPower)),
            'max_power' => (int) round($maxPower),
            'avg_level' => round($avg($sumLevel), 1),
            'avg_signature_item' => round($avg($sumSi), 1),
            'avg_furniture' => round($avg($sumFurniture), 1),
            'avg_engraving' => round($avg($sumEngraving), 1),
            'avg_ascension' => round($avg($sumAscension), 1),
            'invested_count' => $invested,
        ];
    }

    /**
     * @param  iterable<UserHero>  $userHeroes
     * @param  array<int, float>  $powerByHeroId
     * @return array<int, array<string, mixed>>
     */
    private function groupByFaction(iterable $userHeroes, array $powerByHeroId): array
    {
        $buckets = [];
        $factions = Faction::orderBy('display_order')->get(['id', 'code', 'name_pt']);
        foreach ($factions as $f) {
            $buckets[$f->code] = [
                'code' => $f->code,
                'name_pt' => $f->name_pt,
                'count' => 0,
                'total_power' => 0.0,
                'invested_count' => 0,
                'avg_power' => 0,
            ];
        }

        foreach ($userHeroes as $uh) {
            $code = $uh->hero?->faction?->code;
            if ($code === null || ! isset($buckets[$code])) {
                continue;
            }
            $buckets[$code]['count']++;
            $buckets[$code]['total_power'] += $powerByHeroId[$uh->hero_id] ?? 0.0;
            if ($this->isInvested($uh)) {
                $buckets[$code]['invested_count']++;
            }
        }

        foreach ($buckets as $code => &$row) {
            $row['total_power'] = (int) round($row['total_power']);
            $row['avg_power'] = $row['count'] > 0 ? (int) round($row['total_power'] / $row['count']) : 0;
        }
        unset($row);

        return array_values($buckets);
    }

    /**
     * @param  iterable<UserHero>  $userHeroes
     * @param  array<int, float>  $powerByHeroId
     * @return array<int, array<string, mixed>>
     */
    private function groupByClass(iterable $userHeroes, array $powerByHeroId): array
    {
        $buckets = [];
        $classes = HeroClass::orderBy('display_order')->get(['id', 'code', 'name_pt']);
        foreach ($classes as $c) {
            $buckets[$c->code] = [
                'code' => $c->code,
                'name_pt' => $c->name_pt,
                'count' => 0,
                'total_power' => 0.0,
                'invested_count' => 0,
                'avg_power' => 0,
            ];
        }

        foreach ($userHeroes as $uh) {
            $code = $uh->hero?->heroClass?->code;
            if ($code === null || ! isset($buckets[$code])) {
                continue;
            }
            $buckets[$code]['count']++;
            $buckets[$code]['total_power'] += $powerByHeroId[$uh->hero_id] ?? 0.0;
            if ($this->isInvested($uh)) {
                $buckets[$code]['invested_count']++;
            }
        }

        foreach ($buckets as &$row) {
            $row['total_power'] = (int) round($row['total_power']);
            $row['avg_power'] = $row['count'] > 0 ? (int) round($row['total_power'] / $row['count']) : 0;
        }
        unset($row);

        return array_values($buckets);
    }

    /**
     * @param  iterable<UserHero>  $userHeroes
     * @param  array<int, float>  $powerByHeroId
     * @return array<int, array<string, mixed>>
     */
    private function groupByTier(iterable $userHeroes, array $powerByHeroId): array
    {
        $buckets = [];
        $tiers = Tier::orderBy('display_order')->get(['id', 'code', 'name_pt']);
        foreach ($tiers as $t) {
            $buckets[$t->code] = [
                'code' => $t->code,
                'name_pt' => $t->name_pt,
                'count' => 0,
                'total_power' => 0.0,
                'avg_power' => 0,
            ];
        }

        foreach ($userHeroes as $uh) {
            $code = $uh->hero?->tier?->code;
            if ($code === null || ! isset($buckets[$code])) {
                continue;
            }
            $buckets[$code]['count']++;
            $buckets[$code]['total_power'] += $powerByHeroId[$uh->hero_id] ?? 0.0;
        }

        foreach ($buckets as &$row) {
            $row['total_power'] = (int) round($row['total_power']);
            $row['avg_power'] = $row['count'] > 0 ? (int) round($row['total_power'] / $row['count']) : 0;
        }
        unset($row);

        return array_values($buckets);
    }

    /**
     * @param  iterable<UserHero>  $userHeroes
     * @param  array<int, float>  $powerByHeroId
     * @return array<int, array<string, mixed>>
     */
    private function top10Heroes(iterable $userHeroes, array $powerByHeroId): array
    {
        $rows = [];
        foreach ($userHeroes as $uh) {
            $rows[] = [
                'hero_id' => $uh->hero_id,
                'slug' => $uh->hero?->slug,
                'name' => $uh->hero?->name,
                'portrait_url' => $uh->hero?->portrait_url,
                'faction_code' => $uh->hero?->faction?->code,
                'class_code' => $uh->hero?->heroClass?->code,
                'tier_code' => $uh->hero?->tier?->code,
                'level' => (int) $uh->level,
                'signature_item' => (int) $uh->signature_item,
                'furniture' => (int) $uh->furniture,
                'engraving' => (int) $uh->engraving,
                'power' => (int) round($powerByHeroId[$uh->hero_id] ?? 0.0),
            ];
        }

        usort($rows, fn ($a, $b) => $b['power'] <=> $a['power']);

        return array_slice($rows, 0, 10);
    }

    /**
     * Detecta lacunas no roster (facções sub-investidas, classes faltando, etc).
     *
     * @param  iterable<UserHero>  $userHeroes
     * @param  array<int, array<string, mixed>>  $byFaction
     * @param  array<int, array<string, mixed>>  $byClass
     * @return array<int, array<string, string>>
     */
    private function detectGaps(iterable $userHeroes, array $byFaction, array $byClass): array
    {
        $gaps = [];

        // Facções com < MIN heroes investidos
        foreach ($byFaction as $row) {
            if ($row['invested_count'] < self::MIN_INVESTED_PER_FACTION) {
                $deficit = self::MIN_INVESTED_PER_FACTION - $row['invested_count'];
                $gaps[] = [
                    'type' => 'faction_underinvested',
                    'severity' => $row['invested_count'] === 0 ? 'high' : 'medium',
                    'label' => "Faltam {$deficit} hero(s) investido(s) em {$row['name_pt']}",
                    'hint' => $row['count'] === 0
                        ? 'Sem heróis dessa facção no roster.'
                        : "Você tem {$row['count']} hero(s) dessa facção; só {$row['invested_count']} investido(s).",
                ];
            }
        }

        // Classes com count = 0 (sem nenhum hero)
        foreach ($byClass as $row) {
            if ($row['count'] === 0) {
                $gaps[] = [
                    'type' => 'class_missing',
                    'severity' => 'high',
                    'label' => "Sem heróis da classe {$row['name_pt']}",
                    'hint' => 'Class balance no scoring penaliza ausência de Tanque/Suporte especialmente.',
                ];
            } elseif ($row['invested_count'] === 0 && $row['count'] > 0) {
                $gaps[] = [
                    'type' => 'class_underinvested',
                    'severity' => 'low',
                    'label' => "Nenhum hero investido na classe {$row['name_pt']}",
                    'hint' => "Você tem {$row['count']} hero(s) dessa classe, mas nenhum atingiu Ascended/SI 30/F9.",
                ];
            }
        }

        return $gaps;
    }

    private function isInvested(UserHero $uh): bool
    {
        return (int) $uh->ascension_value >= self::INVESTED_ASCENSION_VALUE
            || (int) $uh->signature_item >= self::INVESTED_SI
            || (int) $uh->furniture >= self::INVESTED_FURNITURE;
    }
}
