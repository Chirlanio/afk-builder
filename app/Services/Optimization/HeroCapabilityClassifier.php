<?php

namespace App\Services\Optimization;

/**
 * Lê as descrições das habilidades (texto em inglês da Fandom Wiki) e o papel
 * do herói (`role_label`) e produz o perfil de capacidades: o que o herói FAZ
 * numa luta — cura o time, controla, dá energia, alcança a linha de trás...
 *
 * Funciona por frase: a mesma palavra muda de sentido conforme quem é afetado
 * ("heals all allies" é cura do time; "heals himself" é autossustentação). As
 * regras foram calibradas contra as 906 habilidades do catálogo (2026-10-17).
 *
 * Classificador por regras não entende o jogo como um jogador: o resultado é
 * um ponto de partida revisável pelo admin (heroes.capabilities_manual).
 */
class HeroCapabilityClassifier
{
    public const DAMAGE_AOE = 'damage_aoe';

    public const DAMAGE_SINGLE = 'damage_single';

    public const DAMAGE_OVER_TIME = 'damage_over_time';

    public const HEALING = 'healing';

    public const SHIELD = 'shield';

    public const DAMAGE_REDUCTION = 'damage_reduction';

    public const SELF_SUSTAIN = 'self_sustain';

    public const REVIVE = 'revive';

    public const CC_HARD = 'cc_hard';

    public const CC_SOFT = 'cc_soft';

    public const CC_IMMUNITY = 'cc_immunity';

    public const DISPEL = 'dispel';

    public const ENERGY_GAIN = 'energy_gain';

    public const ENERGY_DRAIN = 'energy_drain';

    public const BUFF_ALLY = 'buff_ally';

    public const DEBUFF_ENEMY = 'debuff_enemy';

    public const BACKLINE_ACCESS = 'backline_access';

    public const FRONTLINE = 'frontline';

    /** Ordem estável da taxonomia (também usada pela UI). */
    public const ALL = [
        self::DAMAGE_AOE,
        self::DAMAGE_SINGLE,
        self::DAMAGE_OVER_TIME,
        self::HEALING,
        self::SHIELD,
        self::DAMAGE_REDUCTION,
        self::SELF_SUSTAIN,
        self::REVIVE,
        self::CC_HARD,
        self::CC_SOFT,
        self::CC_IMMUNITY,
        self::DISPEL,
        self::ENERGY_GAIN,
        self::ENERGY_DRAIN,
        self::BUFF_ALLY,
        self::DEBUFF_ENEMY,
        self::BACKLINE_ACCESS,
        self::FRONTLINE,
    ];

    /** Força máxima de uma capacidade por herói (2 = é um dos focos do kit). */
    public const MAX_STRENGTH = 2;

    private const ALLY = '(all(ied)?\s+(heroes|units)|allies|ally|allied|teammates?|friendly|the\s+team|their\s+team)';

    private const ENEMY = '(enem(y|ies)|opponents?|foes?|targets?|attackers?)';

