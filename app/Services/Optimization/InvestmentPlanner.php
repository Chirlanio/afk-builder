<?php

namespace App\Services\Optimization;

use App\Models\Formation;
use App\Models\SignatureItemCost;
use App\Models\User;
use App\Models\UserHero;
use App\Models\UserResource;

/**
 * Gera sugestões priorizadas de "próximo investimento" no roster do user.
 * Para cada hero, simula upgrades candidatos (próximo milestone de SI/Furniture/
 * Engraving/Ascension), calcula o power gain via PowerCalculator com o estado
 * hipotético, divide pelo custo aproximado, aplica multiplicadores contextuais
 * (faction gap, tier do hero, escopo de formação) e retorna a lista ordenada
 * por score (ROI ajustado) decrescente.
 */
class InvestmentPlanner
{
    public const KIND_SIGNATURE_ITEM = 'signature_item';
    public const KIND_FURNITURE = 'furniture';
    public const KIND_ENGRAVING = 'engraving';
    public const KIND_ASCENSION = 'ascension';

    /**
     * Próximos milestones por tipo (limite superior excluído quando já passou).
     */
    private const ENGRAVING_MILESTONES = [30, 60, 80];

    /**
     * Ascension milestones que importam (asc_value que destrava unlock real
     * de tier ou stars). 4=Legendary, 10=Mythic+, 12=Ascended 1★, 16=Ascended 5★.
     */
    private const ASCENSION_MILESTONES = [4, 10, 12, 16];

    /**
     * Custo unitário (unidade abstrata "esforço") por tipo de upgrade.
     * Calibrado pra que SI/F/E/Asc fiquem comparáveis em ROI:
     *   SI 30→40 (~810 emblemas raros) ≈ Furniture F3→F9 (~1800 frags) ≈ Eng 60→80 (~3000 scrolls)
     * Valores absolutos não importam — só a razão entre eles.
     */
    private const FURNITURE_COST_PER_PIECE_BELOW_3 = 250;
    private const FURNITURE_COST_PER_PIECE_3_TO_9 = 300;
    private const ENGRAVING_COST_PER_LEVEL_BELOW_30 = 30;
    private const ENGRAVING_COST_PER_LEVEL_30_TO_60 = 70;
    private const ENGRAVING_COST_PER_LEVEL_60_TO_80 = 150;
    private const ASCENSION_COST_PER_STEP = 400;

    /** Multiplicador aplicado a heros em facções sub-investidas no roster. */
    private const FACTION_GAP_MULTIPLIER = 1.20;

    /** Multiplicador aplicado a heros tier ascended (catálogo). */
    private const ASCENDED_TIER_MULTIPLIER = 1.15;

    /** Multiplicador aplicado a heros "Awakened" (Despertos). */
    private const AWAKENED_MULTIPLIER = 1.25;

    /** Multiplicador aplicado quando hero está numa formação selecionada (scope). */
    private const IN_FORMATION_MULTIPLIER = 1.50;

    /** Multiplicador aplicado a heros do pentagrama (top 5 do roster por nível). */
    private const IN_PENTAGRAM_MULTIPLIER = 1.30;

    public function __construct(
        private readonly PowerCalculator $power = new PowerCalculator,
    ) {}

