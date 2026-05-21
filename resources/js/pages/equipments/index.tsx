import { Head, Link, router } from '@inertiajs/react';
import { Crown, Filter, Flame, Heart, Shield, ShieldCheck, Sparkles, Sword, X, Zap } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { FACTION_THEME } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { FactionCode } from '@/types/afk';

type Slot = 'weapon' | 'helmet' | 'chest' | 'boots';
type HeroType = 'strength' | 'agility' | 'intelligence';
type QualityTier = 'mythic' | 'mythic_t1' | 'mythic_t2' | 'mythic_t3' | 'mythic_t4';

interface Equipment {
    id: number;
    slug: string;
    name: string;
    name_pt: string;
    set_code: string;
    slot: Slot;
    material: string;
    hero_type: HeroType;
    faction_code: FactionCode;
    faction_name_pt: string;
    quality_tier: QualityTier;
    power_rating: number;
    stats: Record<string, number>;
    set_bonuses: Record<string, number> | null;
    max_upgrade_level: number;
    upgrade_percent_per_level: number;
}

interface FactionOption {
    code: FactionCode;
    name_pt: string;
}

interface Filters {
    faction: string | null;
    slot: string | null;
    hero_type: string | null;
    quality_tier: string | null;
    set_code: string | null;
}

interface PageProps {
    equipments: Equipment[];
    factions: FactionOption[];
    filters: Filters;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Catálogo', href: '/heroes' },
    { title: 'Equipamentos Míticos', href: '/equipment' },
];

const SLOT_LABEL: Record<Slot, string> = {
    weapon: 'Arma',
    helmet: 'Elmo',
    chest: 'Peitoral',
    boots: 'Botas',
};

const SLOT_ICON: Record<Slot, React.ComponentType<{ className?: string }>> = {
    weapon: Sword,
    helmet: Crown,
    chest: ShieldCheck,
    boots: Shield,
};

const HERO_TYPE_LABEL: Record<HeroType, string> = {
    strength: 'Força',
    agility: 'Agilidade',
    intelligence: 'Inteligência',
};

const HERO_TYPE_BADGE: Record<HeroType, string> = {
    strength: 'border-rose-500/40 bg-rose-500/10 text-rose-300',
    agility: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300',
    intelligence: 'border-sky-500/40 bg-sky-500/10 text-sky-300',
};

const QUALITY_LABEL: Record<QualityTier, string> = {
    mythic: 'Mítico',
    mythic_t1: 'Mítico T1',
    mythic_t2: 'Mítico T2',
    mythic_t3: 'Mítico T3',
    mythic_t4: 'Mítico T4',
};

const QUALITY_BADGE: Record<QualityTier, string> = {
    mythic: 'bg-amber-500/15 text-amber-300 border-amber-500/40',
    mythic_t1: 'bg-orange-500/15 text-orange-300 border-orange-500/40',
    mythic_t2: 'bg-rose-500/15 text-rose-300 border-rose-500/40',
    mythic_t3: 'bg-fuchsia-500/15 text-fuchsia-300 border-fuchsia-500/40',
    mythic_t4: 'bg-gradient-to-r from-fuchsia-500/25 to-amber-500/25 text-fuchsia-200 border-fuchsia-400/60',
};

const SET_LABEL: Record<string, string> = {
    gaius: 'Set Gaius',
    ancelot: 'Set Ancelot',
    mystic: 'Set Místico',
};

const STAT_LABEL: Record<string, string> = {
    hp: 'PV',
    attack: 'ATQ',
    defense: 'DEF',
    accuracy: 'PREC',
    dodge: 'ESQUIVA',
    haste: 'PRESSA',
    crit_rate_pct: 'CRIT',
    physical_resistance_pct: 'RF',
    magic_resistance_pct: 'RM',
    hp_pct: 'PV',
    attack_pct: 'ATQ',
    defense_pct: 'DEF',
    hp_flat: 'PV (flat)',
    attack_flat: 'ATQ (flat)',
    defense_flat: 'DEF (flat)',
};

function formatStat(key: string, value: number): string {
    if (key.endsWith('_pct')) {
        return `${value}%`;
    }
    if (value >= 1000) {
        return value.toLocaleString('pt-BR');
    }
    return String(value);
}