    /**
     * Regras por frase. Cada entrada: [padrão obrigatório, contexto exigido na
     * mesma frase (ou null), contexto que anula (ou null)].
     */
    private const RULES = [
        self::DAMAGE_AOE => [
            ['/\b(all|nearby|surrounding|multiple)\s+(enemies|enemy\s+(units|heroes|targets))|enemies\s+(within|in\s+(range|front|the\s+area|its\s+path|her\s+path|his\s+path)|around|nearby)|every\s+enemy|\baoe\b|area\s+of\s+effect|most\s+(densely\s+)?concentrated\s+area/i', '/damag|attack/i', null],
        ],
        self::DAMAGE_SINGLE => [
            ['/(deals?|dealing|inflict\w*|strikes?)\s+.{0,40}damage\s+to\s+(an?|the|one|that|this|a\s+single|the\s+target(ed)?)\s+([\w\x27-]+\s+)?enemy\b/i', null, null],
            ['/\bsingle[-\s]target\b|\b(the|an)\s+enemy\s+with\s+the\s+(highest|lowest)/i', '/damag/i', null],
        ],
        self::DAMAGE_OVER_TIME => [
            ['/\b(bleed|poison|burn|corrosion|curse)\w*/i', '/damag/i', null],
            ['/damage\s+every\s+\d+(\.\d+)?\s*(s|seconds?)|continuous(ly)?\s+(damag|deal)|damage\s+over\s+(a\s+duration\s+of\s+)?\d+/i', null, null],
        ],
        self::HEALING => [
            ['/\bheal\w*|recover\w*\s+([\w\x27-]+\s+){0,3}(health|hp)|restor\w*\s+([\w\x27-]+\s+){0,4}(health|hp)|regenerat\w*/i', '/'.self::ALLY.'/i', null],
        ],
        self::SHIELD => [
            ['/\bshield\w*|\bbarrier\b/i', '/'.self::ALLY.'|\b(grants?|gives?|creates?|provid\w+)\b/i', '/(destroy|break|remove)\w*\s+([\w\x27-]+\s+){0,2}shield/i'],
        ],
        self::DAMAGE_REDUCTION => [
            ['/damage\s+(they\s+|that\s+\w+\s+)?(receive|take)\w*\s+(is\s+)?reduced|reduc\w+\s+(the\s+)?damage\s+(taken|received)|take\s+\d+%\s+less\s+damage|damage\s+reduction|damage\s+dealt\s+to\s+(the|an?|their|all)\s+all\w*\s+(is\s+)?reduc/i', '/'.self::ALLY.'/i', null],
            ['/\b(defense|armor)\s+(rating\s+)?(will\s+be\s+|is\s+|are\s+)?(increas|rais|boost)|(rais|increas|boost)\w*\s+([\w\x27-]+\s+){0,3}(defense|armor)/i', '/'.self::ALLY.'/i', null],
        ],
        self::SELF_SUSTAIN => [
            ['/life\s*(leech|steal)|\bleech\w*|lifesteal|siphon\w*|converted\s+into\s+(health|hp)|steal\w*\s+([\w\x27%-]+\s+){0,4}(current\s+|max\w*\s+)?(health|hp|life)\b/i', null, null],
            ['/\bheals?\s+(him|her|it|them)sel(f|ves)|\b(he|she|it)\s+(heals|recovers|restores)/i', null, null],
        ],
        self::REVIVE => [
            ['/reviv\w*|resurrect\w*|return\w*\s+to\s+(the\s+)?(battle|battlefield|life)|reborn|fatal\s+(damage|blow|hit|strike|attack)|retain\w*\s+1\s+(health|hp)|cannot\s+die|instead\s+of\s+dying|would\s+(otherwise\s+)?die/i', null, null],
        ],
        self::CC_HARD => [
            ['/\bstun\w*|\bfr(ee|o)z\w*|petrif\w*|knock\w*\s+([\w\x27-]+\s+){0,3}(back|up|down|over|into|away|airborne|the\s+air)|\bknocks?\b|\bsleep\w*|\basleep\b|\bfear\w*|\bcharm\w*|entangl\w*|\bpull\w*|unable\s+to\s+(act|move|attack)|cannot\s+(act|move|attack)|\bbanish\w*|\bhex\w*/i', '/'.self::ENEMY.'/i', '/immune\s+to/i'],
        ],
        self::CC_SOFT => [
            ['/prevent\w*\s+([\w\x27-]+\s+){0,3}from\s+(casting|using)|silenc\w*|\bslow\w*|haste\s+(rating\s+)?(is\s+)?(reduc|decreas|lower)|reduc\w+\s+([\w\x27-]+\s+){0,3}haste|\bblind\w*|disarm\w*/i', '/'.self::ENEMY.'/i', '/immune\s+to/i'],
            ['/\btaunt\w*|\bmock\w*\s+([\w\x27-]+\s+){0,2}enem|forc\w+\s+([\w\x27-]+\s+){0,3}(enem\w+|them)\s+to\s+(use\s+([\w\x27-]+\s+){0,2})?attack/i', null, null],
        ],
        self::CC_IMMUNITY => [
            ['/(immune|impervious)\s+to\s+(all\s+|any\s+)?(negative\s+|mind\s+)?(control|crowd|stun|cc\b)|cannot\s+be\s+(controlled|stunned|interrupted|disrupted)|immunity\s+to\s+control/i', null, null],
        ],
        self::DISPEL => [
            ['/(remov|cleans|purg|dispel|eras)\w*\s+([\w\x27-]+\s+){0,4}(buffs?|debuffs?|negative\s+(status|effects?)|positive\s+(status|effects?)|control\s+effects?|enhancements?)/i', null, null],
        ],
        self::ENERGY_GAIN => [
            ['/(gain|recover|restor|grant|giv|receiv|provid)\w*\s+([\w\x27-]+\s+){0,3}\d*\s*energy|energy\s+(points?\s+)?(is\s+|are\s+)?(restored|recovered|increased|gained)|energy\s+recovery|energy\s+regenerat/i', '/'.self::ALLY.'/i', '/(steal|drain|reduc|lose|loses|lost|deplet)\w*\s+([\w\x27-]+\s+){0,4}energy/i'],
        ],
        self::ENERGY_DRAIN => [
            ['/(steal|drain|reduc|deplet|drop)\w*\s+([\w\x27-]+\s+){0,5}energy|energy\s+([\w\x27-]+\s+){0,2}(is\s+|are\s+)?(stolen|drained|reduced|depleted|lost)|(lose|loses)\s+([\w\x27-]+\s+){0,2}\d*\s*energy/i', '/'.self::ENEMY.'/i', null],
        ],
        self::BUFF_ALLY => [
            // "grants the allied target ... 25% of the target's own Attack Rating and 25 Attack Speed points"
            ['/\bgrant\w*\s+(the\s+)?(all(y|ies|ied)\b).{0,80}(attack\s+rating|attack\s+speed|haste|crit\w*|accuracy)/i', null, null],
            ['/(attack|haste|crit\w*|damage|accuracy|speed|attack\s+speed|magic\s+piercing|physical\s+piercing)\s+(rating\s+|ratings\s+)?(will\s+be\s+|is\s+|are\s+|by\s+|gets?\s+)?(increas|boost|rais|enhanc|amplif)\w*|(increas|boost|rais|enhanc)\w*\s+([\w\x27-]+\s+){0,3}(attack|haste|crit\w*|damage|accuracy)/i', '/'.self::ALLY.'/i', '/enem(y|ies)\s+([\w\x27-]+\s+){0,3}(attack|haste|damage)/i'],
        ],
        self::DEBUFF_ENEMY => [
            ['/(armor|defense|accuracy|attack\s+rating|magic\s+resist\w*|physical\s+resist\w*|damage\s+resist\w*)\s+([\w\x27-]+\s+){0,2}(is\s+|are\s+|by\s+)?(reduc|decreas|lower|drop)\w*|(reduc|decreas|lower)\w*\s+([\w\x27-]+\s+){0,4}(armor|defense|accuracy|attack\s+rating|resist\w*)/i', '/'.self::ENEMY.'/i', null],
            ['/takes?\s+(\d+%\s+)?(more|additional|extra|increased)\s+damage|damage\s+dealt\s+to\s+(the\s+)?enem(y|ies)\s+(is\s+)?increas|enem(y|ies)\s+to\s+become\s+more\s+vulnerable|damage\s+(they\s+)?(take|receive)\w*\s+([\w\x27-]+\s+){0,3}increas/i', null, null],
        ],
        self::BACKLINE_ACCESS => [
            ['/furthest|farthest|back\s*-?\s*(row|line)|backline|rear\s+(row|line|enem)|behind\s+(the\s+)?enem|(enemy|enemies|target)\s+with\s+the\s+lowest\s+(health|hp)|weakest\s+enemy|lowest\s+(health|hp)\s+enemy/i', '/damag|attack|strike|teleport|leap|jump|dash|charg/i', '/all(y|ies|ied)\s+with\s+the\s+lowest/i'],
        ],
    ];

