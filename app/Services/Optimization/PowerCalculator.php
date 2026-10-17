<?php

namespace App\Services\Optimization;

use App\Models\Artifact;
use App\Models\Beast;
use App\Models\Collection;
use App\Models\Hero;
use App\Models\HeroStat;
use App\Models\SignatureItemCost;
use App\Models\UserHero;

class PowerCalculator
{
    private const TIER_BASELINE = [
        'common' => ['ascension' => 3, 'level' => 100],
        'legendary' => ['ascension' => 9, 'level' => 160],
        'ascended' => ['ascension' => 12, 'level' => 240],
    ];

    /**
     * Fator aplicado ao custo cumulativo de emblemas investidos no SI para
     * converter em pontos de power. Calibrado para manter SI 30 em ~144
     * (próximo dos 150 da fórmula linear antiga `signature_item * 5`), e
     * dar peso adicional ao caminho SI 31-40 que custa 1050 emblemas a mais
     * (exclusivo Celestial/Hypogean). Resultado típico:
     *   SI 30 → 144 (era 150)
     *   SI 38 → 258 (era 190)
     *   SI 40 → 306 (era 200)
     */
    private const SI_CUMULATIVE_FACTOR = 0.2;

    /** Multiplicador default aplicado ao buff de beast (sem tier cadastrado). */
    private const BEAST_BUFF_FACTOR = 0.5;

    /**
     * Engraving (Inscrições) milestones. Linear `engraving * 0.5` permanece, mas
     * cada milestone atingido injeta um bônus discreto que reflete o unlock real
     * no jogo (E30 = ativação inicial, E60 = camada de combate avançada, E80 =
     * pico exclusivo de heroes Ascended Celestial/Hypogean).
     *
     * Resultado típico:
     *   Eng 0  → 0
     *   Eng 30 → 30*0.5 + 50           = 65
     *   Eng 60 → 60*0.5 + 50 + 100     = 180
     *   Eng 80 → 80*0.5 + 50 + 100 + 50= 240
     */
    private const ENGRAVING_MILESTONE_E30 = 50.0;

    private const ENGRAVING_MILESTONE_E60 = 100.0;

    private const ENGRAVING_MILESTONE_E80 = 50.0;

    /**
     * Multiplicador por tier do beast (afk.guide). Beasts S+ valem mais que tier C/D.
     * Aplicado em vez de BEAST_BUFF_FACTOR quando o beast tem `tier` cadastrado.
     */
    private const BEAST_TIER_FACTOR = [
        'S+' => 1.0,
        'S' => 0.8,
        'A' => 0.6,
        'B' => 0.5,
        'C' => 0.4,
        'D' => 0.3,
        'F' => 0.2,
    ];

    /**
     * Fatores aplicados aos atributos REAIS do UserHero (valores do jogo, ordem de
     * 10^5 em HP em estágios avançados). Calibrados a partir de snapshots empíricos:
     *
     *   Shemira Lendário (Graveborn Mage):    HP=642254  ATK=41552  DEF=9239
     *   Thoran Lendário (Graveborn Warrior):  HP=642794  ATK=41471  DEF=9258
     *   Baden Lendário (Celestial Warrior):   HP=890481  ATK=75446  DEF=13374
     *   Thane Lendário (Mauler Warrior):      HP=890628  ATK=75438  DEF=13372
     *   Ivan Elite (Lightbearer Tank):        HP=995903  ATK=69194  DEF=14051
     *   Ivan Elite+ (Lightbearer Tank):       HP=997282  ATK=69292  DEF=14076
     *
     * Observação: stats são essencialmente idênticos entre heros do mesmo arquétipo
     * (mage/warrior/tank) e mesma ascensão — variações <0.1%. Elite→Elite+ adiciona
     * só ~0.14%. A função existente `statContribution(HeroStat)` usa fatores 10x
     * maiores porque opera sobre stats do CATÁLOGO (Lv 1, ordem 10^3).
     *
     * Com os fatores abaixo, a contribuição fica comparável ao bloco `ascension*100
     * + level` (~760 pra Lendário Lv 160), permitindo escalonamento intuitivo:
     *   Shemira:  ~574  → mage padrão
     *   Baden:    ~888  → warrior celestial (~55% acima da mage)
     *   Ivan:     ~912  → tank lightbearer (mais HP que warriors)
     */
    private const REAL_STAT_HP_FACTOR = 0.0005;

    private const REAL_STAT_ATK_FACTOR = 0.005;

    private const REAL_STAT_DEF_FACTOR = 0.005;

    private const REAL_STAT_CRIT_FACTOR = 0.3;

    private const REAL_STAT_ACC_FACTOR = 0.2;

    private const REAL_STAT_DODGE_FACTOR = 0.2;

