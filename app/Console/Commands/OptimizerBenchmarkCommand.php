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
 * Valida o score contra a comunidade: para cada comp recomendado completo
 * (PvE/PvP, 5 heróis), calcula em que percentil ele fica frente a times
 * aleatórios do catálogo — por componente e no total. Acaso = 50.
 *
 * Usar antes de mudar pesos ou componentes do TeamScorer. Referência
 * (2026-10-18, catálogo de produção): total 88,0; facção 78; tier rating 78,5;
 * sinergias 70; classes 59; destaques 59. Variações de peso ficaram em ±2 —
 * ruído para 9 comps —, então os pesos foram mantidos.
 *
 * Modo alvo (sem roster): o poder fica constante entre heróis do mesmo tier.
 * As Provas da Forja ficam de fora — os ratings desse modo são derivados dos
 * próprios comps, então o teste seria circular.
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