    /**
     * Papel da wiki → capacidades garantidas (força 1). Complementa o texto: um
     * herói marcado como "Tank" segura a linha de frente mesmo que nenhuma
     * frase diga isso com essas palavras.
     */
    private const ROLE_SEEDS = [
        'tank' => [self::FRONTLINE],
        'control' => [self::CC_HARD],
        'regen' => [self::HEALING],
        'buffer' => [self::BUFF_ALLY],
        'debuffer' => [self::DEBUFF_ENEMY],
        'assassin' => [self::BACKLINE_ACCESS, self::DAMAGE_SINGLE],
        'burst damage' => [self::DAMAGE_SINGLE],
        'aoe' => [self::DAMAGE_AOE],
        'continuous damage' => [self::DAMAGE_OVER_TIME],
    ];

    /**
     * Classifica uma descrição de habilidade. Retorna as tags em ordem estável.
     *
     * @return array<int, string>
     */
    public function classifyText(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        $found = [];
        foreach ($this->sentences($text) as $sentence) {
            foreach (self::RULES as $tag => $rules) {
                if (isset($found[$tag])) {
                    continue;
                }
                foreach ($rules as [$pattern, $requires, $vetoes]) {
                    if (preg_match($pattern, $sentence) !== 1) {
                        continue;
                    }
                    if ($requires !== null && preg_match($requires, $sentence) !== 1) {
                        continue;
                    }
                    if ($vetoes !== null && preg_match($vetoes, $sentence) === 1) {
                        continue;
                    }
                    $found[$tag] = true;
                    break;
                }
            }
        }

        return array_values(array_filter(self::ALL, fn ($tag) => isset($found[$tag])));
    }

