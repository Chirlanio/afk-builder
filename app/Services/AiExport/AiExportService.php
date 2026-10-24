<?php

namespace App\Services\AiExport;

use App\Enums\Planning;
use App\Models\ChallengeType;
use App\Models\GameAccount;
use App\Models\Hero;
use App\Models\HeroRating;
use App\Models\UserHero;
use App\Services\Optimization\RosterStageInferrer;
use App\Services\Optimization\TeamSuggester;
use App\Services\Optimization\TierRatingScorer;
use Illuminate\Support\Str;

/**
 * Monta o texto Markdown do Export para IA: prompt do Objetivo + dados da
 * Conta de jogo. Nunca inclui Pontuação nem Poder estimado (números internos
 * do app que o modelo interpretaria errado).
 */
class AiExportService
{
    private const STAGE_LABELS = [
        HeroRating::STAGE_EARLY => 'início de jogo',
        HeroRating::STAGE_MID => 'meio de jogo',
        HeroRating::STAGE_END => 'fim de jogo',
    ];

    private const TIER_AND_CAPABILITIES = 'O tier de cada herói é a avaliação da comunidade (afk.guide) para este conteúdo; "sem tier" quer dizer que ela não avaliou o herói aqui. As capacidades (força 1 ou 2) resumem o que as habilidades fazem.';

    /** Corte do Planejamento alvo: os 4 primeiros de HeroRating::TIERS (S+, S, A+, A). */
    private const TARGET_TIER_COUNT = 4;

    private const MAX_INVESTMENT = 'Ascended 5★, nível 240, F9, item de assinatura e gravação no limite da facção (+40/E80 para Celestials e Hypogeans, +30/E60 para os demais)';

    public function __construct(
        private readonly TierRatingScorer $scorer,
        private readonly RosterStageInferrer $stageInferrer,
        private readonly TeamSuggester $suggester,
    ) {}

    /**
     * @return array{markdown: string, warning: ?string}
     */
    public function build(GameAccount $account, AiExportObjective $objective, Planning $planning, array $params = [], bool $includeSuggestion = false): array
    {
        $context = $objective->context();
        $teams = $objective->teams($params);

        if ($planning === Planning::Target) {
            $stage = HeroRating::STAGE_END;
            $entries = $this->targetEntries($context);
        } else {
            $userHeroes = $account->userHeroes()
                ->with([
                    'hero.faction', 'hero.heroClass', 'hero.ratings', 'artifact',
                    'weaponEquipment', 'helmetEquipment', 'chestEquipment', 'bootsEquipment',
                ])
                ->get()
                ->sortBy(fn (UserHero $uh) => $uh->hero->name)
                ->values();
            if ($userHeroes->isEmpty()) {
                return [
                    'markdown' => '',
                    'warning' => 'Seu Roster está vazio: o Planejamento real não tem heróis para enviar. Cadastre seus heróis ou use o Planejamento alvo.',
                ];
            }

            $stage = $this->stageInferrer->inferFromRoster($userHeroes);
            $entries = $userHeroes->map(fn (UserHero $uh) => [
                'hero' => $uh->hero,
                'userHero' => $uh,
                'tier' => $this->contextTier($uh->hero, $context, $stage),
            ])->all();
        }

        $lines = [
            "# AFK Arena — {$objective->label}",
            '',
            'Gerado pelo AFK Builder em '.now()->format('d/m/Y').' · Estágio da conta: '.self::STAGE_LABELS[$stage],
            '',
            '## Pedido',
            '',
            'Você é um especialista em AFK Arena. Quero '.$objective->goal($params).'.',
            ...$objective->formation
                ? $this->formationRequest($objective, $planning, $teams, $params)
                : $this->investmentRequest($planning),
            'Responda em português do Brasil, mas escreva os nomes dos heróis em inglês, exatamente como na lista.',
            '',
            '## Heróis',
            '',
        ];
        foreach ($entries as $entry) {
            $lines[] = $this->heroLine($entry['hero'], $entry['userHero'], $entry['tier']);
        }

        $suggestion = $includeSuggestion && $objective->formation && $objective->challengeType !== null
            ? $this->suggestionSection($account, $objective->challengeType, $planning, $teams)
            : [];
        if ($suggestion !== []) {
            array_push($lines, '', ...$suggestion);
        }

        array_push(
            $lines,
            '',
            '## Formato da resposta',
            '',
            $objective->formation ? $this->answerFormat($teams) : $this->investmentAnswerFormat(),
        );

        return ['markdown' => implode("\n", $lines)."\n", 'warning' => null];
    }

