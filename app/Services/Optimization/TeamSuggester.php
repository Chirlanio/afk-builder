<?php

namespace App\Services\Optimization;

use App\Enums\Planning;
use App\Models\Beast;
use App\Models\ChallengeType;
use App\Models\GameAccount;
use App\Models\Hero;
use App\Models\HeroFeature;
use App\Models\HeroFurnitureAbility;
use App\Models\HeroRating;
use App\Models\HeroSynergy;
use App\Models\UserHero;

/**
 * Sugestão de Equipes a partir de Conta de jogo + Tipo de desafio +
 * Planejamento, sem depender de uma Formação salva. As telas de Formação e o
 * Export para IA usam o mesmo ponto de entrada.
 */
class TeamSuggester
{
    public function __construct(
        private readonly SingleTeamOptimizer $singleTeam,
        private readonly MultiTeamPartitioner $multiTeam,
    ) {}

    /**
     * @param  int[]  $lockedHeroIds
     * @param  int[]  $blockedHeroIds
     * @param  array<string, float>|null  $weightOverrides
     * @return array{hero_ids: int[], score: ScoreBreakdown}
     */
    public function suggestTeam(
        GameAccount $account,
        ChallengeType $challengeType,
        Planning $planning,
        array $lockedHeroIds = [],
        array $blockedHeroIds = [],
        ?Beast $beast = null,
        ?array $weightOverrides = null,
    ): array {
        $inputs = $this->inputs($account, $challengeType, $planning, $weightOverrides);

        return $this->singleTeam->optimize(
            $inputs['heroPoolById'],
            $inputs['userHeroesById'],
            $inputs['synergyWeights'],
            $inputs['profile'],
            array_values($lockedHeroIds),
            $blockedHeroIds,
            $beast,
            $inputs['context'],
        );
    }

    /**
     * Beasts ficam de fora (Fase 48): são escolhidas por Equipe em outro endpoint.
     *
     * @param  array<int, int[]>  $lockedByTeam  posição => hero_ids travados
     * @param  array<string, float>|null  $weightOverrides
     * @return array{teams: array<int, int[]>, total_score: float}
     */
    public function suggestAll(
        GameAccount $account,
        ChallengeType $challengeType,
        Planning $planning,
        int $teamsCount,
        array $lockedByTeam = [],
        ?array $weightOverrides = null,
    ): array {
        $inputs = $this->inputs($account, $challengeType, $planning, $weightOverrides);

        return $this->multiTeam->optimize(
            $inputs['heroPoolById'],
            $inputs['userHeroesById'],
            $inputs['synergyWeights'],
            $inputs['profile'],
            $lockedByTeam,
            $teamsCount,
            (bool) $challengeType->teams_must_be_unique,
            null,
            $inputs['context'],
        );
    }

    /**
     * Tudo o que o otimizador precisa para pontuar Equipes desta conta neste
     * Tipo de desafio e Planejamento.
     *
     * @param  array<string, float>|null  $weightOverrides
     * @return array{heroPoolById: array<int, Hero>, userHeroesById: array<int, UserHero>, synergyWeights: array<string, int>, profile: ScoringProfile, context: ScoringContext}
     */
    public function inputs(GameAccount $account, ChallengeType $challengeType, Planning $planning, ?array $weightOverrides = null): array
    {
        $heroPoolById = $this->loadHeroPool($account, $planning);
        $userHeroesById = $this->loadUserHeroes($account, $planning);

        $profile = ScoringProfile::fromArray($challengeType->scoring_profile);
        if (! empty($weightOverrides)) {
            $profile = $profile->withOverrides($weightOverrides);
        }

        return [
            'heroPoolById' => $heroPoolById,
            'userHeroesById' => $userHeroesById,
            'synergyWeights' => $this->loadSynergyWeights(),
            'profile' => $profile,
            'context' => $this->loadScoringContext($challengeType, $heroPoolById, $userHeroesById),
        ];
    }

    /**
     * @return array<int, Hero>
     */
    private function loadHeroPool(GameAccount $account, Planning $planning): array
    {
        $query = Hero::query()
            ->with(['faction:id,code', 'heroClass:id,code', 'tier:id,code', 'stats'])
            ->available();

        if ($planning === Planning::Real) {
            $rosterIds = UserHero::where('game_account_id', $account->id)->pluck('hero_id')->all();
            $query->whereIn('id', $rosterIds);
        }

        return $query->get()->keyBy('id')->all();
    }

    /**
     * O Planejamento alvo usa qualquer herói do jogo no Investimento máximo, então
     * o Roster fica fora da pontuação: senão os heróis da conta (com o poder real)
     * tirariam o lugar de heróis mais fortes que a conta ainda não tem.
     *
     * @return array<int, UserHero>
     */
    private function loadUserHeroes(GameAccount $account, Planning $planning): array
    {
        if ($planning === Planning::Target) {
            return [];
        }

        $userHeroes = UserHero::where('game_account_id', $account->id)
            ->with([
                'artifact:id,attributes',
                'collection:id,hp_bonus,attack_bonus,defense_bonus',
            ])
            ->get()
            ->keyBy('hero_id')
            ->all();
        (new PowerCalculator)->imputeMissingRealStats($userHeroes);

        return $userHeroes;
    }

    /**
     * @return array<string, int>
     */
    private function loadSynergyWeights(): array
    {
        $map = [];
        foreach (HeroSynergy::all(['hero_a_id', 'hero_b_id', 'weight']) as $s) {
            $map[TeamScorer::pairKey($s->hero_a_id, $s->hero_b_id)] = $s->weight;
        }

        return $map;
    }

    /**
     * Constrói o ScoringContext (P1+P2 da Fase 24): pré-carrega ratings e features
     * dos heróis do pool, infere stage do roster do user e mapeia challenge_type → mode.
     *
     * @param  array<int, Hero>  $heroPoolById
     * @param  array<int, UserHero>  $userHeroesById
     */
    private function loadScoringContext(ChallengeType $challengeType, array $heroPoolById, array $userHeroesById): ScoringContext
    {
        $heroIds = array_keys($heroPoolById);
        if (empty($heroIds)) {
            return new ScoringContext;
        }

        $ratingsByHeroId = HeroRating::whereIn('hero_id', $heroIds)
            ->get(['id', 'hero_id', 'mode', 'stage', 'tier', 'source'])
            ->groupBy('hero_id')
            ->all();

        $featuresByHeroId = HeroFeature::whereIn('hero_id', $heroIds)
            ->get(['id', 'hero_id', 'tag'])
            ->groupBy('hero_id')
            ->all();

        $furnitureAbilitiesByHeroId = HeroFurnitureAbility::whereIn('hero_id', $heroIds)
            ->whereNotNull('effects')
            ->get(['id', 'hero_id', 'effects'])
            ->keyBy('hero_id')
            ->all();

        return new ScoringContext(
            ratingsByHeroId: $ratingsByHeroId,
            featuresByHeroId: $featuresByHeroId,
            furnitureAbilitiesByHeroId: $furnitureAbilitiesByHeroId,
            mode: ChallengeTypeMode::forCode($challengeType->code),
            stage: (new RosterStageInferrer)->inferFromRoster($userHeroesById),
            challengeCode: $challengeType->code,
        );
    }
}