    /**
     * Gera plano de investimento. Retorna até $topN sugestões ordenadas por score.
     * Se $resources for fornecido, cada sugestão recebe `resource_cost` (map por tipo)
     * e `affordable` (bool) — útil pra filtrar/destacar o que cabe no orçamento atual.
     *
     * @return array<int, array<string, mixed>>
     */
    public function plan(User $user, ?Formation $formation = null, int $topN = 10, ?UserResource $resources = null): array
    {
        $userHeroes = $user->userHeroes()
            ->with([
                'hero:id,slug,name,portrait_url,faction_id,hero_class_id,tier_id',
                'hero.faction:id,code,name_pt',
                'hero.heroClass:id,code,name_pt',
                'hero.tier:id,code',
                'hero.stats',
                'artifact:id,attributes',
                'collection:id,hp_bonus,attack_bonus,defense_bonus',
            ])
            ->get()
            ->keyBy('hero_id');

        if ($userHeroes->isEmpty()) {
            return [];
        }

        // Mapas contextuais reusados por todas as sugestões
        $context = $this->buildContext($userHeroes, $formation);

        $suggestions = [];
        foreach ($userHeroes as $uh) {
            $currentPower = $this->power->forUserHero($uh, $uh->hero);
            foreach ($this->candidateUpgrades($uh) as $upgrade) {
                $entry = $this->scoreUpgrade($uh, $upgrade, $currentPower, $context);
                if ($entry !== null) {
                    if ($resources !== null) {
                        $entry['resource_cost'] = $this->resourceCostFor($uh, $upgrade);
                        $entry['affordable'] = $this->isAffordable($entry['resource_cost'], $resources);
                    } else {
                        $entry['resource_cost'] = null;
                        $entry['affordable'] = null;
                    }
                    $suggestions[] = $entry;
                }
            }
        }

        usort($suggestions, fn ($a, $b) => $b['score'] <=> $a['score']);

        return array_slice($suggestions, 0, $topN);
    }

    /**
     * Gera sugestões para um único UserHero usando o contexto completo do roster
     * do user (para que multipliers como faction_gap/in_pentagram funcionem certos).
     * Retorna lista ordenada por score, sem cap. Útil pra páginas de detalhe ou
     * comparação onde queremos ver todos os próximos passos do hero alvo.
     *
     * @return array<int, array<string, mixed>>
     */
    public function planForHero(User $user, UserHero $userHero, ?Formation $formation = null, ?UserResource $resources = null): array
    {
        $userHeroes = $user->userHeroes()
            ->with([
                'hero:id,slug,name,portrait_url,faction_id,hero_class_id,tier_id',
                'hero.faction:id,code,name_pt',
                'hero.heroClass:id,code,name_pt',
                'hero.tier:id,code',
                'hero.stats',
                'artifact:id,attributes',
                'collection:id,hp_bonus,attack_bonus,defense_bonus',
            ])
            ->get()
            ->keyBy('hero_id');

        if ($userHeroes->isEmpty()) {
            return [];
        }

        // Garante que o target tem as mesmas relations carregadas (vem do mapa)
        $target = $userHeroes->get($userHero->hero_id) ?? $userHero;

        $context = $this->buildContext($userHeroes, $formation);

        $suggestions = [];
        $currentPower = $this->power->forUserHero($target, $target->hero);
        foreach ($this->candidateUpgrades($target) as $upgrade) {
            $entry = $this->scoreUpgrade($target, $upgrade, $currentPower, $context);
            if ($entry === null) {
                continue;
            }
            if ($resources !== null) {
                $entry['resource_cost'] = $this->resourceCostFor($target, $upgrade);
                $entry['affordable'] = $this->isAffordable($entry['resource_cost'], $resources);
            } else {
                $entry['resource_cost'] = null;
                $entry['affordable'] = null;
            }
            $suggestions[] = $entry;
        }

        usort($suggestions, fn ($a, $b) => $b['score'] <=> $a['score']);

        return $suggestions;
    }