    /**
     * A sugestão do otimizador para o mesmo Objetivo e Planejamento, só com os
     * nomes dos heróis: o modelo deve criticá-la, não copiá-la. Sem Tipo de
     * desafio no banco ou sem heróis para sugerir, a seção fica de fora.
     *
     * @return string[]
     */
    private function suggestionSection(GameAccount $account, string $challengeCode, Planning $planning, int $teamsCount): array
    {
        $challengeType = ChallengeType::where('code', $challengeCode)->first();
        if ($challengeType === null) {
            return [];
        }

        $teams = array_filter($this->suggester->suggestAll($account, $challengeType, $planning, $teamsCount)['teams']);
        if ($teams === []) {
            return [];
        }
        $names = Hero::whereIn('id', array_merge(...array_values($teams)))->pluck('name', 'id');

        $lines = [
            '## Sugestão do AFK Builder (a revisar)',
            '',
            'O otimizador do AFK Builder sugeriu as Equipes abaixo. Não copie: avalie se fazem sentido, aponte os pontos fracos e diga se a sua resposta é melhor.',
            '',
        ];
        foreach ($teams as $position => $heroIds) {
            $lines[] = "Equipe {$position}: ".implode(', ', array_map(fn (int $id) => $names[$id], $heroIds));
        }

        return $lines;
    }

    /**
     * Pedido dos Objetivos que geram Formação: quantas Equipes, de onde vêm os
     * heróis e as regras do Tipo de desafio.
     *
     * @return string[]
     */
    private function formationRequest(AiExportObjective $objective, Planning $planning, int $teams, array $params): array
    {
        $heroesInstruction = $planning === Planning::Target
            ? 'com qualquer herói do jogo, todos no Investimento máximo ('.self::MAX_INVESTMENT.'). A lista abaixo é só um recorte com os heróis avaliados A ou melhor para este conteúdo: prefira-os, mas pode sugerir outro herói se ele for claramente melhor.'
            : 'usando **apenas** os heróis da lista abaixo, no investimento informado.';

        return [
            "Monte {$teams} ".($teams === 1 ? 'Equipe' : 'Equipes').' '.$heroesInstruction,
            self::TIER_AND_CAPABILITIES,
            'Posições: 1 e 2 são a linha de frente; 3, 4 e 5, a linha de trás.',
            ...$this->objectiveRules($objective, $teams, $params),
        ];
    }

    /**
     * Pedido do Objetivo sem Formação: uma lista priorizada de investimentos.
     *
     * @return string[]
     */
    private function investmentRequest(Planning $planning): array
    {
        return [
            $planning === Planning::Target
                ? 'A lista abaixo traz os heróis mais bem avaliados do jogo. Diga, em ordem de prioridade, quais vale buscar e investir primeiro, e por quê.'
                : 'A lista abaixo é o meu Roster, com o investimento atual de cada herói. Diga, em ordem de prioridade, os próximos investimentos (ascensão, item de assinatura, mobília, gravação) que mais aumentam a força da minha Conta de jogo, e por quê.',
            'O tier de cada herói é a avaliação geral da comunidade (afk.guide), sem um conteúdo específico; "sem tier" quer dizer que ela não avaliou o herói. As capacidades (força 1 ou 2) resumem o que as habilidades fazem.',
        ];
    }

    /**
     * Planejamento alvo: o catálogo (sem o Roster) restrito aos heróis com tier
     * A ou melhor no Contexto, no fim de jogo, para caber no contexto do modelo.
     *
     * @return array<int, array{hero: Hero, userHero: null, tier: string}>
     */
    private function targetEntries(string $context): array
    {
        return Hero::query()
            ->with(['faction', 'heroClass', 'ratings'])
            ->where('is_active', true)
            ->where('pending_review', false)
            ->orderBy('name')
            ->get()
            ->map(fn (Hero $hero) => [
                'hero' => $hero,
                'userHero' => null,
                'tier' => $this->contextTier($hero, $context, HeroRating::STAGE_END),
            ])
            ->filter(fn (array $entry) => in_array($entry['tier'], array_slice(HeroRating::TIERS, 0, self::TARGET_TIER_COUNT), true))
            ->values()
            ->all();
    }

    private function contextTier(Hero $hero, string $context, string $stage): ?string
    {
        return $this->scorer->contextRatingForHero($hero->id, [$hero->id => $hero->ratings], $context, $stage)?->tier;
    }

