<?php

namespace App\Services\Counters;

use App\Models\Hero;
use App\Models\HeroCounter;
use App\Models\HeroRating;
use App\Services\Optimization\TierRatingScorer;
use Illuminate\Support\Collection;

/**
 * Counters de uma Equipe inimiga num Contexto, nas duas direções: o ranking
 * dos heróis que a counteram (soma das forças, desempate pelo tier rating do
 * próprio Contexto) e os heróis do Roster que ela countera. Informativo: não
 * entra na Pontuação (ADR 0001).
 */
class CounterRanking
{
    public function __construct(private readonly TierRatingScorer $scorer) {}

    /**
     * @param  int[]  $enemyHeroIds
     * @param  int[]|null  $candidateHeroIds  null = qualquer herói ativo
     * @return array<int, array{hero: array{id: int, name: string, portrait_url: ?string}, total_strength: int, tier: ?string, counters: array<int, array<string, mixed>>}>
     */
    public function rank(array $enemyHeroIds, ?array $candidateHeroIds, string $context, string $stage): array
    {
        $counters = HeroCounter::query()
            ->with(['hero:id,name,portrait_url,is_active', 'hero.ratings', 'target:id,name'])
            ->where('context', $context)
            ->whereIn('target_hero_id', $enemyHeroIds)
            ->when($candidateHeroIds !== null, fn ($query) => $query->whereIn('hero_id', $candidateHeroIds))
            ->get()
            // No Planejamento real o Roster decide; no alvo, só heróis ativos do jogo.
            ->filter(fn (HeroCounter $counter) => $counter->hero !== null && ($candidateHeroIds !== null || $counter->hero->is_active));

        return $counters
            ->groupBy('hero_id')
            ->map(function (Collection $heroCounters) use ($context, $stage) {
                /** @var Hero $hero */
                $hero = $heroCounters->first()->hero;

                return [
                    'hero' => $hero->only(['id', 'name', 'portrait_url']),
                    'total_strength' => (int) $heroCounters->sum('strength'),
                    'tier' => $this->scorer->contextRatingForHero($hero->id, [$hero->id => $hero->ratings], $context, $stage)?->tier,
                    'counters' => $heroCounters
                        ->sortByDesc('strength')
                        ->map(fn (HeroCounter $counter) => [
                            'target' => $counter->target->only(['id', 'name']),
                            'strength' => $counter->strength,
                            'reason' => $counter->reason,
                            'source_url' => $counter->source_url,
                        ])
                        ->values()
                        ->all(),
                ];
            })
            ->sort(fn (array $a, array $b) => [$b['total_strength'], self::tierRank($a['tier']), $a['hero']['name']]
                <=> [$a['total_strength'], self::tierRank($b['tier']), $b['hero']['name']])
            ->values()
            ->all();
    }

    /**
     * O inverso do ranking: os heróis do Roster que algum herói da Equipe
     * inimiga countera, os mais ameaçados primeiro (soma das forças).
     *
     * @param  int[]  $enemyHeroIds
     * @param  int[]  $rosterHeroIds
     * @return array<int, array{hero: array{id: int, name: string, portrait_url: ?string}, total_strength: int, counters: array<int, array<string, mixed>>}>
     */
    public function threatened(array $enemyHeroIds, array $rosterHeroIds, string $context): array
    {
        return HeroCounter::query()
            ->with(['hero:id,name', 'target:id,name,portrait_url'])
            ->where('context', $context)
            ->whereIn('hero_id', $enemyHeroIds)
            ->whereIn('target_hero_id', $rosterHeroIds)
            ->get()
            ->groupBy('target_hero_id')
            ->map(fn (Collection $targetCounters) => [
                'hero' => $targetCounters->first()->target->only(['id', 'name', 'portrait_url']),
                'total_strength' => (int) $targetCounters->sum('strength'),
                'counters' => $targetCounters
                    ->sortByDesc('strength')
                    ->map(fn (HeroCounter $counter) => [
                        'by' => $counter->hero->only(['id', 'name']),
                        'strength' => $counter->strength,
                        'reason' => $counter->reason,
                        'source_url' => $counter->source_url,
                    ])
                    ->values()
                    ->all(),
            ])
            ->sort(fn (array $a, array $b) => [$b['total_strength'], $a['hero']['name']] <=> [$a['total_strength'], $b['hero']['name']])
            ->values()
            ->all();
    }

    /** Posição do tier na escala (S+ = 0); sem tier vai para o fim. */
    private static function tierRank(?string $tier): int
    {
        $index = $tier === null ? false : array_search($tier, HeroRating::TIERS, true);

        return $index === false ? count(HeroRating::TIERS) : $index;
    }
}
