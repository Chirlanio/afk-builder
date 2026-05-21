import { Head, Link, router } from '@inertiajs/react';
import { ChevronRight, Crown, Flame, Plus, Scale, Sparkles, Star, Target, TrendingUp, Trophy, Users, X, Zap } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { EngravingBadge } from '@/components/engraving-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { ascendedStars, CLASS_LABEL, FACTION_THEME, TIER_THEME } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { ClassCode, EquipmentQuality, FactionCode, TierCode } from '@/types/afk';

type UpgradeKind = 'signature_item' | 'furniture' | 'engraving' | 'ascension';

interface MythicEquipmentSlot {
    name_pt: string;
    quality_tier: EquipmentQuality;
    level: number;
    max_upgrade_level: number;
}

interface UpgradeSuggestion {
    kind: UpgradeKind;
    from: number;
    to: number;
    power_gain: number;
    cost: number;
    score: number;
    reasons: string[];
    resource_cost: Record<string, number> | null;
    affordable: boolean | null;
}

interface ComparisonHero {
    user_hero_id: number;
    hero_id: number;
    slug: string;
    name: string;
    portrait_url: string | null;
    faction_code: FactionCode;
    faction_name_pt: string;
    class_code: ClassCode;
    tier_code: TierCode;
    tier_name_pt: string;
    type: string | null;
    level: number;
    ascension_value: number;
    ascension_code: string | null;
    signature_item: number;
    furniture: number;
    engraving: number;
    equipment: {
        weapon: MythicEquipmentSlot | null;
        helmet: MythicEquipmentSlot | null;
        chest: MythicEquipmentSlot | null;
        boots: MythicEquipmentSlot | null;
    };
    artifact: { name: string; stars: number } | null;
    collection: { name: string } | null;
    power: {
        total: number;
        stat_contribution: number;
        equipment_contribution: number;
        artifact_contribution: number;
        collection_contribution: number;
    };
    upgrades: UpgradeSuggestion[];
}

interface RosterOption {
    slug: string;
    name: string;
    faction_code: FactionCode;
    class_code: ClassCode;
    tier_code: TierCode;
}

interface PageProps {
    heroes: ComparisonHero[];
    rosterOptions: RosterOption[];
    minHeroes: number;
    maxHeroes: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Roster', href: '/heroes' },
    { title: 'Comparar', href: '/roster/compare' },
];

const KIND_LABEL: Record<UpgradeKind, string> = {
    signature_item: 'Assinatura',
    furniture: 'Móveis',
    engraving: 'Inscrições',
    ascension: 'Ascensão',
};

const KIND_BADGE: Record<UpgradeKind, string> = {
    signature_item: 'border-amber-500/50 bg-amber-500/15 text-amber-300',
    furniture: 'border-sky-500/50 bg-sky-500/15 text-sky-300',
    engraving: 'border-rose-500/50 bg-rose-500/15 text-rose-300',
    ascension: 'border-emerald-500/50 bg-emerald-500/15 text-emerald-300',
};

const EQUIPMENT_SLOTS: Array<{ key: 'weapon' | 'helmet' | 'chest' | 'boots'; label: string }> = [
    { key: 'weapon', label: 'Arma' },
    { key: 'helmet', label: 'Capacete' },
    { key: 'chest', label: 'Peitoral' },
    { key: 'boots', label: 'Botas' },
];

function formatStep(kind: UpgradeKind, from: number, to: number): string {
    switch (kind) {
        case 'signature_item':
            return `AS ${from} → ${to}`;
        case 'furniture':
            return `F${from} → F${to}`;
        case 'engraving':
            return `E${from} → E${to}`;
        case 'ascension':
            return `Asc ${from} → ${to}`;
    }
}

function formatNumber(n: number): string {
    return n.toLocaleString('pt-BR');
}

/**
 * Retorna o índice do herói "vencedor" para uma métrica numérica (maior = melhor).
 * Quando há empate, retorna `null` (todos destacados igual ou nenhum).
 */
function winnerIndex(values: number[]): number | null {
    if (values.length === 0) return null;
    const max = Math.max(...values);
    if (max <= 0) return null;
    const winners = values.map((v, i) => (v === max ? i : -1)).filter((i) => i !== -1);
    return winners.length === 1 ? winners[0] : null;
}

