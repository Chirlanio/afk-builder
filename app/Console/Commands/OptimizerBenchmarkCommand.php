<?php

namespace App\Console\Commands;

use App\Models\ChallengeType;
use App\Models\Hero;
use App\Models\HeroFeature;
use App\Models\HeroRating;
use App\Models\HeroSynergy;
use App\Models\RecommendedFormation;
use App\Services\Optimization\ChallengeTypeMode;
use App\Services\Optimization\PowerCalculator;
use App\Services\Optimization\ScoreBreakdown;
use App\Services\Optimization\ScoringContext;
use App\Services\Optimization\ScoringProfile;
use App\Services\Optimization\TeamScorer;
use Illuminate\Console\Command;

/**
 * Checks the score against the community: for each complete recommended comp
 * (PvE/PvP, 5 heroes), computes its percentile against random teams from the
 * catalog — per component and in total. Chance = 50.
 *
 * Run it before changing TeamScorer weights or components. Reference
 * (production catalog): total 88.0 on 2026-10-18; 88.2 after saturating
 * synergy and halving the faction bonus (worst comp 43 → 54). Weight tweaks
 * moved it by ±2 — noise for 9 comps.
 *
 * Target mode (no roster): power is constant among heroes of the same tier.
 * Dragonforge Trials are left out — that mode's ratings are derived from the
 * comps themselves, so the test would be circular.
 */
class OptimizerBenchmarkCommand extends Command
{
    protected $signature = 'optimizer:benchmark {--samples=3000 : Times aleatórios por modo} {--seed=13 : Semente do sorteio}';

    protected $description = 'Percentil dos comps recomendados contra times aleatórios, por componente do score';

    private const MODES = [
        RecommendedFormation::MODE_PVE => 'campaign',
        RecommendedFormation::MODE_PVP => 'arena_of_heroes',
    ];

    private const COMPONENTS = [
        'factionBonus' => 'Bônus de facção',
        'classBalance' => 'Equilíbrio de classes',
        'synergy' => 'Sinergias',
        'power' => 'Poder',
        'tierRating' => 'Tier rating',
        'featureBonus' => 'Destaques',
        'total' => 'TOTAL',
    ];

    public function handle(): int
    {
        $heroes = Hero::with(['faction', 'heroClass', 'tier', 'stats'])
            ->where('is_active', true)->where('pending_review', false)
            ->get()->keyBy('slug');
        if ($heroes->count() < 10) {
            $this->error('Catálogo vazio — rode o sync de heróis antes.');

            return self::FAILURE;
        }

        $ratings = HeroRating::all()->groupBy('hero_id')->all();
        $features = HeroFeature::all()->groupBy('hero_id')->all();
        $synergies = [];
        foreach (HeroSynergy::all() as $s) {
            $synergies[min($s->hero_a_id, $s->hero_b_id).'-'.max($s->hero_a_id, $s->hero_b_id)] = $s->weight;
        }

        $scorer = new TeamScorer(new PowerCalculator);
        mt_srand((int) $this->option('seed'));
        $slugs = $heroes->keys()->all();
        $percentiles = [];

        foreach (self::MODES as $rfMode => $code) {
            $type = ChallengeType::where('code', $code)->first();
            $profile = ScoringProfile::fromArray($type?->scoring_profile);
            $ctx = new ScoringContext($ratings, $features, [], ChallengeTypeMode::forCode($code), HeroRating::STAGE_END, $code);
            $score = fn (array $team): ScoreBreakdown => $scorer->score(array_map(fn ($slug) => $heroes[$slug], $team), [], $synergies, $profile, null, $ctx);

            $random = [];
            for ($i = 0; $i < (int) $this->option('samples'); $i++) {
                shuffle($slugs);
                $breakdown = $score(array_slice($slugs, 0, 5));
                foreach (array_keys(self::COMPONENTS) as $key) {
                    $random[$key][] = $breakdown->$key;
                }
            }

            foreach (RecommendedFormation::where('mode', $rfMode)->where('is_active', true)->get() as $rf) {
                $team = array_values(array_filter(array_slice($rf->hero_slugs ?? [], 0, 5), fn ($slug) => isset($heroes[$slug])));
                if (count($team) < 5) {
                    continue;
                }
                $breakdown = $score($team);
                foreach (array_keys(self::COMPONENTS) as $key) {
                    $percentiles[$key][$rf->slug] = $this->percentile($random[$key], $breakdown->$key);
                }
            }
        }

        if (empty($percentiles)) {
            $this->warn('Nenhum comp recomendado completo (5 heróis do catálogo) para comparar.');

            return self::SUCCESS;
        }

        $comps = array_keys($percentiles['total']);
        $this->info(count($comps).' comps × '.$this->option('samples').' times aleatórios por modo. Acaso = 50.');
        $this->table(
            ['Componente', 'Média', ...array_map(fn ($slug) => mb_substr($slug, 0, 12), $comps)],
            array_map(fn ($key, $label) => [
                $label,
                number_format(array_sum($percentiles[$key]) / count($percentiles[$key]), 1, ',', ''),
                ...array_map(fn ($slug) => (int) round($percentiles[$key][$slug]), $comps),
            ], array_keys(self::COMPONENTS), self::COMPONENTS),
        );

        return self::SUCCESS;
    }

    /** @param array<int, float> $sample */
    private function percentile(array $sample, float $value): float
    {
        $below = count(array_filter($sample, fn ($x) => $x < $value));
        $equal = count(array_filter($sample, fn ($x) => $x == $value));

        return 100 * ($below + $equal / 2) / count($sample);
    }
}