    /**
     * Computa o custo concreto do upgrade em recursos rastreáveis. SI usa o
     * cost real por nível (com bucket de emblem_type); Furniture/Engraving usam
     * 1 unidade por step; Ascension não tem pool global (retorna array vazio).
     *
     * @param  array{kind: string, from: int, to: int}  $upgrade
     * @return array<string, int>
     */
    public function resourceCostFor(UserHero $uh, array $upgrade): array
    {
        $cost = [];

        if ($upgrade['kind'] === self::KIND_SIGNATURE_ITEM) {
            $rows = SignatureItemCost::query()
                ->whereBetween('level', [$upgrade['from'] + 1, $upgrade['to']])
                ->get(['level', 'cost', 'emblem_type']);
            foreach ($rows as $row) {
                $type = (string) $row->emblem_type;
                $field = match ($type) {
                    SignatureItemCost::EMBLEM_PRIMORDIAL => 'primordial_emblems',
                    SignatureItemCost::EMBLEM_AMPLIFYING => 'amplifying_emblems',
                    SignatureItemCost::EMBLEM_FACTION => 'faction_emblems',
                    default => null,
                };
                if ($field === null) {
                    continue;
                }
                $cost[$field] = ($cost[$field] ?? 0) + (int) ($row->cost ?? 0);
            }

            return $cost;
        }

        if ($upgrade['kind'] === self::KIND_FURNITURE) {
            $cost['mythic_furniture'] = $upgrade['to'] - $upgrade['from'];

            return $cost;
        }

        if ($upgrade['kind'] === self::KIND_ENGRAVING) {
            $cost['engraving_scrolls'] = $upgrade['to'] - $upgrade['from'];

            return $cost;
        }

        // Ascension: sem pool global rastreável (fragmentos do hero específico)
        return [];
    }

    /**
     * @param  array<string, int>  $resourceCost
     */
    private function isAffordable(array $resourceCost, UserResource $resources): bool
    {
        if (empty($resourceCost)) {
            // Sem requisitos concretos (ascension) → não bloqueia affordable
            return true;
        }
        foreach ($resourceCost as $field => $required) {
            if (($resources->{$field} ?? 0) < $required) {
                return false;
            }
        }

        return true;
    }

    /**
     * Lista os próximos passos possíveis para um UserHero. Cada upgrade é
     * representado como ['kind' => ..., 'from' => int, 'to' => int].
     *
     * @return array<int, array{kind: string, from: int, to: int}>
     */
    private function candidateUpgrades(UserHero $uh): array
    {
        $upgrades = [];

        // SI: próximo milestone (30 ou 40 conforme cap da facção)
        $factionCode = $uh->hero?->faction?->code;
        $siCap = SignatureItemCost::capForFaction($factionCode);
        $si = (int) $uh->signature_item;
        if ($si < 30 && $si < $siCap) {
            $upgrades[] = ['kind' => self::KIND_SIGNATURE_ITEM, 'from' => $si, 'to' => min(30, $siCap)];
        } elseif ($si < $siCap) {
            $upgrades[] = ['kind' => self::KIND_SIGNATURE_ITEM, 'from' => $si, 'to' => $siCap];
        }

        // Furniture: próximo milestone (3 ou 9)
        $f = (int) $uh->furniture;
        if ($f < 3) {
            $upgrades[] = ['kind' => self::KIND_FURNITURE, 'from' => $f, 'to' => 3];
        } elseif ($f < 9) {
            $upgrades[] = ['kind' => self::KIND_FURNITURE, 'from' => $f, 'to' => 9];
        }

        // Engraving: próximo milestone (30/60/80)
        $e = (int) $uh->engraving;
        foreach (self::ENGRAVING_MILESTONES as $milestone) {
            if ($e < $milestone) {
                $upgrades[] = ['kind' => self::KIND_ENGRAVING, 'from' => $e, 'to' => $milestone];
                break;
            }
        }

        // Ascension: próximo milestone real (não cada step individual)
        $asc = (int) $uh->ascension_value;
        foreach (self::ASCENSION_MILESTONES as $milestone) {
            if ($asc < $milestone) {
                $upgrades[] = ['kind' => self::KIND_ASCENSION, 'from' => $asc, 'to' => $milestone];
                break;
            }
        }

        return $upgrades;
    }