    /**
     * Perfil do herói: força de cada capacidade (1 ou 2). Cada habilidade que
     * tem a capacidade soma 1, limitado a MAX_STRENGTH; o papel da wiki e a
     * classe garantem força mínima 1.
     *
     * @param  array<int, string|null>  $abilityTexts
     * @return array<string, int>
     */
    public function profile(array $abilityTexts, ?string $roleLabel = null, ?string $classCode = null): array
    {
        $strength = [];
        foreach ($abilityTexts as $text) {
            foreach ($this->classifyText($text) as $tag) {
                $strength[$tag] = min(self::MAX_STRENGTH, ($strength[$tag] ?? 0) + 1);
            }
        }

        $seeds = self::ROLE_SEEDS[strtolower(trim((string) $roleLabel))] ?? [];
        if ($classCode === 'tank') {
            $seeds[] = self::FRONTLINE;
        }
        foreach ($seeds as $tag) {
            $strength[$tag] = max(1, $strength[$tag] ?? 0);
        }

        $ordered = [];
        foreach (self::ALL as $tag) {
            if (isset($strength[$tag])) {
                $ordered[$tag] = $strength[$tag];
            }
        }

        return $ordered;
    }

    /**
     * @return array<int, string>
     */
    private function sentences(string $text): array
    {
        // Normaliza aspas/apóstrofos quebrados da wiki e separa por fim de frase.
        $text = str_replace(["\u{2019}", "\u{FFFD}"], "'", $text);
        $parts = preg_split('/(?<=[.!?])\s+|\n+/u', $text) ?: [$text];

        return array_values(array_filter(array_map('trim', $parts), fn ($s) => $s !== ''));
    }
}
