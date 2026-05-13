import { cn } from '@/lib/utils';
import type { ScoreBreakdownPayload } from '@/types/afk';

interface Props {
    score: ScoreBreakdownPayload;
    className?: string;
    /** Compact = sem header, só linhas. Usado em popovers. */
    compact?: boolean;
}

interface ComponentMeta {
    key: keyof ScoreBreakdownPayload;
    label: string;
    tooltip: string;
    /** Componentes "absolutos" (power) têm escala maior; "normalizados" (faction, class) ficam ~0-1. */
    scale?: 'normalized' | 'absolute';
}

const COMPONENTS: ComponentMeta[] = [
    {
        key: 'power',
        label: 'Poder',
        tooltip: 'Soma do power dos heróis (asc, level, SI, móveis, equipamentos, artefato). Normalizado por 2000.',
        scale: 'absolute',
    },
    {
        key: 'faction_bonus',
        label: 'Bônus de facção',
        tooltip: '5-stack ou 3+2. Celestiais entram como wildcard; dimensionais não contam.',
    },
    {
        key: 'class_balance',
        label: 'Balanço de classes',
        tooltip: 'Tank + Support + variedade. Penaliza 4+ heróis da mesma classe.',
    },
    {
        key: 'synergy',
        label: 'Sinergias',
        tooltip: 'Pares de heróis conhecidos no jogo (Ezio+Lucius, Talene+Gwyneth, etc).',
    },
    {
        key: 'tier_rating',
        label: 'Tier rating',
        tooltip: 'Rating S+/S/A/B etc. dos heróis para o modo da formação (afk.guide).',
    },
    {
        key: 'feature_bonus',
        label: 'Destaques',
        tooltip: 'Bônus por heróis marcados como "PvP Top", "Early Carry", etc. para o modo da formação.',
    },
    {
        key: 'furniture_effects',
        label: 'Móveis F3/F9',
        tooltip: 'Effects das habilidades F3/F9 (damage_boost, cc_immunity, etc.) ponderados pelo modo.',
    },
];

function formatValue(value: number, scale: 'normalized' | 'absolute' = 'normalized'): string {
    if (scale === 'absolute') return value.toFixed(2);
    return value.toFixed(3);
}

export function ScoreBreakdownCard({ score, className, compact = false }: Props) {
    return (
        <div className={cn('rounded-lg border bg-card/50 text-sm', className)}>
            {!compact && (
                <div className="border-b px-3 py-2">
                    <h4 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground">Detalhamento do score</h4>
                </div>
            )}
            <div className="divide-y divide-border/40">
                {COMPONENTS.map(({ key, label, tooltip, scale }) => {
                    const value = (score[key] as number | undefined) ?? 0;
                    const isZero = Math.abs(value) < 0.001;
                    return (
                        <div
                            key={key}
                            className={cn('flex items-center justify-between px-3 py-1.5 text-xs', isZero && 'text-muted-foreground/50')}
                            title={tooltip}
                        >
                            <span>{label}</span>
                            <span className="font-mono tabular-nums">{formatValue(value, scale)}</span>
                        </div>
                    );
                })}
                <div className="flex items-center justify-between bg-muted/30 px-3 py-2">
                    <span className="text-xs font-semibold uppercase tracking-wide">Total</span>
                    <span className="font-mono text-base font-bold tabular-nums">{score.total.toFixed(2)}</span>
                </div>
            </div>
        </div>
    );
}