export default function RosterCompare({ heroes, rosterOptions, minHeroes, maxHeroes }: PageProps) {
    const [searchTerm, setSearchTerm] = useState('');
    const [pickerOpen, setPickerOpen] = useState(false);

    const selectedSlugs = useMemo(() => heroes.map((h) => h.slug), [heroes]);
    const canAddMore = heroes.length < maxHeroes;

    const filteredOptions = useMemo(() => {
        const term = searchTerm.trim().toLowerCase();
        return rosterOptions
            .filter((o) => !selectedSlugs.includes(o.slug))
            .filter((o) => (term === '' ? true : o.name.toLowerCase().includes(term)))
            .slice(0, 30);
    }, [rosterOptions, selectedSlugs, searchTerm]);

    const navigate = useCallback((nextSlugs: string[]) => {
        router.get('/roster/compare', { heroes: nextSlugs }, { preserveState: true, preserveScroll: true, replace: true });
    }, []);

    const addHero = useCallback(
        (slug: string) => {
            if (selectedSlugs.includes(slug) || selectedSlugs.length >= maxHeroes) return;
            navigate([...selectedSlugs, slug]);
            setSearchTerm('');
            setPickerOpen(false);
        },
        [selectedSlugs, maxHeroes, navigate],
    );

    const removeHero = useCallback(
        (slug: string) => {
            navigate(selectedSlugs.filter((s) => s !== slug));
        },
        [selectedSlugs, navigate],
    );

    const clearAll = useCallback(() => {
        navigate([]);
        setSearchTerm('');
    }, [navigate]);

    // Métricas comparativas (vencedor por linha)
    const winners = useMemo(() => {
        if (heroes.length < 2) return {} as Record<string, number | null>;
        return {
            total_power: winnerIndex(heroes.map((h) => h.power.total)),
            level: winnerIndex(heroes.map((h) => h.level)),
            ascension: winnerIndex(heroes.map((h) => h.ascension_value)),
            signature_item: winnerIndex(heroes.map((h) => h.signature_item)),
            furniture: winnerIndex(heroes.map((h) => h.furniture)),
            engraving: winnerIndex(heroes.map((h) => h.engraving)),
            equipment_contribution: winnerIndex(heroes.map((h) => h.power.equipment_contribution)),
            stat_contribution: winnerIndex(heroes.map((h) => h.power.stat_contribution)),
        };
    }, [heroes]);

    if (rosterOptions.length === 0) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Comparar heróis" />
                <div className="flex flex-col items-center justify-center gap-3 rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-12 text-center">
                    <Users className="size-12 text-amber-100/30" />
                    <h2 className="text-xl font-semibold text-amber-100">Roster vazio</h2>
                    <p className="max-w-md text-sm text-amber-100/60">
                        Adicione heróis ao seu roster pra começar a comparar.
                    </p>
                    <Link
                        href="/heroes"
                        className="mt-2 inline-flex items-center gap-1 rounded-md bg-amber-500/15 px-3 py-1.5 text-xs font-medium text-amber-200 hover:bg-amber-500/25"
                    >
                        Ir para o catálogo
                        <ChevronRight className="size-3" />
                    </Link>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Comparar heróis" />

            <div className="flex flex-col gap-4 p-1">
                <header className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5">
                    <div className="flex items-start gap-3">
                        <Scale className="mt-0.5 size-5 text-amber-300" />
                        <div className="flex-1">
                            <h1 className="text-lg font-semibold text-amber-100">Comparar heróis lado a lado</h1>
                            <p className="mt-1 text-xs text-amber-100/60">
                                Compare até {maxHeroes} heróis do seu roster: power total, milestones de investimento,
                                equipamento e próximos upgrades sugeridos. Mínimo de {minHeroes} pra ver os destaques.
                            </p>
                        </div>
                    </div>

                    {/* Chips dos heroes selecionados + picker */}
                    <div className="mt-4 flex flex-wrap items-center gap-2">
                        {heroes.map((h) => (
                            <span
                                key={h.slug}
                                className="inline-flex items-center gap-1.5 rounded-full border border-amber-400/30 bg-amber-500/10 px-2.5 py-1 text-xs text-amber-100"
                            >
                                {h.name}
                                <button
                                    type="button"
                                    onClick={() => removeHero(h.slug)}
                                    className="rounded-full p-0.5 hover:bg-amber-500/25"
                                    title="Remover"
                                >
                                    <X className="size-3" />
                                </button>
                            </span>
                        ))}

                        {canAddMore && (
                            <div className="relative">
                                <button
                                    type="button"
                                    onClick={() => setPickerOpen((o) => !o)}
                                    className="inline-flex items-center gap-1 rounded-full border border-dashed border-amber-400/40 bg-black/30 px-2.5 py-1 text-xs text-amber-200/80 hover:border-amber-400/70 hover:bg-amber-500/10"
                                >
                                    <Plus className="size-3" />
                                    Adicionar herói
                                </button>

                                {pickerOpen && (
                                    <div className="absolute left-0 top-full z-10 mt-1 w-72 rounded-lg border border-amber-400/20 bg-zinc-950/95 p-2 shadow-xl">
                                        <input
                                            type="text"
                                            autoFocus
                                            value={searchTerm}
                                            onChange={(e) => setSearchTerm(e.target.value)}
                                            placeholder="Buscar pelo nome…"
                                            className="w-full rounded-md border border-amber-400/20 bg-black/40 px-2 py-1.5 text-xs text-amber-100 placeholder:text-amber-100/30 focus:border-amber-400/50 focus:outline-none"
                                        />
                                        <ul className="mt-2 max-h-56 overflow-y-auto">
                                            {filteredOptions.length === 0 ? (
                                                <li className="px-2 py-3 text-center text-[11px] text-amber-100/40">
                                                    Nenhum herói encontrado.
                                                </li>
                                            ) : (
                                                filteredOptions.map((o) => (
                                                    <li key={o.slug}>
                                                        <button
                                                            type="button"
                                                            onClick={() => addHero(o.slug)}
                                                            className="flex w-full items-center justify-between gap-2 rounded-md px-2 py-1.5 text-left text-xs text-amber-100 hover:bg-amber-500/15"
                                                        >
                                                            <span className="truncate">{o.name}</span>
                                                            <span className="font-mono text-[10px] text-amber-100/40">
                                                                {CLASS_LABEL[o.class_code]}
                                                            </span>
                                                        </button>
                                                    </li>
                                                ))
                                            )}
                                        </ul>
                                    </div>
                                )}
                            </div>
                        )}

                        {heroes.length > 0 && (
                            <Button variant="ghost" size="sm" onClick={clearAll} className="ml-auto text-[11px]">
                                Limpar tudo
                            </Button>
                        )}
                    </div>
                </header>

                {heroes.length === 0 ? (
                    <div className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-8 text-center">
                        <Users className="mx-auto mb-3 size-10 text-amber-100/30" />
                        <p className="text-sm font-semibold text-amber-100">
                            Selecione pelo menos {minHeroes} heróis pra começar a comparar.
                        </p>
                        <p className="mt-1 text-xs text-amber-100/50">
                            Use o botão acima ou{' '}
                            <Link href="/roster/analytics" className="text-amber-300 underline hover:text-amber-200">
                                vá ao Top 10 do Analytics
                            </Link>{' '}
                            pra pegar candidatos.
                        </p>
                    </div>
                ) : heroes.length < minHeroes ? (
                    <div className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-8 text-center">
                        <Scale className="mx-auto mb-3 size-10 text-amber-100/30" />
                        <p className="text-sm font-semibold text-amber-100">
                            Adicione mais {minHeroes - heroes.length} herói(s) pra ver os destaques.
                        </p>
                    </div>
                ) : (
                    <ComparisonGrid heroes={heroes} winners={winners} />
                )}
            </div>
        </AppLayout>
    );
}