    private const REAL_STAT_HASTE_FACTOR = 0.2;

    /**
     * Catalog stats are level 1 base stats (HP in the thousands). Anything above
     * this came from an account's game screen (10^5 to 10^9 HP); with the level 1
     * factors it reached 10^5 power per hero and decided every suggestion alone.
     */
    private const CATALOG_STAT_HP_MAX = 100_000;

    /** Limiar pra considerar que `UserHero->hp` tem valor REAL do jogo (não vazio). */
    private const REAL_STAT_HP_THRESHOLD = 1000;

    public function forUserHero(UserHero $userHero, ?Hero $hero = null, ?Beast $beast = null, int $beastLevel = Beast::MAX_LEVEL): float
    {
        $siInvested = SignatureItemCost::cumulativeCostUpToLevel((int) $userHero->signature_item);

        $base = $userHero->ascension_value * 100
            + $userHero->level
            + $siInvested * self::SI_CUMULATIVE_FACTOR
            + $userHero->furniture * 30
            + $userHero->engraving * 0.5
            + $this->engravingMilestoneBonus((int) $userHero->engraving);

        // Bônus de Mythic Furniture: F3 desbloqueia uma habilidade transformadora,
        // F9 melhora pra versão mais forte. Reflete que esses unlocks valem
        // muito mais do que apenas o stat boost dos 30 power/peça.
        if ($userHero->furniture >= 9) {
            $base += 200;
        }
        if ($userHero->furniture >= 3) {
            $base += 100;
        }

        // Quando o UserHero tem stats REAIS do jogo preenchidos (player snapshot),
        // usamos eles direto. Caso contrário, caímos pro baseline do catálogo (Lv 1)
        // + Equipamento Mítico (sets Gaius/Ancelot/Místico, com bônus de facção).
        // Com stats reais o equipamento NÃO entra: a tela de atributos do jogo já
        // mostra os valores com ele equipado, e somar de novo contava duas vezes.
        $base += $this->hasRealStats($userHero)
            ? $this->realStatContribution($userHero)
            : $this->statContribution($this->safeStats($hero)) + $this->mythicEquipmentContribution($userHero, $hero);

        // Equipamento na slot artefato/coleção (mutex no controller).
        if ($userHero->relationLoaded('artifact') && $userHero->artifact) {
            $base += $this->artifactContribution($userHero->artifact, $userHero->artifact_star_level);
        }
        if ($userHero->relationLoaded('collection') && $userHero->collection) {
            $base += $this->collectionContribution($userHero->collection);
        }

        // Buff do beast ativo na formação. Soma buffs do scope do type + scope team,
        // escala pelo level (level/MAX_LEVEL) e aplica fator do tier da besta.
        if ($beast) {
            $base += $this->beastBuffForType($beast, $hero?->type, $beastLevel) * $this->beastFactor($beast);
        }

        return $base;
    }

    /**
     * Bônus discreto somado ao power quando o user atinge E30/E60/E80.
     * Cumulativo: E80 paga os três milestones.
     */
    public function engravingMilestoneBonus(int $engraving): float
    {
        $bonus = 0.0;
        if ($engraving >= 30) {
            $bonus += self::ENGRAVING_MILESTONE_E30;
        }
        if ($engraving >= 60) {
            $bonus += self::ENGRAVING_MILESTONE_E60;
        }
        if ($engraving >= 80) {
            $bonus += self::ENGRAVING_MILESTONE_E80;
        }

        return $bonus;
    }

    /**
     * Milestone atingido pelo nível de engraving informado, ou null se < 30.
     */
    public static function engravingMilestoneLabel(int $engraving): ?string
    {
        if ($engraving >= 80) {
            return 'E80';
        }
        if ($engraving >= 60) {
            return 'E60';
        }
        if ($engraving >= 30) {
            return 'E30';
        }

        return null;
    }

    /**
     * Multiplicador do buff de beast baseado no tier (afk.guide). Cai pro default 0.5
     * se o beast não tem tier cadastrado.
     */
    private function beastFactor(Beast $beast): float
    {
        if ($beast->tier !== null && isset(self::BEAST_TIER_FACTOR[$beast->tier])) {
            return self::BEAST_TIER_FACTOR[$beast->tier];
        }

        return self::BEAST_BUFF_FACTOR;
    }

    public function targetBaseline(Hero $hero): float
    {
        $tierCode = $hero->tier?->code ?? 'common';
        $baseline = self::TIER_BASELINE[$tierCode] ?? self::TIER_BASELINE['common'];

        return $baseline['ascension'] * 100 + $baseline['level'] + $this->statContribution($this->safeStats($hero));
    }

