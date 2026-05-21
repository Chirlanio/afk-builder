import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, BarChart3, ChevronRight, Crown, Flame, Gem, Scale, Sparkles, Target, TrendingUp, Users } from 'lucide-react';
import { Bar, BarChart, CartesianGrid, Cell, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';
import AppLayout from '@/layouts/app-layout';
import { CLASS_LABEL, FACTION_THEME, TIER_THEME } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { ClassCode, FactionCode, TierCode } from '@/types/afk';
import { EngravingBadge } from '@/components/engraving-badge';

interface Summary {
    total_power: number;
    avg_power: number;
    max_power: number;
    avg_level: number;
    avg_signature_item: number;
    avg_furniture: number;
    avg_engraving: number;
    avg_ascension: number;
    invested_count: number;
}

interface FactionRow {
    code: FactionCode;
    name_pt: string;
    count: number;
    total_power: number;
    avg_power: number;
    invested_count: number;
}

interface ClassRow {
    code: ClassCode;
    name_pt: string;
    count: number;
    total_power: number;
    avg_power: number;
    invested_count: number;
}

interface TierRow {
    code: TierCode;
    name_pt: string;
    count: number;
    total_power: number;
    avg_power: number;
}

interface TopHero {
    hero_id: number;
    slug: string;
    name: string;
    portrait_url: string | null;
    faction_code: FactionCode;
    class_code: ClassCode;
    tier_code: TierCode;
    level: number;
    signature_item: number;
    furniture: number;
    engraving: number;
    power: number;
}

interface Gap {
    type: string;
    severity: 'high' | 'medium' | 'low';
    label: string;
    hint: string;
}

interface PentagramHero {
    hero_id: number;
    slug: string;
    name: string;
    portrait_url: string | null;
    faction_code: FactionCode;
    level: number;
    tier_code: TierCode;
}

interface Pentagram {
    heroes: PentagramHero[];
    shared_level: number | null;
    is_full: boolean;
    is_at_240: boolean;
    level_cap: number;
}

interface PageProps {
    rosterSize: number;
    summary: Summary;
    byFaction: FactionRow[];
    byClass: ClassRow[];
    byTier: TierRow[];
    top10: TopHero[];
    gaps: Gap[];
    pentagram: Pentagram;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Roster', href: '/heroes' },
    { title: 'Análise', href: '/roster/analytics' },
];

const FACTION_BAR_COLOR: Record<FactionCode, string> = {
    lightbearer: '#f59e0b',
    mauler: '#f97316',
    wilder: '#10b981',
    graveborn: '#a855f7',
    celestial: '#38bdf8',
    hypogean: '#dc2626',
    dimensional: '#3b82f6',
    draconis: '#f43f5e',
};

function formatNumber(n: number): string {
    return n.toLocaleString('pt-BR');
}

function severityColor(sev: Gap['severity']): string {
    switch (sev) {
        case 'high':
            return 'border-rose-500/40 bg-rose-500/10 text-rose-300';
        case 'medium':
            return 'border-amber-500/40 bg-amber-500/10 text-amber-300';
        default:
            return 'border-zinc-500/40 bg-zinc-500/10 text-zinc-300';
    }
}

export default function RosterAnalytics({ rosterSize, summary, byFaction, byClass, byTier, top10, gaps, pentagram }: PageProps) {
    if (rosterSize === 0) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Análise do roster" />
                <div className="flex flex-col items-center justify-center gap-3 rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-12 text-center">
                    <Users className="size-12 text-amber-100/30" />
                    <h2 className="text-xl font-semibold text-amber-100">Roster vazio</h2>
                    <p className="max-w-md text-sm text-amber-100/60">
                        Adicione heróis ao seu roster pra ver a análise — distribuição de power, médias de investimento, top heroes e lacunas.
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
            <Head title="Análise do roster" />

            <div className="flex flex-col gap-5 p-1">
                {/* KPIs */}
                <section className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    <KpiCard icon={Users} label="Heróis no roster" value={formatNumber(rosterSize)} hint={`${summary.invested_count} investido(s)`} />
                    <KpiCard icon={TrendingUp} label="Power total" value={formatNumber(summary.total_power)} hint={`Média: ${formatNumber(summary.avg_power)}`} />
                    <KpiCard icon={Crown} label="Maior power" value={formatNumber(summary.max_power)} hint={top10[0]?.name ?? '—'} />
                    <KpiCard
                        icon={Sparkles}
                        label="Investimento médio"
                        value={`AS ${summary.avg_signature_item.toFixed(1)} · F${summary.avg_furniture.toFixed(1)} · E${summary.avg_engraving.toFixed(1)}`}
                        hint={`Nível médio ${summary.avg_level.toFixed(0)}`}
                        valueClass="text-xs sm:text-sm"
                    />
                </section>

                {/* Pentagrama do Cristal Ressonante */}
                <section className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5">
                    <header className="mb-3 flex flex-wrap items-center gap-2">
                        <Gem className="size-4 text-amber-300" />
                        <h2 className="text-sm font-semibold text-amber-100">Pentagrama (Cristal Ressonante)</h2>
                        {pentagram.shared_level !== null && (
                            <span className="rounded-full bg-amber-400/15 px-2 py-0.5 text-[10px] font-mono text-amber-200">
                                Nível compartilhado: {pentagram.shared_level}
                            </span>
                        )}
                        {pentagram.is_at_240 && (
                            <span className="rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] text-emerald-300">
                                Pronto pra upgrade
                            </span>
                        )}
                        <Link href="/crystal" className="ml-auto text-[10px] text-amber-200/70 hover:text-amber-200 underline">
                            Detalhes →
                        </Link>
                    </header>

                    {pentagram.heroes.length === 0 ? (
                        <p className="text-xs text-amber-100/50">Sem heróis no roster pra formar o pentagrama.</p>
                    ) : (
                        <>
                            <div className="grid gap-2 sm:grid-cols-3 lg:grid-cols-5">
                                {pentagram.heroes.map((h, idx) => (
                                    <div
                                        key={h.hero_id}
                                        className={cn(
                                            'flex items-center gap-2 rounded-lg border border-amber-400/10 bg-black/30 p-2',
                                            idx === 4 && 'border-amber-400/40',
                                        )}
                                    >
                                        <span className="grid size-5 flex-shrink-0 place-items-center rounded-full bg-amber-400/15 font-mono text-[9px] font-bold text-amber-200">
                                            {idx + 1}
                                        </span>
                                        {h.portrait_url ? (
                                            <img
                                                src={h.portrait_url}
                                                alt={h.name}
                                                loading="lazy"
                                                className={cn(
                                                    'size-8 rounded-md object-cover object-top',
                                                    FACTION_THEME[h.faction_code]?.cardGradient,
                                                )}
                                            />
                                        ) : (
                                            <div className="size-8 rounded-md bg-zinc-800" />
                                        )}
                                        <div className="flex min-w-0 flex-1 flex-col">
                                            <span className="truncate text-[11px] font-medium text-amber-100">{h.name}</span>
                                            <span className="font-mono text-[10px] text-amber-200">Nv {h.level}</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                            <p className="mt-2 text-[10px] text-amber-100/40">
                                {pentagram.is_at_240
                                    ? `Teto do cristal pós-upgrade: ${pentagram.level_cap} (cresce +5 por hero Ascendido não-Dimensional).`
                                    : `Subir o 5º maior (atualmente ${pentagram.shared_level}) eleva o cristal inteiro. Quando os 5 chegarem ao 240, você destrava o upgrade.`}
                            </p>
                        </>
                    )}
                </section>

                {/* Gaps em destaque */}
                {gaps.length > 0 && (
                    <section className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5">
                        <header className="mb-3 flex items-center gap-2">
                            <AlertTriangle className="size-4 text-amber-300" />
                            <h2 className="text-sm font-semibold text-amber-100">Lacunas detectadas</h2>
                            <span className="ml-auto text-[10px] text-amber-100/40">{gaps.length}</span>
                        </header>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {gaps.map((gap, idx) => (
                                <div
                                    key={`${gap.type}-${idx}`}
                                    className={cn('rounded-lg border px-3 py-2 text-xs', severityColor(gap.severity))}
                                >
                                    <p className="font-medium">{gap.label}</p>
                                    <p className="mt-0.5 text-[11px] opacity-70">{gap.hint}</p>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                {/* Distribuição por facção */}
                <section className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5">
                    <header className="mb-3 flex items-center gap-2">
                        <BarChart3 className="size-4 text-amber-300" />
                        <h2 className="text-sm font-semibold text-amber-100">Power por facção</h2>
                    </header>
                    <div className="h-72 w-full">
                        <ResponsiveContainer width="100%" height="100%">
                            <BarChart data={byFaction} margin={{ top: 4, right: 10, left: 0, bottom: 4 }}>
                                <CartesianGrid strokeDasharray="3 3" stroke="#27272a" />
                                <XAxis dataKey="name_pt" stroke="#71717a" tick={{ fontSize: 10 }} interval={0} angle={-20} textAnchor="end" height={60} />
                                <YAxis stroke="#71717a" tick={{ fontSize: 10 }} tickFormatter={(v: number) => formatCompact(v)} width={50} />
                                <Tooltip
                                    contentStyle={{ background: '#09090b', border: '1px solid #3f3f46', borderRadius: 8, fontSize: 12 }}
                                    formatter={(value, name) => {
                                        const num = typeof value === 'number' ? value : Number(value);
                                        if (name === 'total_power') return [formatNumber(num), 'Power total'];
                                        return [num, name];
                                    }}
                                />
                                <Bar dataKey="total_power" radius={[4, 4, 0, 0]}>
                                    {byFaction.map((row) => (
                                        <Cell key={row.code} fill={FACTION_BAR_COLOR[row.code] ?? '#71717a'} />
                                    ))}
                                </Bar>
                            </BarChart>
                        </ResponsiveContainer>
                    </div>
                    <div className="mt-3 grid grid-cols-2 gap-2 text-[11px] sm:grid-cols-4 lg:grid-cols-8">
                        {byFaction.map((row) => (
                            <div key={row.code} className={cn('rounded-md px-2 py-1.5', FACTION_THEME[row.code]?.cardGradient)}>
                                <div className="font-medium">{row.name_pt}</div>
                                <div className="opacity-70">
                                    {row.count} hero{row.count === 1 ? '' : 's'} · {row.invested_count} inv.
                                </div>
                            </div>
                        ))}
                    </div>
                </section>

                {/* Distribuição por classe + tier (lado-a-lado em desktop) */}
                <section className="grid gap-3 lg:grid-cols-2">
                    <div className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5">
                        <header className="mb-3 flex items-center gap-2">
                            <Target className="size-4 text-amber-300" />
                            <h2 className="text-sm font-semibold text-amber-100">Por classe</h2>
                        </header>
                        <ul className="flex flex-col gap-2 text-xs">
                            {byClass.map((row) => {
                                const pct = summary.total_power > 0 ? (row.total_power / summary.total_power) * 100 : 0;
                                return (
                                    <li key={row.code} className="flex flex-col gap-1">
                                        <div className="flex items-center justify-between text-amber-100">
                                            <span className="font-medium">{row.name_pt}</span>
                                            <span className="font-mono text-[10px] opacity-70">
                                                {row.count} · {formatNumber(row.total_power)}
                                            </span>
                                        </div>
                                        <div className="h-1.5 w-full overflow-hidden rounded-full bg-zinc-800">
                                            <div className="h-full bg-amber-400/60" style={{ width: `${Math.min(100, pct)}%` }} />
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>

                    <div className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5">
                        <header className="mb-3 flex items-center gap-2">
                            <Flame className="size-4 text-amber-300" />
                            <h2 className="text-sm font-semibold text-amber-100">Por tier do catálogo</h2>
                        </header>
                        <ul className="flex flex-col gap-2 text-xs">
                            {byTier.map((row) => {
                                const pct = summary.total_power > 0 ? (row.total_power / summary.total_power) * 100 : 0;
                                const themeBadge = TIER_THEME[row.code]?.badge ?? '';
                                return (
                                    <li key={row.code} className="flex flex-col gap-1">
                                        <div className="flex items-center justify-between">
                                            <span className={cn('rounded px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide', themeBadge)}>
                                                {row.name_pt}
                                            </span>
                                            <span className="font-mono text-[10px] text-amber-100/70">
                                                {row.count} · {formatNumber(row.total_power)}
                                            </span>
                                        </div>
                                        <div className="h-1.5 w-full overflow-hidden rounded-full bg-zinc-800">
                                            <div className="h-full bg-amber-400/60" style={{ width: `${Math.min(100, pct)}%` }} />
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>
                    </div>
                </section>

                {/* Top 10 heroes */}
                <section className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5">
                    <header className="mb-3 flex items-center gap-2">
                        <Crown className="size-4 text-amber-300" />
                        <h2 className="text-sm font-semibold text-amber-100">Top 10 por power</h2>
                        {top10.length >= 2 && (
                            <Link
                                href={`/roster/compare?${top10.slice(0, 4).map((h) => `heroes[]=${h.slug}`).join('&')}`}
                                className="ml-auto inline-flex items-center gap-1 rounded-md border border-amber-400/30 px-2 py-1 text-[10px] text-amber-200 hover:bg-amber-500/15"
                            >
                                <Scale className="size-3" />
                                Comparar top {Math.min(top10.length, 4)}
                            </Link>
                        )}
                    </header>
                    <ol className="flex flex-col gap-1.5">
                        {top10.map((hero, idx) => (
                            <li key={hero.hero_id} className="flex items-center gap-3 rounded-lg border border-amber-400/10 bg-black/30 p-2">
                                <span className="grid size-6 flex-shrink-0 place-items-center rounded-full bg-amber-400/15 font-mono text-[10px] font-bold text-amber-200">
                                    {idx + 1}
                                </span>
                                <Link
                                    href={`/heroes/${hero.slug}`}
                                    className="flex flex-1 items-center gap-2 hover:bg-amber-500/5"
                                >
                                    {hero.portrait_url ? (
                                        <img
                                            src={hero.portrait_url}
                                            alt={hero.name}
                                            loading="lazy"
                                            className={cn(
                                                'size-9 rounded-md object-cover object-top',
                                                FACTION_THEME[hero.faction_code]?.cardGradient,
                                            )}
                                        />
                                    ) : (
                                        <div className="size-9 rounded-md bg-zinc-800" />
                                    )}
                                    <div className="flex min-w-0 flex-1 flex-col text-xs">
                                        <span className="truncate font-medium text-amber-100">{hero.name}</span>
                                        <span className="truncate text-[10px] text-amber-100/50">
                                            Nv {hero.level} · {CLASS_LABEL[hero.class_code]}
                                            {hero.signature_item > 0 && ` · AS ${hero.signature_item}`}
                                            {hero.furniture > 0 && ` · F${hero.furniture}`}
                                        </span>
                                    </div>
                                </Link>
                                <EngravingBadge engraving={hero.engraving} size="xs" />
                                <span className="ml-auto rounded bg-amber-500/15 px-2 py-1 font-mono text-xs tabular-nums text-amber-200">
                                    {formatNumber(hero.power)}
                                </span>
                            </li>
                        ))}
                    </ol>
                </section>
            </div>
        </AppLayout>
    );
}

interface KpiCardProps {
    icon: React.ComponentType<{ className?: string }>;
    label: string;
    value: string;
    hint?: string;
    valueClass?: string;
}

function KpiCard({ icon: Icon, label, value, hint, valueClass }: KpiCardProps) {
    return (
        <div className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-4">
            <div className="mb-2 flex items-center gap-1.5 text-[10px] uppercase tracking-wider text-amber-100/50">
                <Icon className="size-3.5" />
                {label}
            </div>
            <div className={cn('font-bold text-amber-100', valueClass ?? 'text-xl')}>{value}</div>
            {hint && <div className="mt-0.5 text-[10px] text-amber-100/40">{hint}</div>}
        </div>
    );
}

function formatCompact(n: number): string {
    if (n >= 1_000_000) return `${(n / 1_000_000).toFixed(1)}M`;
    if (n >= 1_000) return `${(n / 1_000).toFixed(0)}k`;
    return n.toString();
}
