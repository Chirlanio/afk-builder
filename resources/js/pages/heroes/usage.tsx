import AppLayout from '@/layouts/app-layout';
import { CLASS_ICON, FACTION_THEME, TIER_THEME } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { ClassCode, FactionCode, TierCode } from '@/types/afk';
import { Head, Link } from '@inertiajs/react';
import { AlertTriangle, BarChart3, GhostIcon, Trophy } from 'lucide-react';

interface UsageStats {
    total_used: number;
    total_in_roster: number;
    never_used_total: number;
    invested_forgotten_total: number;
}

interface HeroPayload {
    id: number;
    slug: string;
    name: string;
    portrait_url: string | null;
    faction_code: FactionCode | null;
    class_code: ClassCode | null;
    tier_code: TierCode | null;
}

interface TopUsedHero extends HeroPayload {
    team_count: number;
    formation_count: number;
    challenge_types_count: number;
}

interface RosterHeroPayload extends HeroPayload {
    level: number;
    signature_item: number;
    furniture: number;
    engraving: number;
}

interface ChallengeUsage {
    code: string;
    name_pt: string;
    heroes: Array<HeroPayload & { team_count: number }>;
}

interface PageProps {
    stats: UsageStats;
    topUsed: TopUsedHero[];
    neverUsed: RosterHeroPayload[];
    neverUsedShownLimit: number;
    investedForgotten: RosterHeroPayload[];
    usageByChallengeType: ChallengeUsage[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Heróis', href: '/heroes' },
    { title: 'Uso de heróis', href: '/heroes/usage' },
];

function HeroAvatar({ hero, size = 'md' }: { hero: HeroPayload; size?: 'sm' | 'md' }) {
    const theme = hero.faction_code ? FACTION_THEME[hero.faction_code] : null;
    const dim = size === 'sm' ? 'size-8' : 'size-10';
    return (
        <img
            src={hero.portrait_url ?? ''}
            alt={hero.name}
            loading="lazy"
            className={cn(dim, 'rounded-full border-2 object-cover', theme?.cardBorder ?? 'border-amber-400/30')}
            onError={(e) => {
                (e.currentTarget as HTMLImageElement).style.opacity = '0';
            }}
        />
    );
}

function HeroLink({ hero, children }: { hero: HeroPayload; children: React.ReactNode }) {
    return (
        <Link
            href={`${route('heroes.index')}?open=${hero.slug}`}
            className="block transition-all hover:scale-[1.02]"
        >
            {children}
        </Link>
    );
}

function InvestmentBadges({ hero }: { hero: RosterHeroPayload }) {
    const badges: string[] = [];
    if (hero.signature_item > 0) badges.push(`SI +${hero.signature_item}`);
    if (hero.furniture > 0) badges.push(`F${hero.furniture}`);
    if (hero.engraving > 0) badges.push(`E${hero.engraving}`);
    if (badges.length === 0) badges.push(`Nv ${hero.level}`);
    return <span className="text-[10px] text-amber-100/50">{badges.join(' · ')}</span>;
}

function EmptyState({ title, description, icon: Icon }: { title: string; description: string; icon: React.ComponentType<{ className?: string }> }) {
    return (
        <div className="flex flex-col items-center justify-center gap-2 rounded-lg border border-dashed border-amber-400/20 bg-black/20 py-8 text-center">
            <Icon className="size-8 text-amber-100/30" />
            <p className="text-sm font-medium text-amber-100/70">{title}</p>
            <p className="text-xs text-amber-100/40">{description}</p>
        </div>
    );
}

export default function HeroUsageIndex({
    stats,
    topUsed,
    neverUsed,
    neverUsedShownLimit,
    investedForgotten,
    usageByChallengeType,
}: PageProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Uso de heróis" />

            <div className="flex flex-col gap-5 px-4 py-6 md:px-6 lg:px-8">
                <header className="flex flex-col gap-1">
                    <div className="flex items-center gap-2">
                        <BarChart3 className="size-5 text-amber-300" />
                        <h1 className="text-2xl font-semibold text-amber-100">Uso de heróis</h1>
                    </div>
                    <p className="text-sm text-amber-100/60">
                        Quais heróis você mais (e menos) usa nas suas formações. Identifique investimentos esquecidos no roster.
                    </p>
                </header>

                {/* Stats top */}
                <section className="grid grid-cols-2 gap-3 md:grid-cols-4">
                    <StatCard label="Heróis utilizados" value={stats.total_used} hint="distintos em formações" />
                    <StatCard label="Roster total" value={stats.total_in_roster} hint="heróis cadastrados" />
                    <StatCard label="Nunca usados" value={stats.never_used_total} hint="no roster, fora de formações" tone={stats.never_used_total > 0 ? 'warning' : undefined} />
                    <StatCard label="Investidos + esquecidos" value={stats.invested_forgotten_total} hint="SI≥20 ou F≥3 ou Eng≥60" tone={stats.invested_forgotten_total > 0 ? 'danger' : undefined} />
                </section>

                {/* Top 10 usados */}
                <section className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5 md:p-6">
                    <header className="mb-4 flex items-center gap-2">
                        <Trophy className="size-4 text-amber-300" />
                        <h2 className="text-lg font-semibold text-amber-100">Top 10 mais utilizados</h2>
                    </header>
                    {topUsed.length === 0 ? (
                        <EmptyState
                            icon={GhostIcon}
                            title="Sem dados de uso ainda"
                            description="Crie formações para ver quais heróis você mais utiliza."
                        />
                    ) : (
                        <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-2 xl:grid-cols-2">
                            {topUsed.map((hero, idx) => {
                                const theme = hero.faction_code ? FACTION_THEME[hero.faction_code] : null;
                                const ClassIcon = hero.class_code ? CLASS_ICON[hero.class_code] : null;
                                return (
                                    <HeroLink key={hero.id} hero={hero}>
                                        <div
                                            className={cn(
                                                'flex items-center gap-3 rounded-lg border bg-black/30 p-3',
                                                theme?.cardBorder ?? 'border-amber-400/15',
                                            )}
                                        >
                                            <span className="grid size-7 shrink-0 place-items-center rounded-full bg-amber-500/20 text-xs font-bold text-amber-300">
                                                #{idx + 1}
                                            </span>
                                            <HeroAvatar hero={hero} />
                                            <div className="min-w-0 flex-1">
                                                <div className="flex items-center gap-1.5">
                                                    <span className="truncate text-sm font-semibold text-amber-100">{hero.name}</span>
                                                    {ClassIcon && <ClassIcon className="size-3 shrink-0 text-amber-100/50" />}
                                                </div>
                                                <div className="text-[11px] text-amber-100/50">
                                                    {hero.team_count} {hero.team_count === 1 ? 'time' : 'times'} em {hero.formation_count} {hero.formation_count === 1 ? 'formação' : 'formações'}
                                                    {hero.challenge_types_count > 1 && (
                                                        <> · {hero.challenge_types_count} desafios</>
                                                    )}
                                                </div>
                                            </div>
                                            <div className="font-mono text-lg font-bold tabular-nums text-amber-300">{hero.team_count}</div>
                                        </div>
                                    </HeroLink>
                                );
                            })}
                        </div>
                    )}
                </section>

                {/* Investidos + esquecidos (red flag) */}
                {investedForgotten.length > 0 && (
                    <section className="rounded-2xl border border-rose-400/30 bg-rose-950/20 p-5 md:p-6">
                        <header className="mb-4 flex items-start gap-2">
                            <AlertTriangle className="mt-0.5 size-4 shrink-0 text-rose-300" />
                            <div>
                                <h2 className="text-lg font-semibold text-rose-100">Investidos mas esquecidos</h2>
                                <p className="text-xs text-rose-100/60">
                                    Heróis com SI ≥ 20, móveis ≥ 3 ou inscrições ≥ 60 que nunca entraram em uma formação. Vale revisar se ainda fazem sentido no seu projeto.
                                </p>
                            </div>
                        </header>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {investedForgotten.map((hero) => {
                                const theme = hero.faction_code ? FACTION_THEME[hero.faction_code] : null;
                                return (
                                    <HeroLink key={hero.id} hero={hero}>
                                        <div
                                            className={cn(
                                                'flex items-center gap-3 rounded-lg border bg-black/30 p-3',
                                                theme?.cardBorder ?? 'border-rose-400/30',
                                            )}
                                        >
                                            <HeroAvatar hero={hero} />
                                            <div className="min-w-0 flex-1">
                                                <div className="truncate text-sm font-semibold text-rose-100">{hero.name}</div>
                                                <InvestmentBadges hero={hero} />
                                            </div>
                                        </div>
                                    </HeroLink>
                                );
                            })}
                        </div>
                    </section>
                )}

                {/* Por challenge type */}
                {usageByChallengeType.length > 0 && (
                    <section className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5 md:p-6">
                        <header className="mb-4">
                            <h2 className="text-lg font-semibold text-amber-100">Top 5 por tipo de desafio</h2>
                            <p className="text-xs text-amber-100/50">Os heróis mais usados em cada modo onde você tem formações.</p>
                        </header>
                        <div className="grid gap-4 md:grid-cols-2">
                            {usageByChallengeType.map((bucket) => (
                                <div key={bucket.code} className="rounded-lg border border-amber-400/10 bg-black/30 p-3">
                                    <h3 className="mb-2 text-sm font-semibold text-amber-200">{bucket.name_pt}</h3>
                                    <div className="flex flex-col gap-1.5">
                                        {bucket.heroes.map((hero, idx) => (
                                            <HeroLink key={hero.id} hero={hero}>
                                                <div className="flex items-center gap-2.5 rounded-md bg-black/20 p-2">
                                                    <span className="grid size-5 shrink-0 place-items-center rounded-full bg-amber-500/15 text-[10px] font-bold text-amber-300">
                                                        {idx + 1}
                                                    </span>
                                                    <HeroAvatar hero={hero} size="sm" />
                                                    <span className="min-w-0 flex-1 truncate text-xs text-amber-100">{hero.name}</span>
                                                    <span className="font-mono text-xs tabular-nums text-amber-300">{hero.team_count}×</span>
                                                </div>
                                            </HeroLink>
                                        ))}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>
                )}

                {/* Never used roster */}
                <section className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5 md:p-6">
                    <header className="mb-4 flex items-start justify-between gap-2">
                        <div>
                            <h2 className="text-lg font-semibold text-amber-100">Nunca usados no roster</h2>
                            <p className="text-xs text-amber-100/50">
                                Heróis que você cadastrou mas nunca colocou em uma formação.{' '}
                                {stats.never_used_total > neverUsedShownLimit && (
                                    <span>Mostrando os {neverUsedShownLimit} primeiros de {stats.never_used_total}.</span>
                                )}
                            </p>
                        </div>
                    </header>
                    {neverUsed.length === 0 ? (
                        <EmptyState
                            icon={Trophy}
                            title={stats.total_in_roster === 0 ? 'Roster vazio' : 'Todo seu roster está em uso'}
                            description={stats.total_in_roster === 0 ? 'Cadastre heróis no /heroes para começar.' : 'Você usa todos os heróis que cadastrou — bom planejamento!'}
                        />
                    ) : (
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                            {neverUsed.map((hero) => {
                                const theme = hero.faction_code ? FACTION_THEME[hero.faction_code] : null;
                                const tierTheme = hero.tier_code ? TIER_THEME[hero.tier_code] : null;
                                return (
                                    <HeroLink key={hero.id} hero={hero}>
                                        <div
                                            className={cn(
                                                'flex flex-col items-center gap-1.5 rounded-lg border bg-black/30 p-2',
                                                theme?.cardBorder ?? 'border-amber-400/15',
                                            )}
                                        >
                                            <HeroAvatar hero={hero} />
                                            <div className="w-full truncate text-center text-[11px] text-amber-100">{hero.name}</div>
                                            {tierTheme && (
                                                <span className={cn('rounded px-1.5 py-0.5 text-[9px] font-bold uppercase', tierTheme.badge)}>
                                                    {tierTheme.label.slice(0, 3)}
                                                </span>
                                            )}
                                        </div>
                                    </HeroLink>
                                );
                            })}
                        </div>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}

interface StatCardProps {
    label: string;
    value: number;
    hint?: string;
    tone?: 'warning' | 'danger';
}

function StatCard({ label, value, hint, tone }: StatCardProps) {
    const toneClasses = tone === 'danger'
        ? 'border-rose-400/30 bg-rose-950/30 text-rose-100'
        : tone === 'warning'
            ? 'border-amber-500/40 bg-amber-950/30 text-amber-100'
            : 'border-amber-400/15 bg-zinc-950/60 text-amber-100';

    return (
        <div className={cn('rounded-xl border p-4', toneClasses)}>
            <div className="text-xs uppercase tracking-wide opacity-60">{label}</div>
            <div className="mt-1 text-2xl font-bold tabular-nums">{value}</div>
            {hint && <div className="mt-0.5 text-[10px] opacity-50">{hint}</div>}
        </div>
    );
}