interface ComparisonGridProps {
    heroes: ComparisonHero[];
    winners: Record<string, number | null>;
}

function ComparisonGrid({ heroes, winners }: ComparisonGridProps) {
    const gridCols = heroes.length === 2 ? 'grid-cols-2' : heroes.length === 3 ? 'grid-cols-3' : 'grid-cols-4';

    return (
        <section className={cn('grid gap-3', gridCols)}>
            {heroes.map((h, idx) => (
                <HeroCard key={h.slug} hero={h} index={idx} winners={winners} />
            ))}
        </section>
    );
}

interface HeroCardProps {
    hero: ComparisonHero;
    index: number;
    winners: Record<string, number | null>;
}

function HeroCard({ hero, index, winners }: HeroCardProps) {
    const factionTheme = FACTION_THEME[hero.faction_code];
    const tierTheme = TIER_THEME[hero.tier_code];
    const stars = ascendedStars(hero.ascension_value);

    return (
        <article className={cn('flex flex-col gap-3 rounded-2xl border bg-zinc-950/60 p-4', factionTheme?.cardBorder ?? 'border-amber-400/15')}>
            {/* Header com portrait */}
            <header className="flex items-start gap-3">
                {hero.portrait_url ? (
                    <img
                        src={hero.portrait_url}
                        alt={hero.name}
                        loading="lazy"
                        className={cn('size-14 rounded-md object-cover object-top', factionTheme?.cardGradient)}
                    />
                ) : (
                    <div className="size-14 rounded-md bg-zinc-800" />
                )}
                <div className="flex-1 min-w-0">
                    <Link href={`/heroes/${hero.slug}`} className="block truncate text-sm font-semibold text-amber-100 hover:underline">
                        {hero.name}
                    </Link>
                    <div className="mt-0.5 flex flex-wrap items-center gap-1 text-[10px] text-amber-100/60">
                        <span className={cn('rounded px-1 py-0.5 font-bold', tierTheme?.badge)}>{hero.tier_name_pt}</span>
                        <span>{hero.faction_name_pt}</span>
                        <span>·</span>
                        <span>{CLASS_LABEL[hero.class_code]}</span>
                    </div>
                </div>
            </header>

            {/* Power total */}
            <div className={cn('rounded-lg border bg-black/30 p-3', winners.total_power === index ? 'border-amber-400/60 ring-1 ring-amber-400/30' : 'border-amber-400/10')}>
                <div className="flex items-center justify-between">
                    <span className="flex items-center gap-1 text-[10px] uppercase tracking-wider text-amber-100/50">
                        <TrendingUp className="size-3" />
                        Power total
                    </span>
                    {winners.total_power === index && (
                        <span className="inline-flex items-center gap-0.5 rounded-full bg-amber-400/20 px-1.5 py-0.5 text-[9px] font-bold text-amber-200">
                            <Trophy className="size-2.5" />
                            Maior
                        </span>
                    )}
                </div>
                <div className="mt-1 font-mono text-xl font-bold tabular-nums text-amber-100">{formatNumber(hero.power.total)}</div>
            </div>

            {/* Métricas comparáveis */}
            <dl className="grid gap-1.5 text-xs">
                <Metric label="Nível" value={hero.level} highlight={winners.level === index} maxLevel={tierTheme?.maxLevel} />
                <Metric
                    label="Ascensão"
                    value={hero.ascension_value}
                    suffix={stars.total > 0 ? <StarsBadge filled={stars.filled} total={stars.total} /> : null}
                    highlight={winners.ascension === index}
                />
                <Metric label="Assinatura" value={hero.signature_item} formatter={(v) => `AS ${v}`} highlight={winners.signature_item === index} />
                <Metric label="Móveis" value={hero.furniture} formatter={(v) => `F${v} / 9`} highlight={winners.furniture === index} />
                <Metric
                    label="Inscrições"
                    value={hero.engraving}
                    formatter={(v) => `E${v}`}
                    suffix={hero.engraving >= 30 ? <EngravingBadge engraving={hero.engraving} size="xs" /> : null}
                    highlight={winners.engraving === index}
                />
            </dl>

            {/* Equipamentos Míticos */}
            <section className="rounded-lg border border-amber-400/10 bg-black/20 p-2">
                <header className="mb-1.5 flex items-center gap-1 text-[10px] uppercase tracking-wider text-amber-100/50">
                    <Target className="size-3" />
                    Equipamentos Míticos
                </header>
                <div className="grid grid-cols-1 gap-1">
                    {EQUIPMENT_SLOTS.map(({ key, label }) => {
                        const item = hero.equipment[key];
                        return (
                            <div key={key} className="flex items-center justify-between gap-2 text-[10px]">
                                <span className="text-amber-100/40">{label}</span>
                                {item ? (
                                    <span className="truncate font-mono text-amber-100" title={item.name_pt}>
                                        {item.name_pt}
                                        {item.max_upgrade_level > 0 && (
                                            <span className="ml-1 text-amber-300/70">+{item.level}</span>
                                        )}
                                    </span>
                                ) : (
                                    <span className="text-amber-100/30">—</span>
                                )}
                            </div>
                        );
                    })}
                </div>
                <div className={cn('mt-2 flex items-center justify-between text-[10px]', winners.equipment_contribution === index ? 'font-semibold text-amber-200' : 'text-amber-100/50')}>
                    <span>Contribuição</span>
                    <span className="font-mono">{formatNumber(hero.power.equipment_contribution)}</span>
                </div>
            </section>

            {/* Artefato/Coleção */}
            {(hero.artifact || hero.collection) && (
                <section className="rounded-lg border border-amber-400/10 bg-black/20 p-2 text-[10px]">
                    {hero.artifact && (
                        <div className="flex items-center justify-between">
                            <span className="flex items-center gap-1 text-amber-100/50">
                                <Sparkles className="size-3" />
                                Artefato
                            </span>
                            <span className="font-mono text-amber-100">
                                {hero.artifact.name}
                                {hero.artifact.stars > 0 && ` (${hero.artifact.stars}★)`}
                            </span>
                        </div>
                    )}
                    {hero.collection && (
                        <div className="mt-1 flex items-center justify-between">
                            <span className="flex items-center gap-1 text-amber-100/50">
                                <Crown className="size-3" />
                                Coleção
                            </span>
                            <span className="truncate font-mono text-amber-100">{hero.collection.name}</span>
                        </div>
                    )}
                </section>
            )}

            {/* Stats base do hero */}
            <div className={cn('flex items-center justify-between rounded-lg border bg-black/20 px-2 py-1.5 text-[10px]', winners.stat_contribution === index ? 'border-amber-400/30 text-amber-200' : 'border-amber-400/10 text-amber-100/60')}>
                <span className="flex items-center gap-1">
                    <Flame className="size-3" />
                    Stats base (catálogo)
                </span>
                <span className="font-mono tabular-nums">{formatNumber(hero.power.stat_contribution)}</span>
            </div>

            {/* Próximos upgrades */}
            <section className="rounded-lg border border-amber-400/10 bg-black/20 p-2">
                <header className="mb-1.5 flex items-center gap-1 text-[10px] uppercase tracking-wider text-amber-100/50">
                    <Zap className="size-3" />
                    Próximos upgrades
                </header>
                {hero.upgrades.length === 0 ? (
                    <p className="text-[10px] text-amber-100/30">No max — sem sugestões.</p>
                ) : (
                    <ul className="flex flex-col gap-1">
                        {hero.upgrades.map((u, i) => (
                            <li key={i} className="flex flex-col gap-1 rounded border border-amber-400/5 bg-black/20 p-1.5">
                                <div className="flex flex-wrap items-center gap-1">
                                    <span className={cn('inline-flex items-center rounded border px-1 py-0.5 text-[9px] font-semibold', KIND_BADGE[u.kind])}>
                                        {KIND_LABEL[u.kind]}
                                    </span>
                                    <span className="font-mono text-[10px] text-amber-100">{formatStep(u.kind, u.from, u.to)}</span>
                                </div>
                                <div className="flex items-center justify-between text-[10px]">
                                    <span className="text-emerald-300">+{formatNumber(u.power_gain)} power</span>
                                    {u.affordable === true && (
                                        <span className="rounded bg-emerald-500/15 px-1 py-0.5 text-[9px] text-emerald-300">Cabe</span>
                                    )}
                                    {u.affordable === false && (
                                        <span className="rounded bg-rose-500/15 px-1 py-0.5 text-[9px] text-rose-300">Falta</span>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </article>
    );
}

interface MetricProps {
    label: string;
    value: number;
    formatter?: (v: number) => string;
    suffix?: React.ReactNode;
    highlight?: boolean;
    maxLevel?: number;
}

function Metric({ label, value, formatter, suffix, highlight, maxLevel }: MetricProps) {
    return (
        <div className={cn('flex items-center justify-between rounded border px-2 py-1', highlight ? 'border-amber-400/40 bg-amber-500/10' : 'border-amber-400/5 bg-black/15')}>
            <dt className="flex items-center gap-1 text-[10px] uppercase tracking-wider text-amber-100/50">
                {label}
                {highlight && <Trophy className="size-2.5 text-amber-300" />}
            </dt>
            <dd className={cn('flex items-center gap-1 font-mono text-xs tabular-nums', highlight ? 'font-bold text-amber-200' : 'text-amber-100')}>
                {formatter ? formatter(value) : value}
                {maxLevel ? <span className="text-[9px] text-amber-100/30">/ {maxLevel}</span> : null}
                {suffix}
            </dd>
        </div>
    );
}

function StarsBadge({ filled, total }: { filled: number; total: number }) {
    return (
        <span className="ml-1 inline-flex items-center gap-0.5" title={`${filled}/${total} estrelas`}>
            {Array.from({ length: total }).map((_, i) => (
                <Star key={i} className={cn('size-2.5', i < filled ? 'fill-amber-400 text-amber-400' : 'text-amber-100/20')} />
            ))}
        </span>
    );
}
