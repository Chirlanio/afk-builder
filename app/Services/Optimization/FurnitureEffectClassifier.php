<?php

namespace App\Services\Optimization;

class FurnitureEffectClassifier
{
    public const TAG_DAMAGE_BOOST = 'damage_boost';

    public const TAG_DAMAGE_REDUCTION = 'damage_reduction';

    public const TAG_HEALING = 'healing';

    public const TAG_CC_CONTROL = 'cc_control';

    public const TAG_CC_IMMUNITY = 'cc_immunity';

    public const TAG_DISPEL = 'dispel';

    public const TAG_REVIVE = 'revive';

    public const TAG_ENERGY_BOOST = 'energy_boost';

    public const TAG_SHIELD = 'shield';

    public const TAG_SURVIVABILITY = 'survivability';

    public const TAG_ABILITY_UPTIME = 'ability_uptime';

    public const ALL_TAGS = [
        self::TAG_DAMAGE_BOOST,
        self::TAG_DAMAGE_REDUCTION,
        self::TAG_HEALING,
        self::TAG_CC_CONTROL,
        self::TAG_CC_IMMUNITY,
        self::TAG_DISPEL,
        self::TAG_REVIVE,
        self::TAG_ENERGY_BOOST,
        self::TAG_SHIELD,
        self::TAG_SURVIVABILITY,
        self::TAG_ABILITY_UPTIME,
    ];

    /**
     * Regras de classificação: cada tag tem padrões regex (case-insensitive).
     * Texto que cai em múltiplas categorias recebe múltiplas tags — comum (ex: "heals
     * by X and removes debuffs" → healing + dispel).
     */
    private const RULES = [
        self::TAG_DAMAGE_BOOST => [
            '/damage.{0,40}?(is\s+)?(also\s+)?increased/i',
            '/damage\s+bonus.{0,30}?increased/i',
            '/damage\s+(dealt|done)/i',
            '/deals?\s+\d+%\s+(more|additional|extra|increased)/i',
            '/damage\s+by\s+\d+%/i',
            '/damage\s+amplification/i',
            '/(attack|atk).{0,20}?(rating|stat)?.{0,10}?(is\s+)?(are\s+)?increased/i',
            '/attack\s+(rating|ratings)?.{0,15}?(by\s+)?\d+%/i',
            '/critical\s+(strike|hit|damage)/i',
            '/additional\s+damage/i',
            '/factional\s+advantage\s+damage/i',
        ],
        self::TAG_DAMAGE_REDUCTION => [
            '/damage\s+(taken\s+)?(is\s+)?reduced/i',
            '/damage\s+reduction/i',
            '/takes?\s+\d+%\s+less\s+damage/i',
            '/reduces?\s+damage\s+taken/i',
            '/incoming\s+damage/i',
            '/damage\s+immunity/i',
        ],
        self::TAG_HEALING => [
            '/heals?\b/i',
            '/healing\b/i',
            '/restores?\s+(\d+%\s+)?(of\s+)?(their\s+)?(maximum\s+)?(hp|health|life)/i',
            '/(hp|health)\s+(is\s+)?regenerat/i',
            '/regen(eration)?/i',
            '/recover(s|y|ed)?\s+hp/i',
        ],
        self::TAG_CC_CONTROL => [
            '/\bstun(s|ned|ning)?\b/i',
            '/\bfreez(es|ing|e)\b/i',
            '/\bsilenc(es|ed|ing|e)\b/i',
            '/\bfear(s|ed|ing)?\b/i',
            '/petrif(ies|y|ied)/i',
            '/\btaunt(s|ed|ing)?\b/i',
            '/\bsleep(s|ing|y)?\b/i',
            '/\bcharm(s|ed|ing)?\b/i',
            '/\bknock\s*(back|down|up)/i',
            '/(reduces?|lose[s]?)\s+\d+\s+haste/i',
            '/haste\s+is\s+reduced/i',
        ],
        self::TAG_CC_IMMUNITY => [
            '/immune\s+to\s+(negative\s+)?control/i',
            '/immune\s+to\s+(stun|freeze|silence|petrif|crowd\s*control|cc)/i',
            '/cannot\s+be\s+(stunned|frozen|silenced|petrified|controlled)/i',
            '/(removes?|cleanses?|purges?)\s+(all\s+)?(negative\s+)?(control|debuff|crowd\s*control)/i',
            '/control\s+(abilities|effects)\s+are\s+removed/i',
        ],
        self::TAG_DISPEL => [
            '/(removes?|cleanses?|purges?|dispels?)\s+(all\s+)?(positive\s+)?(buff|enhancement|effect)s/i',
            '/(removes?|cleanses?)\s+\d+\s+buff/i',
        ],
        self::TAG_REVIVE => [
            '/\brevive(s|d|ing)?\b/i',
            '/resurrect(s|ed|ion|ing)?/i',
            '/respawn(s|ed|ing)?/i',
            '/comes?\s+back\s+to\s+life/i',
            '/return\s+to\s+(the\s+)?battle/i',
        ],
        self::TAG_ENERGY_BOOST => [
            '/\d+\s+energy\s+points?/i',
            '/gain[s]?\s+(\d+\s+)?energy/i',
            '/energy\s+recovery/i',
            '/energy\s+(is\s+)?(restored|recovered|increased)/i',
            '/ultimate\s+cooldown/i',
        ],
        self::TAG_SHIELD => [
            '/\bshield(s|ed|ing)?\b/i',
            '/\bbarrier\b/i',
            '/absorbs?\s+\d+/i',
            '/damage\s+absorption/i',
        ],
        self::TAG_SURVIVABILITY => [
            '/life\s+leech/i',
            '/life\s*steal/i',
            '/\bdodge\b/i',
            '/\bblock\b/i',
            '/tenacity/i',
            '/\barmor\b/i',
            '/(physical|magic)\s+resistance/i',
            '/damage\s+resistance/i',
            '/max(imum)?\s+hp\s+(is\s+)?increased/i',
            '/defense\s+(rating|ratings)?.{0,15}?(by\s+)?\d+%/i',
            '/defense.{0,20}?(is\s+)?(are\s+)?increased/i',
            '/\d+%\s+(of\s+)?defense/i',
        ],
        self::TAG_ABILITY_UPTIME => [
            '/cooldown.{0,80}?(reduced|by\s+\d+)/i',
            '/instantly\s+triggered\s+again/i',
            '/cast\s+again/i',
            '/(additional|extra)\s+cast/i',
            '/ability\s+can\s+be\s+used\s+\d+\s+(more|additional)\s+times?/i',
            '/duration\s+(is\s+)?(increased|extended)/i',
            '/(reduces?|reduce)\s+(the\s+)?cooldown/i',
        ],
    ];

    /**
     * Classifica o texto de uma furniture ability. Retorna lista de tags em ordem
     * estável (ordem da taxonomia). Texto vazio ou null retorna [].
     *
     * @return array<int, string>
     */
    public function classify(?string $text): array
    {
        if ($text === null || trim($text) === '') {
            return [];
        }

        $tags = [];
        foreach (self::RULES as $tag => $patterns) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $text) === 1) {
                    $tags[] = $tag;
                    break;
                }
            }
        }

        return $tags;
    }
}