    /**
     * Calcula o score (ROI ponderado) de um upgrade específico.
     * Retorna null se o ganho de power for ≤ 0 (não vale sugerir).
     *
     * @param  array{kind: string, from: int, to: int}  $upgrade
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>|null
     */
    private function scoreUpgrade(UserHero $uh, array $upgrade, float $currentPower, array $context): ?array
    {
        // Simula UserHero com o upgrade aplicado, calcula power "hipotético"
        $simulated = $this->simulate($uh, $upgrade);
        $newPower = $this->power->forUserHero($simulated, $uh->hero);
        $powerGain = $newPower - $currentPower;

        if ($powerGain <= 0.001) {
            return null;
        }

        $cost = $this->costFor($upgrade);
        $baseScore = $powerGain / max(1.0, $cost);

        // Multiplicadores contextuais
        $multiplier = 1.0;
        $reasons = [];

        $factionCode = $uh->hero?->faction?->code;
        if ($factionCode !== null && in_array($factionCode, $context['gap_factions'], true)) {
            $multiplier *= self::FACTION_GAP_MULTIPLIER;
            $reasons[] = 'faction_gap';
        }

        $tierCode = $uh->hero?->tier?->code;
        if ($tierCode === 'ascended') {
            $multiplier *= self::ASCENDED_TIER_MULTIPLIER;
            $reasons[] = 'tier_ascended';
        }

        // Awakened detection via slug != slug(name) — alinhado com is_awakened do HeroController
        if ($this->isAwakened($uh->hero?->name, $uh->hero?->slug)) {
            $multiplier *= self::AWAKENED_MULTIPLIER;
            $reasons[] = 'awakened';
        }

        if (! empty($context['formation_hero_ids']) && in_array($uh->hero_id, $context['formation_hero_ids'], true)) {
            $multiplier *= self::IN_FORMATION_MULTIPLIER;
            $reasons[] = 'in_formation';
        }

        if (! empty($context['pentagram_hero_ids']) && in_array($uh->hero_id, $context['pentagram_hero_ids'], true)) {
            $multiplier *= self::IN_PENTAGRAM_MULTIPLIER;
            $reasons[] = 'in_pentagram';
        }

        return [
            'hero_id' => $uh->hero_id,
            'slug' => $uh->hero?->slug,
            'name' => $uh->hero?->name,
            'portrait_url' => $uh->hero?->portrait_url,
            'faction_code' => $factionCode,
            'class_code' => $uh->hero?->heroClass?->code,
            'tier_code' => $tierCode,
            'kind' => $upgrade['kind'],
            'from' => $upgrade['from'],
            'to' => $upgrade['to'],
            'power_gain' => (int) round($powerGain),
            'cost' => (int) round($cost),
            'score' => round($baseScore * $multiplier, 4),
            'reasons' => $reasons,
            'current' => [
                'level' => (int) $uh->level,
                'ascension_value' => (int) $uh->ascension_value,
                'signature_item' => (int) $uh->signature_item,
                'furniture' => (int) $uh->furniture,
                'engraving' => (int) $uh->engraving,
            ],
        ];
    }

    /**
     * Retorna um UserHero "in-memory" com o upgrade aplicado. NÃO persiste.
     *
     * @param  array{kind: string, from: int, to: int}  $upgrade
     */
    private function simulate(UserHero $uh, array $upgrade): UserHero
    {
        $clone = $uh->replicate();
        $clone->setRelation('hero', $uh->hero);
        if ($uh->relationLoaded('artifact')) {
            $clone->setRelation('artifact', $uh->artifact);
        }
        if ($uh->relationLoaded('collection')) {
            $clone->setRelation('collection', $uh->collection);
        }

        match ($upgrade['kind']) {
            self::KIND_SIGNATURE_ITEM => $clone->signature_item = $upgrade['to'],
            self::KIND_FURNITURE => $clone->furniture = $upgrade['to'],
            self::KIND_ENGRAVING => $clone->engraving = $upgrade['to'],
            self::KIND_ASCENSION => $clone->ascension_value = $upgrade['to'],
            default => null,
        };

        return $clone;
    }