    /**
     * Acessa stats apenas se a relation foi pré-carregada (evita lazy load).
     * Em testes unitários, sem connection ao DB, lazy load falharia.
     */
    private function safeStats(?Hero $hero): ?HeroStat
    {
        if (! $hero) {
            return null;
        }
        if (! $hero->relationLoaded('stats')) {
            return null;
        }

        return $hero->stats;
    }

    public function forHero(Hero $hero, ?UserHero $userHero, ?Beast $beast = null, int $beastLevel = Beast::MAX_LEVEL): float
    {
        if ($userHero) {
            return $this->forUserHero($userHero, $hero, $beast, $beastLevel);
        }

        $base = $this->targetBaseline($hero);
        // No modo target, beast ainda contribui (depende da formação, não do roster).
        if ($beast) {
            $base += $this->beastBuffForType($beast, $hero->type, $beastLevel) * $this->beastFactor($beast);
        }

        return $base;
    }

    /**
     * Stats base do herói (Nv 1) viram um diferenciador entre heróis no scoring.
     * HP/ATK/DEF têm peso linear; demais (crit/acc/dodge/haste) entram com peso menor.
     * Quando stats não foram cadastradas, retorna 0.0 (não penaliza, mas também não diferencia).
     */
    public function statContribution(?HeroStat $stats): float
    {
        if ($stats === null || (int) ($stats->hp ?? 0) > self::CATALOG_STAT_HP_MAX) {
            return 0.0;
        }

        return ($stats->hp ?? 0) * 0.1
            + ($stats->attack ?? 0) * 1.0
            + ($stats->defense ?? 0) * 0.7
            + ($stats->crit_rate ?? 0) * 0.3
            + ($stats->crit_amp ?? 0) * 0.3
            + ($stats->accuracy ?? 0) * 0.2
            + ($stats->dodge ?? 0) * 0.2
            + ($stats->haste ?? 0) * 0.2;
    }

    /**
     * Contribuição dos Equipamentos Míticos equipados nos 4 slots (Gaius/Ancelot/
     * Místico). Agrega stats com upgrade aplicado, multiplica por 1.30 quando o
     * hero pertence à mesma faction do item (bônus de facção), e converte os
     * stats agregados pra power score via REAL_STAT_*_FACTOR (mesma escala do
     * realStatContribution pra coerência de magnitude).
     *
     * Stats `_pct` (crit_rate_pct, physical_resistance_pct, magic_resistance_pct)
     * contribuem com peso menor — refletem percent caps que se traduzem em power
     * efetivo só quando o hero está sob ataque/causando dano.
     *
     * Set bonuses (T3/T4) ficam de fora de propósito: o EquipmentSeeder guarda
     * valores absolutos de fim de jogo (+1,39M de HP, +582k de ATK só na arma),
     * numa escala que não bate com a calibração dos REAL_STAT_*_FACTOR (heróis
     * Lendários, ~890k de HP). Somados, um set completo valia ~10k de power —
     * quase 4 heróis Ascended no máximo — e passava a decidir sozinho o
     * otimizador. Recalibrar só com snapshot real de um herói equipado com T3+.
     */
    public function mythicEquipmentContribution(UserHero $userHero, ?Hero $hero = null): float
    {
        $items = $userHero->equippedMythicItems();
        if (empty($items)) {
            return 0.0;
        }

        $heroFactionId = $hero?->faction?->id ?? $hero?->faction_id;
        $aggregate = [];

        foreach ($items as [$equipment, $level]) {
            $stats = $equipment->statsAtLevel($level);
            $factionMatch = $heroFactionId !== null
                && (int) $equipment->faction_id === (int) $heroFactionId;
            $stats = $equipment->applyFactionBonus($stats, $factionMatch);

            foreach ($stats as $key => $value) {
                $aggregate[$key] = ($aggregate[$key] ?? 0.0) + (float) $value;
            }
        }

        return $this->convertAggregateStatsToPower($aggregate);
    }

    /**
     * Converte stats agregados (HP/ATK/DEF/CRIT/etc) em pontos de power usando
     * a mesma escala calibrada em realStatContribution. Stats `_pct` são
     * tratados como pontos diretos com peso modesto (consistent com crit_rate).
     *
     * @param  array<string, float|int>  $aggregate
     */
    private function convertAggregateStatsToPower(array $aggregate): float
    {
        $power = 0.0;
        foreach ($aggregate as $key => $value) {
            $power += match ($key) {
                'hp' => $value * self::REAL_STAT_HP_FACTOR,
                'attack' => $value * self::REAL_STAT_ATK_FACTOR,
                'defense' => $value * self::REAL_STAT_DEF_FACTOR,
                'crit_rate_pct' => $value * self::REAL_STAT_CRIT_FACTOR,
                'accuracy' => $value * self::REAL_STAT_ACC_FACTOR,
                'dodge' => $value * self::REAL_STAT_DODGE_FACTOR,
                'haste' => $value * self::REAL_STAT_HASTE_FACTOR,
                'physical_resistance_pct', 'magic_resistance_pct' => $value * 0.5,
                default => 0.0,
            };
        }

        return $power;
    }

