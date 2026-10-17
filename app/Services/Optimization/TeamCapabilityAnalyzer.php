<?php

namespace App\Services\Optimization;

use App\Models\Hero;
use App\Services\Optimization\HeroCapabilityClassifier as Cap;

/**
 * Análise INFORMATIVA das capacidades de um time — o que ele cobre, o que
 * falta e avisos de contexto (ex: controle contra chefe). Não entra no score.
 *
 * Por que não pontua: validado em 2026-10-17 contra os comps recomendados da
 * comunidade, nenhum critério baseado em capacidades (cobertura, intensidade,
 * essenciais...) separou os comps fortes de times aleatórios — todos ficaram no
 * percentil ≤ 50. Comps fortes vencem por mecânicas específicas (reflexo de dano,
 * burst que dispensa tanque, empilhar charm) e pela força dos heróis, que o texto
 * das habilidades não revela. A informação ajuda o jogador a decidir; virar nota
 * só depois de pesos validados com dados (ex: tier ratings por modo).
 */
class TeamCapabilityAnalyzer
{
    public const KIND_PVE = 'pve';

    public const KIND_PVP = 'pvp';

    public const KIND_BOSS = 'boss';

    /** Código do ChallengeType → contexto. Default: PvE. */
    private const KIND_BY_CHALLENGE = [
        'arena_of_heroes' => self::KIND_PVP,
        'legends_championship' => self::KIND_PVP,
        'heroes_of_esperia' => self::KIND_PVP,
        'dream_realm' => self::KIND_BOSS,
        'cursed_realm' => self::KIND_BOSS,
        'dragon_trial' => self::KIND_BOSS,
    ];

    /** Capacidades que costumam fazer falta em cada contexto (avisa quando ausentes). */
    private const IMPORTANT = [
        self::KIND_PVE => [Cap::FRONTLINE, Cap::HEALING, Cap::CC_HARD, Cap::DAMAGE_AOE],
        self::KIND_PVP => [Cap::FRONTLINE, Cap::CC_HARD, Cap::BACKLINE_ACCESS, Cap::DAMAGE_SINGLE],
        self::KIND_BOSS => [Cap::DAMAGE_SINGLE, Cap::BUFF_ALLY, Cap::DEBUFF_ENEMY, Cap::ENERGY_GAIN],
    ];

    /** Capacidades sem efeito no contexto (chefes são imunes a controle). */
    private const INEFFECTIVE = [
        self::KIND_BOSS => [Cap::CC_HARD, Cap::CC_SOFT, Cap::ENERGY_DRAIN],
    ];

    /** Grupos de sustentação: nenhum provedor → aviso. */
    private const SUSTAIN = [Cap::HEALING, Cap::SHIELD, Cap::DAMAGE_REDUCTION, Cap::SELF_SUSTAIN, Cap::REVIVE];

    public static function kindFor(?string $challengeCode): string
    {
        return self::KIND_BY_CHALLENGE[$challengeCode] ?? self::KIND_PVE;
    }

    /**
     * @param  Hero[]  $heroes
     * @return array{kind: string, has_profiles: bool, covered: array<string, array<int, string>>, missing: array<int, string>, ineffective: array<string, array<int, string>>, no_sustain: bool}
     */
    public function analyze(array $heroes, ?string $challengeCode): array
    {
        $kind = self::kindFor($challengeCode);

        // tag => [slugs dos heróis que a fornecem]
        $providers = [];
        $hasProfiles = false;
        foreach ($heroes as $hero) {
            $profile = $hero->effectiveCapabilities();
            if (! empty($profile)) {
                $hasProfiles = true;
            }
            foreach (array_keys($profile) as $tag) {
                $providers[$tag][] = $hero->slug;
            }
        }

        $covered = [];
        foreach (Cap::ALL as $tag) {
            if (isset($providers[$tag])) {
                $covered[$tag] = $providers[$tag];
            }
        }

        $missing = $hasProfiles
            ? array_values(array_filter(self::IMPORTANT[$kind], fn ($tag) => ! isset($providers[$tag])))
            : [];

        $ineffective = [];
        foreach (self::INEFFECTIVE[$kind] ?? [] as $tag) {
            if (isset($providers[$tag])) {
                $ineffective[$tag] = $providers[$tag];
            }
        }

        $noSustain = $hasProfiles && empty(array_intersect(self::SUSTAIN, array_keys($providers)));

        return [
            'kind' => $kind,
            'has_profiles' => $hasProfiles,
            'covered' => $covered,
            'missing' => $missing,
            'ineffective' => $ineffective,
            'no_sustain' => $noSustain,
        ];
    }
}