    /**
     * Estima o custo (em unidade abstrata "esforço") de um upgrade.
     *
     * @param  array{kind: string, from: int, to: int}  $upgrade
     */
    private function costFor(array $upgrade): float
    {
        return match ($upgrade['kind']) {
            self::KIND_SIGNATURE_ITEM => (float) (
                SignatureItemCost::cumulativeCostUpToLevel($upgrade['to'])
                - SignatureItemCost::cumulativeCostUpToLevel($upgrade['from'])
            ),
            self::KIND_FURNITURE => $this->furnitureCost($upgrade['from'], $upgrade['to']),
            self::KIND_ENGRAVING => $this->engravingCost($upgrade['from'], $upgrade['to']),
            self::KIND_ASCENSION => ($upgrade['to'] - $upgrade['from']) * self::ASCENSION_COST_PER_STEP,
            default => 1.0,
        };
    }

    private function furnitureCost(int $from, int $to): float
    {
        $cost = 0.0;
        for ($f = $from; $f < $to; $f++) {
            $cost += $f < 3 ? self::FURNITURE_COST_PER_PIECE_BELOW_3 : self::FURNITURE_COST_PER_PIECE_3_TO_9;
        }

        return $cost;
    }

    private function engravingCost(int $from, int $to): float
    {
        $cost = 0.0;
        for ($e = $from; $e < $to; $e++) {
            if ($e < 30) {
                $cost += self::ENGRAVING_COST_PER_LEVEL_BELOW_30;
            } elseif ($e < 60) {
                $cost += self::ENGRAVING_COST_PER_LEVEL_30_TO_60;
            } else {
                $cost += self::ENGRAVING_COST_PER_LEVEL_60_TO_80;
            }
        }

        return $cost;
    }

    /**
     * Pré-calcula contexto reusado por todas as sugestões:
     * - Facções com poucos heros investidos no roster (FACTION_GAP_MULTIPLIER alvo)
     * - IDs dos heros pertencentes à formation selecionada (IN_FORMATION_MULTIPLIER alvo)
     * - IDs do top 5 do roster por nível (IN_PENTAGRAM_MULTIPLIER alvo)
     *
     * @param  \Illuminate\Support\Collection<int, UserHero>  $userHeroes
     * @return array{gap_factions: array<int, string>, formation_hero_ids: array<int, int>, pentagram_hero_ids: array<int, int>}
     */
    private function buildContext(\Illuminate\Support\Collection $userHeroes, ?Formation $formation): array
    {
        $investedByFaction = [];
        foreach ($userHeroes as $uh) {
            $code = $uh->hero?->faction?->code;
            if ($code === null) {
                continue;
            }
            $investedByFaction[$code] ??= 0;
            if ($this->isInvested($uh)) {
                $investedByFaction[$code]++;
            }
        }

        $gapFactions = [];
        foreach ($investedByFaction as $code => $count) {
            if ($count < 3) {
                $gapFactions[] = $code;
            }
        }

        $formationHeroIds = [];
        if ($formation !== null) {
            $formation->loadMissing('teams.teamHeroes:id,formation_team_id,hero_id');
            foreach ($formation->teams as $team) {
                foreach ($team->teamHeroes as $th) {
                    $formationHeroIds[$th->hero_id] = true;
                }
            }
            $formationHeroIds = array_keys($formationHeroIds);
        }

        // Top 5 do roster por nível — heros do pentagrama do Cristal Ressonante.
        // Investir num desses puxa o "5º maior" pra cima e eleva o cristal inteiro.
        $pentagramHeroIds = $userHeroes
            ->sortByDesc('level')
            ->take(5)
            ->pluck('hero_id')
            ->all();

        return [
            'gap_factions' => $gapFactions,
            'formation_hero_ids' => $formationHeroIds,
            'pentagram_hero_ids' => $pentagramHeroIds,
        ];
    }

    private function isInvested(UserHero $uh): bool
    {
        return (int) $uh->ascension_value >= 10
            || (int) $uh->signature_item >= 30
            || (int) $uh->furniture >= 9;
    }

    private function isAwakened(?string $name, ?string $slug): bool
    {
        if ($name === null || $slug === null) {
            return false;
        }
        // Mesma regra de HeroController: slug != slug(name) → desperto
        return \Illuminate\Support\Str::slug($name) !== $slug;
    }
}
