import { ChevronDown, ChevronRight, Sliders } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { EquipmentTier, Tier } from '@/types/afk';

export interface AdvancedFiltersState {
    siMin: number; // 0..40
    fMin: number; // 0..9
    engMin: number; // 0..80
    levelMin: number; // 0..240
    tierIds: Set<number>; // filtro multi por tier do catálogo
    hasArtifact: 'all' | 'yes' | 'no';
    hasCollection: 'all' | 'yes' | 'no';
    equipTierMin: EquipmentTier | null; // T1..T6, requer slot=null (qualquer slot) ou single slot
}

const SI_THRESHOLDS = [0, 10, 20, 30, 40];
const F_THRESHOLDS = [0, 3, 6, 9];
const ENG_THRESHOLDS = [0, 30, 60, 80];
const LEVEL_THRESHOLDS = [0, 160, 200, 220, 240];
const EQUIP_TIERS: EquipmentTier[] = ['T1', 'T2', 'T3', 'T4', 'T5', 'T6'];

interface Props {
    state: AdvancedFiltersState;
    tiers: Tier[];
    onChange: (next: AdvancedFiltersState) => void;
    onReset: () => void;
    isActive: boolean;
}

export function defaultAdvancedFilters(): AdvancedFiltersState {
    return {
        siMin: 0,
        fMin: 0,
        engMin: 0,
        levelMin: 0,
        tierIds: new Set(),
        hasArtifact: 'all',
        hasCollection: 'all',
        equipTierMin: null,
    };
}

export function isAdvancedFiltersActive(state: AdvancedFiltersState): boolean {
    return (
        state.siMin > 0 ||
        state.fMin > 0 ||
        state.engMin > 0 ||
        state.levelMin > 0 ||
        state.tierIds.size > 0 ||
        state.hasArtifact !== 'all' ||
        state.hasCollection !== 'all' ||
        state.equipTierMin !== null
    );
}

interface SegmentProps<T> {
    label: string;
    options: ReadonlyArray<T>;
    value: T;
    formatter?: (v: T) => string;
    onChange: (v: T) => void;
}

