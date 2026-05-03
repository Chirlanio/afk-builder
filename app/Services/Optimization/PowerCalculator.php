<?php

namespace App\Services\Optimization;

use App\Models\Artifact;
use App\Models\Beast;
use App\Models\Collection;
use App\Models\Hero;
use App\Models\HeroStat;
use App\Models\UserHero;

class PowerCalculator
{
    private const TIER_BASELINE = [
        'common' => ['ascension' => 3, 'level' => 100],
        'legendary' => ['ascension' => 9, 'level' => 160],
        'ascended' => ['ascension' => 12, 'level' => 240],
    ];

    /** Multiplicador aplicado ao buff de beast (que é por-tipo). Ajustável. */
    private const BEAST_BUFF_FACTOR = 0.5;

    public function forUserHero(UserHero $userHero, ?Hero $hero = null, ?Beast $beast = null): float
    {
        $base = $userHero->ascension_value * 100
            + $userHero->level
            + $userHero->signature_item * 5
            + $userHero->furniture * 30
            + $userHero->engraving * 0.5;

        // Bônus de Mythic Furniture: F3 desbloqueia uma habilidade transformadora,
        // F9 melhora pra versão mais forte. Reflete que esses unlocks valem
        // muito mais do que apenas o stat boost dos 30 power/peça.
        if ($userHero->furniture >= 9) {
            $base += 200;
        }
        if ($userHero->furniture >= 3) {
            $base += 100;
        }

        $base += $this->statContribution($this->safeStats($hero));

        // Equipamento na slot artefato/coleção (mutex no controller).
        if ($userHero->relationLoaded('artifact') && $userHero->artifact) {
            $base += $this->artifactContribution($userHero->artifact, $userHero->artifact_star_level);
        }
        if ($userHero->relationLoaded('collection') && $userHero->collection) {
            $base += $this->collectionContribution($userHero->collection);
        }

        // Buff do beast ativo na formação (aplicado apenas se hero tem type compatível).
        if ($beast && $hero?->type) {
            $base += $this->beastBuffForType($beast, $hero->type) * self::BEAST_BUFF_FACTOR;
        }

        return $base;
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

    public function forHero(Hero $hero, ?UserHero $userHero, ?Beast $beast = null): float
    {
        if ($userHero) {
            return $this->forUserHero($userHero, $hero, $beast);
        }

        $base = $this->targetBaseline($hero);
        // No modo target, beast ainda contribui (depende da formação, não do roster).
        if ($beast && $hero->type) {
            $base += $this->beastBuffForType($beast, $hero->type) * self::BEAST_BUFF_FACTOR;
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
        if ($stats === null) {
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
     * Retorna o buff bruto do beast para o tipo do herói. Multiplicador é aplicado
     * pelo chamador (BEAST_BUFF_FACTOR no forUserHero/forHero).
     */
    public function beastBuffForType(?Beast $beast, ?string $heroType): float
    {
        if (! $beast || $heroType === null) {
            return 0.0;
        }

        return match ($heroType) {
            'strength' => (float) $beast->strength_buff,
            'intelligence' => (float) $beast->intelligence_buff,
            'agility' => (float) $beast->agility_buff,
            default => 0.0,
        };
    }
}
