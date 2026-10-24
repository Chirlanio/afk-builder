<?php

namespace App\Services\AiExport;

use App\Models\GameAccount;
use App\Models\HeroRating;
use App\Models\UserHero;
use App\Services\Optimization\ChallengeTypeMode;
use App\Services\Optimization\RosterStageInferrer;
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

    public function __construct(
        private readonly TierRatingScorer $scorer,
        private readonly RosterStageInferrer $stageInferrer,
    ) {}

    /**
     * @return array{markdown: string, warning: ?string}
     */
    public function build(GameAccount $account, string $objective, string $planning, array $params = []): array
    {
        $definition = AiExportObjectives::get($objective);
        $context = ChallengeTypeMode::forCode($definition['challenge_type']);

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
        $ratingsByHeroId = $userHeroes->mapWithKeys(fn (UserHero $uh) => [$uh->hero_id => $uh->hero->ratings])->all();

        $teams = $definition['teams'];
        $lines = [
            "# AFK Arena — {$definition['label']}",
            '',
            'Gerado pelo AFK Builder em '.now()->format('d/m/Y').' · Estágio da conta: '.self::STAGE_LABELS[$stage],
            '',
            '## Pedido',
            '',
            'Você é um especialista em AFK Arena. Quero montar '.($definition['goal'])($params).'.',
            "Monte {$teams} ".($teams === 1 ? 'Equipe' : 'Equipes').' usando **apenas** os heróis da lista abaixo, no investimento informado.',
            'O tier de cada herói é a avaliação da comunidade (afk.guide) para este conteúdo; "sem tier" quer dizer que ela não avaliou o herói aqui. As capacidades (força 1 ou 2) resumem o que as habilidades fazem.',
            'Posições: 1 e 2 são a linha de frente; 3, 4 e 5, a linha de trás.',
            'Responda em português do Brasil, mas escreva os nomes dos heróis em inglês, exatamente como na lista.',
            '',
            '## Heróis',
            '',
        ];
        foreach ($userHeroes as $uh) {
            $rating = $this->scorer->contextRatingForHero($uh->hero_id, $ratingsByHeroId, $context, $stage);
            $lines[] = $this->heroLine($uh, $rating?->tier);
        }

        array_push($lines, '', '## Formato da resposta', '', $this->answerFormat($teams));

        return ['markdown' => implode("\n", $lines)."\n", 'warning' => null];
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

        return "Explique o raciocínio e termine com este bloco, exatamente neste formato:\n\n```\n"
            .implode("\n", $block)
            ."\n```";
    }

    private function heroLine(UserHero $uh, ?string $tier): string
    {
        $hero = $uh->hero;
        $parts = [
            $hero->faction?->name,
            $hero->heroClass?->name,
            $tier ? "tier {$tier}" : 'sem tier',
            $this->ascensionLabel($uh->ascension_code),
            $uh->level ? "nível {$uh->level}" : null,
            $uh->signature_item !== null ? "SI +{$uh->signature_item}" : null,
            $uh->furniture !== null ? "F{$uh->furniture}" : null,
            $uh->engraving !== null ? "E{$uh->engraving}" : null,
        ];
        foreach ($uh->equippedMythicItems() as [$equipment, $level]) {
            $parts[] = "{$equipment->name} +{$level}";
        }
        if ($uh->artifact) {
            $parts[] = $uh->artifact->name.($uh->artifact_star_level ? " {$uh->artifact_star_level}★" : '');
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

        $stats = $this->screenStats($uh);
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