function Segment<T extends number | string>({ label, options, value, formatter, onChange }: SegmentProps<T>) {
    return (
        <div className="flex flex-wrap items-center gap-1.5">
            <span className="min-w-[60px] text-xs font-medium text-muted-foreground">{label}:</span>
            <div className="inline-flex flex-wrap rounded-md border bg-muted/30 p-0.5">
                {options.map((opt) => {
                    const active = opt === value;
                    return (
                        <button
                            key={String(opt)}
                            type="button"
                            onClick={() => onChange(opt)}
                            className={cn(
                                'rounded px-2 py-1 text-[11px] font-medium transition-colors',
                                active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground',
                            )}
                            aria-pressed={active}
                        >
                            {formatter ? formatter(opt) : opt === 0 || opt === '' ? 'Qualquer' : `≥${opt}`}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

interface TristateProps {
    label: string;
    value: 'all' | 'yes' | 'no';
    onChange: (v: 'all' | 'yes' | 'no') => void;
}

function Tristate({ label, value, onChange }: TristateProps) {
    const options: Array<{ v: 'all' | 'yes' | 'no'; label: string }> = [
        { v: 'all', label: 'Qualquer' },
        { v: 'yes', label: 'Com' },
        { v: 'no', label: 'Sem' },
    ];
    return (
        <div className="flex flex-wrap items-center gap-1.5">
            <span className="min-w-[60px] text-xs font-medium text-muted-foreground">{label}:</span>
            <div className="inline-flex rounded-md border bg-muted/30 p-0.5">
                {options.map((opt) => {
                    const active = opt.v === value;
                    return (
                        <button
                            key={opt.v}
                            type="button"
                            onClick={() => onChange(opt.v)}
                            className={cn(
                                'rounded px-2 py-1 text-[11px] font-medium transition-colors',
                                active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground',
                            )}
                            aria-pressed={active}
                        >
                            {opt.label}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

export function HeroesAdvancedFilters({ state, tiers, onChange, onReset, isActive }: Props) {
    const [open, setOpen] = useState(false);

    const toggleTier = (id: number) => {
        const next = new Set(state.tierIds);
        if (next.has(id)) next.delete(id);
        else next.add(id);
        onChange({ ...state, tierIds: next });
    };

    return (
        <div className="rounded-lg border bg-muted/20">
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                className="flex w-full items-center justify-between rounded-lg px-3 py-2 text-left text-xs font-medium hover:bg-muted/40"
                aria-expanded={open}
            >
                <span className="flex items-center gap-1.5">
                    {open ? <ChevronDown className="size-3.5" /> : <ChevronRight className="size-3.5" />}
                    <Sliders className="size-3.5" />
                    Filtros avançados
                    {isActive && (
                        <span className="rounded-full bg-amber-500/15 px-1.5 py-0.5 text-[10px] text-amber-600 dark:text-amber-400">
                            ativos
                        </span>
                    )}
                </span>
                {isActive && open && (
                    <span
                        role="button"
                        tabIndex={0}
                        onClick={(e) => {
                            e.stopPropagation();
                            onReset();
                        }}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter') {
                                e.stopPropagation();
                                onReset();
                            }
                        }}
                        className="cursor-pointer text-[11px] text-muted-foreground hover:text-foreground"
                    >
                        Limpar avançados
                    </span>
                )}
            </button>

            {open && (
                <div className="flex flex-col gap-2 border-t p-3">
                    <Segment
                        label="SI"
                        options={SI_THRESHOLDS}
                        value={state.siMin}
                        onChange={(v) => onChange({ ...state, siMin: v })}
                    />
                    <Segment
                        label="Móveis"
                        options={F_THRESHOLDS}
                        value={state.fMin}
                        onChange={(v) => onChange({ ...state, fMin: v })}
                    />
                    <Segment
                        label="Inscrição"
                        options={ENG_THRESHOLDS}
                        value={state.engMin}
                        onChange={(v) => onChange({ ...state, engMin: v })}
                    />
                    <Segment
                        label="Nível"
                        options={LEVEL_THRESHOLDS}
                        value={state.levelMin}
                        onChange={(v) => onChange({ ...state, levelMin: v })}
                    />

                    <Tristate
                        label="Artefato"
                        value={state.hasArtifact}
                        onChange={(v) => onChange({ ...state, hasArtifact: v })}
                    />
                    <Tristate
                        label="Coleção"
                        value={state.hasCollection}
                        onChange={(v) => onChange({ ...state, hasCollection: v })}
                    />

                    <div className="flex flex-wrap items-center gap-1.5">
                        <span className="min-w-[60px] text-xs font-medium text-muted-foreground">Equip mín:</span>
                        <div className="inline-flex flex-wrap rounded-md border bg-muted/30 p-0.5">
                            <button
                                type="button"
                                onClick={() => onChange({ ...state, equipTierMin: null })}
                                className={cn(
                                    'rounded px-2 py-1 text-[11px] font-medium transition-colors',
                                    state.equipTierMin === null ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground',
                                )}
                                aria-pressed={state.equipTierMin === null}
                            >
                                Qualquer
                            </button>
                            {EQUIP_TIERS.map((tier) => {
                                const active = state.equipTierMin === tier;
                                return (
                                    <button
                                        key={tier}
                                        type="button"
                                        onClick={() => onChange({ ...state, equipTierMin: tier })}
                                        className={cn(
                                            'rounded px-2 py-1 text-[11px] font-medium transition-colors',
                                            active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground',
                                        )}
                                        aria-pressed={active}
                                    >
                                        {tier}+
                                    </button>
                                );
                            })}
                        </div>
                        <span className="text-[10px] text-muted-foreground">
                            (todas as 4 peças do equipamento)
                        </span>
                    </div>

                    <div className="flex flex-wrap items-center gap-1.5">
                        <span className="min-w-[60px] text-xs font-medium text-muted-foreground">Tier:</span>
                        {tiers.map((tier) => {
                            const active = state.tierIds.has(tier.id);
                            return (
                                <button
                                    key={tier.id}
                                    type="button"
                                    onClick={() => toggleTier(tier.id)}
                                    className={cn(
                                        'rounded-full border px-2.5 py-0.5 text-[11px] font-medium transition-all',
                                        active
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border text-muted-foreground hover:border-foreground/30 hover:text-foreground',
                                    )}
                                    aria-pressed={active}
                                >
                                    {tier.name_pt}
                                </button>
                            );
                        })}
                    </div>

                    <p className="text-[10px] text-muted-foreground">
                        Filtros de investimento e equipamento aplicam-se apenas a heróis do seu roster.
                    </p>

                    {isActive && (
                        <div className="flex justify-end">
                            <Button size="sm" variant="ghost" onClick={onReset} className="text-xs">
                                Limpar todos
                            </Button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}

const EQUIP_TIER_RANK: Record<EquipmentTier, number> = {
    T1: 1,
    T2: 2,
    T3: 3,
    T4: 4,
    T5: 5,
    T6: 6,
};

export function tierCodeMeetsMin(tier: EquipmentTier | null | undefined, min: EquipmentTier): boolean {
    if (!tier) return false;
    return EQUIP_TIER_RANK[tier] >= EQUIP_TIER_RANK[min];
}