    /**
     * Regras do Tipo de desafio que o modelo precisa respeitar para não sugerir
     * algo impossível no jogo.
     *
     * @return string[]
     */
    private function objectiveRules(AiExportObjective $objective, int $teams, array $params): array
    {
        $rules = [];
        if ($objective->uniqueTeams && $teams > 1) {
            $rules[] = 'Nenhum herói pode estar em duas Equipes.';
        }
        // O jogo nunca aceita as duas versões juntas, nem dentro de uma Equipe só.
        $rules[] = 'A versão base e a Desperta de um herói (ex.: Shemira e Shemira Desperta) são o mesmo personagem: use no máximo uma delas em toda a Formação.';

        return [...$rules, ...$objective->notes($params)];
    }

    /**
     * Bloco fixo e versionado, para comparar respostas de modelos diferentes
     * e, no futuro, importá-las como Formação.
     */
    private function answerFormat(int $teams): string
    {
        $block = ['AFK-BUILDER-RESPOSTA v1'];
        for ($i = 1; $i <= $teams; $i++) {
            $block[] = "Equipe {$i}: 1=<herói> | 2=<herói> | 3=<herói> | 4=<herói> | 5=<herói>";
        }

        return $this->fencedAnswer($block);
    }

    /**
     * Bloco fixo e versionado do Objetivo sem Formação: uma lista priorizada.
     */
    private function investmentAnswerFormat(): string
    {
        $block = ['AFK-BUILDER-INVESTIMENTO v1'];
        for ($i = 1; $i <= 5; $i++) {
            $block[] = "{$i}. <herói> — <investimento>";
        }

        return $this->fencedAnswer($block);
    }

    /**
     * @param  string[]  $block
     */
    private function fencedAnswer(array $block): string
    {
        return "Explique o raciocínio e termine com este bloco, exatamente neste formato:\n\n```\n"
            .implode("\n", $block)
            ."\n```";
    }

    /**
     * No Planejamento alvo não há herói do Roster: a linha leva só a ficha.
     */
    private function heroLine(Hero $hero, ?UserHero $uh, ?string $tier): string
    {
        $parts = [
            $hero->faction?->name,
            $hero->heroClass?->name,
            $tier ? "tier {$tier}" : 'sem tier',
        ];
        if ($uh) {
            array_push(
                $parts,
                $this->ascensionLabel($uh->ascension_code),
                $uh->level ? "nível {$uh->level}" : null,
                $uh->signature_item !== null ? "SI +{$uh->signature_item}" : null,
                $uh->furniture !== null ? "F{$uh->furniture}" : null,
                $uh->engraving !== null ? "E{$uh->engraving}" : null,
            );
            foreach ($uh->equippedMythicItems() as [$equipment, $level]) {
                $parts[] = "{$equipment->name} +{$level}";
            }
            if ($uh->artifact) {
                $parts[] = $uh->artifact->name.($uh->artifact_star_level ? " {$uh->artifact_star_level}★" : '');
            }
        }

        $line = "- **{$hero->name}**: ".implode(' · ', array_filter($parts));

        $capabilities = $hero->effectiveCapabilities();
        if ($capabilities !== []) {
            $line .= ' · Capacidades: '.implode(', ', array_map(
                fn (string $tag, int $strength) => "{$tag} {$strength}",
                array_keys($capabilities),
                $capabilities,
            ));
        }

        $stats = $uh ? $this->screenStats($uh) : [];
        if ($stats !== []) {
            $line .= ' · Atributos de tela: '.implode(', ', $stats);
        }

        return $line;
    }

    /**
     * Atributos de tela cadastrados, com o nome em inglês do jogo.
     *
     * @return string[]
     */
    private function screenStats(UserHero $uh): array
    {
        $labels = ['hp' => 'HP', 'attack' => 'ATK', 'defense' => 'DEF'];
        $stats = [];
        foreach (UserHero::STAT_FIELDS as $field) {
            $value = $uh->{$field};
            if ($value === null) {
                continue;
            }
            $label = $labels[$field] ?? Str::of($field)->replace('_', ' ')->title()->toString();
            $stats[] = $label.' '.rtrim(rtrim(number_format((float) $value, 2, '.', ''), '0'), '.');
        }

        return $stats;
    }

    /**
     * "ascended_5_stars" → "Ascended 5★", "mythic_plus" → "Mythic+".
     */
    private function ascensionLabel(?string $code): ?string
    {
        if ($code === null) {
            return null;
        }

        if (preg_match('/^ascended_(\d)_stars?$/', $code, $m)) {
            return "Ascended {$m[1]}★";
        }

        return Str::of($code)->replace('_plus', '+')->title()->toString();
    }
}