function formatPower(n: number): string {
    if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(2)}M`;
    if (n >= 1_000) return `${(n / 1_000).toFixed(1)}k`;
    return n.toString();
}

export default function EquipmentsIndex({ equipments, factions, filters }: PageProps) {
    const [pendingFilters, setPendingFilters] = useState<Filters>(filters);

    const applyFilters = useCallback((next: Filters) => {
        const params: Record<string, string> = {};
        if (next.faction) params.faction = next.faction;
        if (next.slot) params.slot = next.slot;
        if (next.hero_type) params.hero_type = next.hero_type;
        if (next.quality_tier) params.quality_tier = next.quality_tier;
        if (next.set_code) params.set_code = next.set_code;
        router.get('/equipment', params, { preserveState: true, preserveScroll: true, replace: true });
    }, []);

    const updateFilter = useCallback(
        <K extends keyof Filters>(key: K, value: Filters[K]) => {
            const next = { ...pendingFilters, [key]: value };
            setPendingFilters(next);
            applyFilters(next);
        },
        [pendingFilters, applyFilters],
    );

    const clearAll = useCallback(() => {
        const empty: Filters = { faction: null, slot: null, hero_type: null, quality_tier: null, set_code: null };
        setPendingFilters(empty);
        applyFilters(empty);
    }, [applyFilters]);

    const hasActiveFilter = useMemo(
        () => Object.values(filters).some((v) => v !== null && v !== ''),
        [filters],
    );

    // Agrupa por set_code pra mostrar como seções
    const grouped = useMemo(() => {
        const map = new Map<string, Equipment[]>();
        for (const e of equipments) {
            if (! map.has(e.set_code)) map.set(e.set_code, []);
            map.get(e.set_code)!.push(e);
        }
        return Array.from(map.entries());
    }, [equipments]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Equipamentos Míticos" />

            <div className="flex flex-col gap-4 p-1">
                <header className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5">
                    <div className="flex items-start gap-3">
                        <Sparkles className="mt-0.5 size-5 text-amber-300" />
                        <div className="flex-1">
                            <h1 className="text-lg font-semibold text-amber-100">Equipamentos Míticos</h1>
                            <p className="mt-1 text-xs text-amber-100/60">
                                Sets de equipamentos nomeados (Gaius/Ancelot/Místico) com bônus de facção de 30%.
                                Tiers Mítico → T1 → T2 escalam linearmente; T3 e T4 substituem upgrades por <strong>set bonuses</strong>.
                            </p>
                        </div>
                    </div>

                    {/* Filtros */}
                    <div className="mt-4 flex flex-wrap items-center gap-2">
                        <span className="inline-flex items-center gap-1 text-[11px] text-amber-100/50">
                            <Filter className="size-3" />
                            Filtros:
                        </span>

                        <FilterSelect
                            label="Facção"
                            value={pendingFilters.faction ?? ''}
                            onChange={(v) => updateFilter('faction', v || null)}
                            options={factions.map((f) => ({ value: f.code, label: f.name_pt }))}
                        />
                        <FilterSelect
                            label="Slot"
                            value={pendingFilters.slot ?? ''}
                            onChange={(v) => updateFilter('slot', v || null)}
                            options={[
                                { value: 'weapon', label: 'Arma' },
                                { value: 'helmet', label: 'Elmo' },
                                { value: 'chest', label: 'Peitoral' },
                                { value: 'boots', label: 'Botas' },
                            ]}
                        />
                        <FilterSelect
                            label="Tipo"
                            value={pendingFilters.hero_type ?? ''}
                            onChange={(v) => updateFilter('hero_type', v || null)}
                            options={[
                                { value: 'strength', label: 'Força' },
                                { value: 'agility', label: 'Agilidade' },
                                { value: 'intelligence', label: 'Inteligência' },
                            ]}
                        />
                        <FilterSelect
                            label="Qualidade"
                            value={pendingFilters.quality_tier ?? ''}
                            onChange={(v) => updateFilter('quality_tier', v || null)}
                            options={[
                                { value: 'mythic', label: 'Mítico' },
                                { value: 'mythic_t1', label: 'Mítico T1' },
                                { value: 'mythic_t2', label: 'Mítico T2' },
                                { value: 'mythic_t3', label: 'Mítico T3' },
                                { value: 'mythic_t4', label: 'Mítico T4' },
                            ]}
                        />
                        <FilterSelect
                            label="Set"
                            value={pendingFilters.set_code ?? ''}
                            onChange={(v) => updateFilter('set_code', v || null)}
                            options={[
                                { value: 'gaius', label: 'Gaius' },
                                { value: 'ancelot', label: 'Ancelot' },
                                { value: 'mystic', label: 'Místico' },
                            ]}
                        />

                        {hasActiveFilter && (
                            <Button variant="ghost" size="sm" onClick={clearAll} className="ml-auto text-[11px]">
                                <X className="mr-1 size-3" />
                                Limpar
                            </Button>
                        )}
                    </div>
                </header>

                {equipments.length === 0 ? (
                    <div className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-8 text-center">
                        <Sparkles className="mx-auto mb-3 size-10 text-amber-100/30" />
                        <p className="text-sm font-semibold text-amber-100">Nenhum equipamento encontrado.</p>
                        <p className="mt-1 text-xs text-amber-100/50">
                            Tente remover filtros pra ver o catálogo completo.
                        </p>
                    </div>
                ) : (
                    grouped.map(([setCode, items]) => (
                        <section key={setCode} className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5">
                            <header className="mb-3 flex items-center gap-2">
                                <Flame className="size-4 text-amber-300" />
                                <h2 className="text-sm font-semibold text-amber-100">
                                    {SET_LABEL[setCode] ?? setCode}
                                </h2>
                                <span className="text-[10px] text-amber-100/40">{items.length} item(s)</span>
                            </header>
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {items.map((e) => (
                                    <EquipmentCard key={e.id} equipment={e} />
                                ))}
                            </div>
                        </section>
                    ))
                )}
            </div>
        </AppLayout>
    );
}

interface EquipmentCardProps {
    equipment: Equipment;
}

function EquipmentCard({ equipment }: EquipmentCardProps) {
    const SlotIcon = SLOT_ICON[equipment.slot];
    const factionTheme = FACTION_THEME[equipment.faction_code];

    return (
        <article className={cn('flex flex-col gap-2 rounded-xl border bg-black/30 p-3', factionTheme?.cardBorder ?? 'border-amber-400/20')}>
            <header className="flex items-start gap-2">
                <div className={cn('grid size-9 flex-shrink-0 place-items-center rounded-md', factionTheme?.cardGradient)}>
                    <SlotIcon className="size-4 text-amber-200" />
                </div>
                <div className="flex-1 min-w-0">
                    <h3 className="text-sm font-semibold text-amber-100 truncate">{equipment.name_pt}</h3>
                    <div className="mt-0.5 flex flex-wrap items-center gap-1 text-[10px]">
                        <span className={cn('rounded border px-1 py-0.5 font-mono uppercase tracking-wide', QUALITY_BADGE[equipment.quality_tier])}>
                            {QUALITY_LABEL[equipment.quality_tier]}
                        </span>
                        <span className={cn('rounded border px-1 py-0.5', HERO_TYPE_BADGE[equipment.hero_type])}>
                            {HERO_TYPE_LABEL[equipment.hero_type]}
                        </span>
                        <span className="rounded border border-amber-400/30 bg-amber-500/10 px-1 py-0.5 text-amber-200">
                            {SLOT_LABEL[equipment.slot]}
                        </span>
                    </div>
                </div>
            </header>

            {/* Power */}
            <div className="flex items-center justify-between rounded border border-amber-400/15 bg-black/40 px-2 py-1.5">
                <span className="flex items-center gap-1 text-[10px] uppercase tracking-wider text-amber-100/50">
                    <Zap className="size-3" />
                    Força
                </span>
                <span className="font-mono text-sm font-bold tabular-nums text-amber-100">
                    {formatPower(equipment.power_rating)}
                </span>
            </div>

            {/* Stats */}
            <dl className="grid gap-1 text-[11px]">
                {Object.entries(equipment.stats).map(([key, value]) => (
                    <div key={key} className="flex items-center justify-between rounded border border-amber-400/5 bg-black/20 px-2 py-0.5">
                        <dt className="text-amber-100/50">{STAT_LABEL[key] ?? key}</dt>
                        <dd className="font-mono text-amber-100">{formatStat(key, value)}</dd>
                    </div>
                ))}
            </dl>

            {/* Set bonuses (T3+) */}
            {equipment.set_bonuses && (
                <details className="rounded border border-fuchsia-500/30 bg-fuchsia-500/5 px-2 py-1.5 text-[10px]">
                    <summary className="cursor-pointer font-semibold text-fuchsia-300">Set Bonuses</summary>
                    <ul className="mt-1 grid gap-0.5">
                        {Object.entries(equipment.set_bonuses).map(([key, value]) => (
                            <li key={key} className="flex items-center justify-between text-fuchsia-200">
                                <span>{STAT_LABEL[key] ?? key}</span>
                                <span className="font-mono">
                                    {key.endsWith('_pct') ? `${value}%` : value.toLocaleString('pt-BR')}
                                </span>
                            </li>
                        ))}
                    </ul>
                </details>
            )}

            {/* Faction bonus + upgrades */}
            <footer className="flex items-center justify-between text-[10px]">
                <span className={cn('inline-flex items-center gap-1 rounded px-1.5 py-0.5', factionTheme?.cardGradient)}>
                    <Heart className="size-3 text-amber-200" />
                    {equipment.faction_name_pt}
                    <span className="font-mono">+30%</span>
                </span>
                {equipment.max_upgrade_level > 0 ? (
                    <span className="text-amber-100/40">
                        {equipment.max_upgrade_level} upgrades · +{equipment.upgrade_percent_per_level}% /nv
                    </span>
                ) : (
                    <span className="text-fuchsia-300/60">Sem upgrades</span>
                )}
            </footer>
        </article>
    );
}

interface FilterSelectProps {
    label: string;
    value: string;
    onChange: (v: string) => void;
    options: Array<{ value: string; label: string }>;
}

function FilterSelect({ label, value, onChange, options }: FilterSelectProps) {
    return (
        <label className="inline-flex items-center gap-1 text-[11px] text-amber-100/70">
            <span className="opacity-60">{label}:</span>
            <select
                value={value}
                onChange={(e) => onChange(e.target.value)}
                className="rounded-md border border-amber-400/20 bg-black/30 px-2 py-1 text-xs text-amber-100 focus:border-amber-400/50 focus:outline-none"
            >
                <option value="">Todos</option>
                {options.map((o) => (
                    <option key={o.value} value={o.value}>
                        {o.label}
                    </option>
                ))}
            </select>
        </label>
    );
}
