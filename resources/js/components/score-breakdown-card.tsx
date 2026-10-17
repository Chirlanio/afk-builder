import { TeamCapabilityPanel } from '@/components/capability-badges';
import type { WeightOverrides } from '@/components/weight-tuner-sheet';
import type { TeamCapabilityAnalysis } from '@/lib/capabilities';
import { cn } from '@/lib/utils';
import type { ScoreBreakdownPayload } from '@/types/afk';

interface Props {
    score: ScoreBreakdownPayload;
    className?: string;
    /** Compact = sem header, só linhas. Usado em popovers. */
    compact?: boolean;
    /** Quando passado, recalcula o total client-side com esses pesos. */
    weights?: WeightOverrides;
}

interface ComponentMeta {
    key: keyof ScoreBreakdownPayload;
    weightKey: keyof WeightOverrides;
    label: string;
    tooltip: string;
    /** Componentes "absolutos" (power) têm escala maior; "normalizados" (faction, class) ficam ~0-1. */
    scale?: 'normalized' | 'absolute';
}

const COMPONENTS: ComponentMeta[] = [
    {
        key: 'power',
        weightKey: 'power_weight',
        label: 'Poder',
        tooltip: 'Soma do power dos heróis (asc, level, SI, móveis, equipamentos, artefato). Normalizado por 2000.',
        scale: 'absolute',
    },
    {
        key: 'faction_bonus',
        weightKey: 'faction_bonus_weight',
        label: 'Bônus de facção',
        tooltip: '5-stack ou 3+2. Celestiais entram como wildcard; dimensionais não contam.',
    },
    {
        key: 'class_balance',
        weightKey: 'class_balance_weight',
        label: 'Balanço de classes',
        tooltip: 'Tank + Support + variedade. Penaliza 4+ heróis da mesma classe.',
    },
    {
        key: 'synergy',
        weightKey: 'synergy_weight',
        label: 'Sinergias',
        tooltip: 'Pares de heróis conhecidos no jogo (Ezio+Lucius, Talene+Gwyneth, etc).',
    },
    {
        key: 'tier_rating',
        weightKey: 'tier_rating_weight',
        label: 'Tier rating',
        tooltip: 'Rating S+/S/A/B etc. dos heróis para o modo da formação (afk.guide).',
    },
    {
        key: 'feature_bonus',
        weightKey: 'feature_weight',
        label: 'Destaques',
        tooltip: 'Bônus por heróis marcados como "PvP Top", "Early Carry", etc. para o modo da formação.',
    },
    {
        key: 'furniture_effects',
        weightKey: 'furniture_effect_weight',
        label: 'Móveis F3/F9',
        tooltip: 'Effects das habilidades F3/F9 (damage_boost, cc_immunity, etc.) ponderados pelo modo.',
    },
    {
        key: 'engraving_bonus',
        weightKey: 'engraving_weight',
        label: 'Inscrições',
        tooltip: 'Bônus por milestones de Inscrições (E30/E60/E80) atingidos pelos heróis do time.',
    },
];

function formatValue(value: number, scale: 'normalized' | 'absolute' = 'normalized'): string {
    if (scale === 'absolute') return value.toFixed(2);
    return value.toFixed(3);
}

export function ScoreBreakdownCard({ score, className, compact = false, weights }: Props) {
    // Total custom: recalcula client-side somando component × weight.
    // Quando weights não é passado, usa o total do backend (com pesos default do challenge_type).
    const customTotal = weights
        ? COMPONENTS.reduce((sum, { key, weightKey }) => sum + ((score[key] as number | undefined) ?? 0) * weights[weightKey], 0)
        : null;
    const hasCustomWeights = customTotal !== null;

    return (
        <div className={cn('bg-card/50 rounded-lg border text-sm', className)}>
            {!compact && (
                <div className="border-b px-3 py-2">
                    <h4 className="text-muted-foreground text-xs font-semibold tracking-wider uppercase">Detalhamento do score</h4>
                </div>
            )}
            <div className="divide-border/40 divide-y">
                {COMPONENTS.map(({ key, weightKey, label, tooltip, scale }) => {
                    const value = (score[key] as number | undefined) ?? 0;
                    const weight = weights?.[weightKey];
                    const isZero = Math.abs(value) < 0.001;
                    return (
                        <div
                            key={key}
                            className={cn('flex items-center justify-between px-3 py-1.5 text-xs', isZero && 'text-muted-foreground/50')}
                            title={tooltip}
                        >
                            <span>{label}</span>
                            <span className="font-mono tabular-nums">
                                {formatValue(value, scale)}
                                {weight !== undefined && <span className="text-muted-foreground ml-1.5 text-[10px]">×{weight.toFixed(1)}</span>}
                            </span>
                        </div>
                    );
                })}
                <div className="bg-muted/30 flex items-center justify-between px-3 py-2">
                    <span className="text-xs font-semibold tracking-wide uppercase">Total{hasCustomWeights && ' (custom)'}</span>
                    <span className="flex items-baseline gap-2">
                        <span className={cn('font-mono text-base font-bold tabular-nums', hasCustomWeights && 'text-amber-400')}>
                            {(customTotal ?? score.total).toFixed(2)}
                        </span>
                        {hasCustomWeights && (
                            <span className="text-muted-foreground font-mono text-[10px] line-through">{score.total.toFixed(2)}</span>
                        )}
                    </span>
                </div>
                <TeamCapabilityPanel analysis={score.details?.capabilities as TeamCapabilityAnalysis | undefined} />
            </div>
        </div>
    );
}