    /**
     * Detecta se o UserHero tem stats REAIS do jogo preenchidos. Heurística: HP
     * acima de REAL_STAT_HP_THRESHOLD (1k) — stats de catálogo (Lv 1) ficam abaixo
     * disso, e nenhum hero in-game tem menos que ~30k de HP em qualquer ascensão.
     */
    public function hasRealStats(UserHero $userHero): bool
    {
        return (int) ($userHero->hp ?? 0) > self::REAL_STAT_HP_THRESHOLD;
    }

    /**
     * Contribuição dos stats REAIS do UserHero (valores in-game). Multipliers MUITO
     * menores que `statContribution()` porque opera sobre números de ordem 10^5
     * (HP) em vez de 10^3 (stats de catálogo Lv 1).
     *
     * Calibrado a partir de snapshots reais — ver as constantes REAL_STAT_*_FACTOR.
     */
    public function realStatContribution(UserHero $userHero): float
    {
        return ((int) ($userHero->hp ?? 0)) * self::REAL_STAT_HP_FACTOR
            + ((int) ($userHero->attack ?? 0)) * self::REAL_STAT_ATK_FACTOR
            + ((int) ($userHero->defense ?? 0)) * self::REAL_STAT_DEF_FACTOR
            + ((float) ($userHero->crit_rate ?? 0)) * self::REAL_STAT_CRIT_FACTOR
            + ((float) ($userHero->accuracy ?? 0)) * self::REAL_STAT_ACC_FACTOR
            + ((float) ($userHero->dodge ?? 0)) * self::REAL_STAT_DODGE_FACTOR
            + ((float) ($userHero->haste ?? 0)) * self::REAL_STAT_HASTE_FACTOR;
    }

    /**
     * Contribuição do artefato equipado: bônus base por estrelas + somatório
     * leve dos atributos numéricos cadastrados (HP%, CRIT, HASTE, etc).
     */
    public function artifactContribution(?Artifact $artifact, ?int $starLevel): float
    {
        if (! $artifact) {
            return 0.0;
        }

        $stars = max(1, $starLevel ?? 1);
        $base = 50.0 * $stars; // 1★=50, 2★=100, ..., 5★=250

        foreach ($artifact->attributes ?? [] as $attr) {
            $rawValue = (string) ($attr['value'] ?? '0');
            $numeric = (float) preg_replace('/[^0-9.]/', '', $rawValue);
            $base += $numeric * 2; // multiplicador genérico
        }

        return $base;
    }

    /**
     * Contribuição da coleção da Galeria Tenebrosa: usa os bônus HP/ATK/DEF
     * com a mesma escala da statContribution.
     */
    public function collectionContribution(?Collection $collection): float
    {
        if (! $collection) {
            return 0.0;
        }

        return $collection->hp_bonus * 0.1
            + $collection->attack_bonus * 1.0
            + $collection->defense_bonus * 0.7;
    }

    /**
     * Soma todos os buffs (percentuais) que a besta aplica ao herói, considerando:
     * - Scope do type do herói (buffs_strength/intelligence/agility) — aplicado só pra heroes daquele type
     * - Scope team (buffs_team) — aplicado a todos os heroes
     * Escala linearmente pelo level (level/MAX_LEVEL). Multiplicador BEAST_TIER_FACTOR
     * é aplicado pelo chamador.
     */
    public function beastBuffForType(?Beast $beast, ?string $heroType, int $beastLevel = Beast::MAX_LEVEL): float
    {
        if (! $beast) {
            return 0.0;
        }

        $sum = 0.0;

        // Scope do type do hero (omitido pra heroes sem type)
        if ($heroType !== null) {
            $typeScope = match ($heroType) {
                'strength' => $beast->buffs_strength,
                'intelligence' => $beast->buffs_intelligence,
                'agility' => $beast->buffs_agility,
                default => null,
            };
            if (is_array($typeScope)) {
                foreach ($typeScope as $value) {
                    $sum += (float) $value;
                }
            }
        }

        // Scope team — sempre aplicado
        if (is_array($beast->buffs_team)) {
            foreach ($beast->buffs_team as $value) {
                $sum += (float) $value;
            }
        }

        $level = max(1, min(Beast::MAX_LEVEL, $beastLevel));
        $levelFactor = $level / Beast::MAX_LEVEL;

        return $sum * $levelFactor;
    }
}
