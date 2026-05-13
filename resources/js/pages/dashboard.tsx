import { Head, Link, usePage } from '@inertiajs/react';
import { BookOpen, ChevronRight, Crown, Flame, Gem, LayoutGrid, PenLine, Shield, Sparkles, Star, Sword, Target, Trophy, Users } from 'lucide-react';

import AppLayout from '@/layouts/app-layout';
import { CLASS_ICON, CLASS_LABEL, FACTION_THEME, TIER_THEME } from '@/lib/afk';
import { cn } from '@/lib/utils';
import { type BreadcrumbItem, type SharedData } from '@/types';
import type { ClassCode, FactionCode, TierCode } from '@/types/afk';

interface DashboardStats {
    roster_total: number;
    roster_ascended: number;
    roster_awakened: number;
    roster_si_30: number;
    roster_si_40: number;
    roster_f9: number;
    roster_eng_80: number;
    catalog_total: number;
    factions_count: number;
    formations_total: number;
}

interface DistributionRow<T extends string> {
    code: T;
    name_pt: string;
    total: number;
}

interface TopPowerHero {
    hero_slug: string | null;
    hero_name: string | null;
    portrait_url: string | null;
    faction_code: FactionCode | null;
    class_code: ClassCode | null;
    tier_code: TierCode | null;
    power: number;
    signature_item: number;
    furniture: number;
    engraving: number;
    level: number;
}

interface FeaturedHero {
    slug: string | null;
    name: string | null;
    portrait_url: string | null;
}

interface RecentFormationRow {
    id: number;
    name: string;
    mode: 'real' | 'target';
    teams_count: number;
    challenge_label: string | null;
    updated_at: string | null;
}

interface DashboardProps {
    stats: DashboardStats;
    rosterByFaction: DistributionRow<FactionCode>[];
    rosterByClass: DistributionRow<ClassCode>[];
    rosterByTier: DistributionRow<TierCode>[];
    topPowerHeroes: TopPowerHero[];
    featuredInRoster: {
        pvp_top: FeaturedHero[];
        twisted_realm_top: FeaturedHero[];
    };
    recentFormations: RecentFormationRow[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

export default function Dashboard({
    stats,
    rosterByFaction,
    rosterByClass,
    rosterByTier,
    topPowerHeroes,
    featuredInRoster,
    recentFormations,
}: DashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const userName = auth.user?.name?.split(' ')[0] ?? 'Herói';

    const rosterPct = stats.catalog_total > 0 ? Math.round((stats.roster_total / stats.catalog_total) * 100) : 0;

    const factionMap = new Map(rosterByFaction.map((f) => [f.code, f.total]));
    const classMap = new Map(rosterByClass.map((c) => [c.code, c.total]));
    const tierMap = new Map(rosterByTier.map((t) => [t.code, t.total]));

    const allFactions: FactionCode[] = ['lightbearer', 'mauler', 'wilder', 'graveborn', 'celestial', 'hypogean', 'dimensional', 'draconis'];
    const allClasses: ClassCode[] = ['tank', 'warrior', 'ranger', 'mage', 'support'];
    const allTiers: TierCode[] = ['common', 'legendary', 'ascended'];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />

            <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
                <section className="relative overflow-hidden rounded-2xl border border-amber-400/20 bg-gradient-to-br from-zinc-900 via-zinc-950 to-black p-6 shadow-[0_0_50px_-15px_rgba(251,191,36,0.3)] md:p-8">
                    <div
                        className="pointer-events-none absolute inset-0 opacity-30"
                        style={{
                            backgroundImage:
                                'radial-gradient(circle at 100% 0%, rgba(251,191,36,0.25), transparent 50%), radial-gradient(circle at 0% 100%, rgba(168,85,247,0.20), transparent 50%)',
                        }}
                    />
                    <div className="relative flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                        <div>
                            <span className="inline-flex items-center gap-2 rounded-full border border-amber-400/40 bg-amber-400/10 px-3 py-1 text-xs font-medium tracking-widest text-amber-200 uppercase">
                                <Sparkles className="size-3" />
                                Bem-vindo de volta
                            </span>
                            <h1 className="mt-3 text-2xl font-bold text-amber-50 md:text-3xl">Olá, {userName}!</h1>
                            <p className="mt-1 max-w-xl text-sm text-amber-100/60">Acompanhe seu progresso, monte formações e conquiste cada modo de Esperia.</p>
                        </div>
                        <div className="flex flex-wrap gap-2">
                            <Link
                                href={route('formations.create')}
                                className="inline-flex items-center gap-2 rounded-md bg-gradient-to-r from-amber-500 to-yellow-400 px-4 py-2 text-sm font-semibold text-zinc-900 shadow-[0_0_18px_-4px_rgba(251,191,36,0.6)] transition-all hover:from-amber-400 hover:to-yellow-300"
                            >
                                <Sword className="size-4" />
                                Nova formação
                            </Link>
                            <Link
                                href={route('heroes.index')}
                                className="inline-flex items-center gap-2 rounded-md border border-amber-400/30 bg-white/5 px-4 py-2 text-sm font-semibold text-amber-100 transition-colors hover:bg-white/10"
                            >
                                <Users className="size-4" />
                                Meu roster
                            </Link>
                        </div>
                    </div>
                </section>

                <section className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <StatCard
                        icon={<Users className="size-5" />}
                        label="Roster"
                        value={stats.roster_total}
                        sub={`${rosterPct}% do catálogo`}
                        accent="from-amber-500/20 to-amber-500/0 border-amber-500/30 text-amber-300"
                    />
                    <StatCard
                        icon={<Crown className="size-5" />}
                        label="Ascendidos"
                        value={stats.roster_ascended}
                        sub="Asc 1★ ou superior"
                        accent="from-yellow-500/20 to-yellow-500/0 border-yellow-500/30 text-yellow-300"
                    />
                    <StatCard
                        icon={<Sparkles className="size-5" />}
                        label="Despertos"
                        value={stats.roster_awakened}
                        sub="Variantes Awakened"
                        accent="from-purple-500/20 to-purple-500/0 border-purple-500/30 text-purple-300"
                    />
                    <StatCard
                        icon={<Sword className="size-5" />}
                        label="Formações"
                        value={stats.formations_total}
                        sub="Salvas na conta"
                        accent="from-rose-500/20 to-rose-500/0 border-rose-500/30 text-rose-300"
                    />
                </section>

                {/* Milestones de investimento */}
                <section className="grid grid-cols-2 gap-4 md:grid-cols-4">
                    <StatCard
                        icon={<Gem className="size-5" />}
                        label="SI +30"
                        value={stats.roster_si_30}
                        sub={`${stats.roster_si_40} no cap +40`}
                        accent="from-emerald-500/20 to-emerald-500/0 border-emerald-500/30 text-emerald-300"
                    />
                    <StatCard
                        icon={<Flame className="size-5" />}
                        label="Móveis F9"
                        value={stats.roster_f9}
                        sub="9/9 unlocked"
                        accent="from-orange-500/20 to-orange-500/0 border-orange-500/30 text-orange-300"
                    />
                    <StatCard
                        icon={<PenLine className="size-5" />}
                        label="Inscrições 80+"
                        value={stats.roster_eng_80}
                        sub="E80 ou maior"
                        accent="from-fuchsia-500/20 to-fuchsia-500/0 border-fuchsia-500/30 text-fuchsia-300"
                    />
                    <StatCard
                        icon={<Trophy className="size-5" />}
                        label="Top power"
                        value={topPowerHeroes[0]?.power ?? 0}
                        sub={topPowerHeroes[0]?.hero_name ?? 'Sem heróis ainda'}
                        accent="from-sky-500/20 to-sky-500/0 border-sky-500/30 text-sky-300"
                    />
                </section>

                <section className="grid gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <div className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5 md:p-6">
                            <header className="mb-5 flex items-center justify-between">
                                <div>
                                    <h2 className="text-lg font-semibold text-amber-100">Roster por facção</h2>
                                    <p className="text-xs text-amber-100/50">Distribuição dos seus heróis pelas 8 facções</p>
                                </div>
                                <Link
                                    href={route('heroes.index')}
                                    className="inline-flex items-center gap-1 text-xs font-medium text-amber-300 hover:text-amber-200"
                                >
                                    Ver tudo <ChevronRight className="size-3" />
                                </Link>
                            </header>

                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                                {allFactions.map((code) => {
                                    const theme = FACTION_THEME[code];
                                    const total = factionMap.get(code) ?? 0;
                                    return (
                                        <div
                                            key={code}
                                            className={`relative overflow-hidden rounded-lg border ${theme.cardBorder} ${theme.cardGradient} p-3 transition-all hover:scale-[1.02]`}
                                        >
                                            <div className={`text-xs font-semibold ${theme.headerText}`}>{theme.label}</div>
                                            <div className="mt-1 text-2xl font-bold text-amber-50">{total}</div>
                                            <div className="text-[10px] tracking-wider text-amber-100/40 uppercase">{total === 1 ? 'herói' : 'heróis'}</div>
                                        </div>
                                    );
                                })}
                            </div>
                        </div>
                    </div>

                    <div className="space-y-4">
                        <Link
                            href={route('heroes.index')}
                            className="group block rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5 transition-all hover:border-amber-400/40 hover:bg-zinc-900/60"
                        >
                            <div className="flex items-center gap-3">
                                <div className="flex size-10 items-center justify-center rounded-md bg-amber-500/15 text-amber-300">
                                    <Users className="size-5" />
                                </div>
                                <div className="flex-1">
                                    <div className="text-sm font-semibold text-amber-100">Catálogo de heróis</div>
                                    <div className="text-xs text-amber-100/50">{stats.catalog_total} disponíveis em {stats.factions_count} facções</div>
                                </div>
                                <ChevronRight className="size-4 text-amber-100/30 transition-transform group-hover:translate-x-1 group-hover:text-amber-300" />
                            </div>
                        </Link>

                        <Link
                            href={route('formations.index')}
                            className="group block rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5 transition-all hover:border-amber-400/40 hover:bg-zinc-900/60"
                        >
                            <div className="flex items-center gap-3">
                                <div className="flex size-10 items-center justify-center rounded-md bg-rose-500/15 text-rose-300">
                                    <LayoutGrid className="size-5" />
                                </div>
                                <div className="flex-1">
                                    <div className="text-sm font-semibold text-amber-100">Minhas formações</div>
                                    <div className="text-xs text-amber-100/50">{stats.formations_total} salvas</div>
                                </div>
                                <ChevronRight className="size-4 text-amber-100/30 transition-transform group-hover:translate-x-1 group-hover:text-amber-300" />
                            </div>
                        </Link>

                        <Link
                            href={route('guides.index')}
                            className="group block rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5 transition-all hover:border-amber-400/40 hover:bg-zinc-900/60"
                        >
                            <div className="flex items-center gap-3">
                                <div className="flex size-10 items-center justify-center rounded-md bg-purple-500/15 text-purple-300">
                                    <BookOpen className="size-5" />
                                </div>
                                <div className="flex-1">
                                    <div className="text-sm font-semibold text-amber-100">Guias de mecânicas</div>
                                    <div className="text-xs text-amber-100/50">Hotel Oak, Inscrições, Atributos…</div>
                                </div>
                                <ChevronRight className="size-4 text-amber-100/30 transition-transform group-hover:translate-x-1 group-hover:text-amber-300" />
                            </div>
                        </Link>
                    </div>
                </section>

                {/* Distribuição por classe e tier */}
                <section className="grid gap-6 md:grid-cols-2">
                    <div className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5">
                        <h2 className="mb-3 text-sm font-semibold text-amber-100">Distribuição por classe</h2>
                        <div className="flex flex-col gap-2">
                            {allClasses.map((code) => {
                                const Icon = CLASS_ICON[code];
                                const total = classMap.get(code) ?? 0;
                                const pct = stats.roster_total > 0 ? (total / stats.roster_total) * 100 : 0;
                                return (
                                    <div key={code} className="flex items-center gap-3">
                                        <div className="flex size-7 shrink-0 items-center justify-center rounded-md bg-amber-500/10 text-amber-300">
                                            <Icon className="size-3.5" />
                                        </div>
                                        <div className="min-w-[80px] text-xs text-amber-100/70">{CLASS_LABEL[code]}</div>
                                        <div className="relative h-2 flex-1 overflow-hidden rounded-full bg-amber-900/30">
                                            <div className="absolute inset-y-0 left-0 bg-gradient-to-r from-amber-500 to-yellow-400 transition-all" style={{ width: `${pct}%` }} />
                                        </div>
                                        <div className="min-w-[28px] text-right font-mono text-xs text-amber-100 tabular-nums">{total}</div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    <div className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5">
                        <h2 className="mb-3 text-sm font-semibold text-amber-100">Distribuição por tier</h2>
                        <div className="flex flex-col gap-2">
                            {allTiers.map((code) => {
                                const theme = TIER_THEME[code];
                                const total = tierMap.get(code) ?? 0;
                                const pct = stats.roster_total > 0 ? (total / stats.roster_total) * 100 : 0;
                                return (
                                    <div key={code} className="flex items-center gap-3">
                                        <div className={cn('shrink-0 rounded-md px-2 py-0.5 text-[10px] font-semibold uppercase', theme.badge)}>{theme.label}</div>
                                        <div className="relative h-2 flex-1 overflow-hidden rounded-full bg-amber-900/30">
                                            <div className="absolute inset-y-0 left-0 bg-gradient-to-r from-amber-500 to-yellow-400 transition-all" style={{ width: `${pct}%` }} />
                                        </div>
                                        <div className="min-w-[28px] text-right font-mono text-xs text-amber-100 tabular-nums">{total}</div>
                                    </div>
                                );
                            })}
                        </div>
                    </div>
                </section>

                {/* Top 5 power */}
                {topPowerHeroes.length > 0 && (
                    <section className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5 md:p-6">
                        <header className="mb-4 flex items-center justify-between">
                            <div>
                                <h2 className="text-lg font-semibold text-amber-100">Top 5 power</h2>
                                <p className="text-xs text-amber-100/50">Seus heróis mais investidos (asc + nível + SI + móveis + equip)</p>
                            </div>
                        </header>
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                            {topPowerHeroes.map((h, idx) => {
                                const theme = h.faction_code ? FACTION_THEME[h.faction_code] : null;
                                return (
                                    <Link
                                        key={h.hero_slug ?? idx}
                                        href={h.hero_slug ? `${route('heroes.index')}?open=${h.hero_slug}` : route('heroes.index')}
                                        className={cn(
                                            'group flex flex-col gap-2 rounded-lg border bg-black/30 p-3 transition-all hover:scale-[1.02]',
                                            theme?.cardBorder ?? 'border-amber-400/15',
                                        )}
                                    >
                                        <div className="flex items-center gap-2">
                                            <span className="grid size-6 shrink-0 place-items-center rounded-full bg-amber-500/20 text-[10px] font-bold text-amber-300">#{idx + 1}</span>
                                            {h.portrait_url && <img src={h.portrait_url} alt={h.hero_name ?? ''} className="size-8 rounded-full border border-amber-400/30 object-cover" />}
                                            <div className="min-w-0 flex-1">
                                                <div className="truncate text-xs font-semibold text-amber-100">{h.hero_name}</div>
                                                <div className="text-[10px] text-amber-100/40">
                                                    Nv {h.level} · SI +{h.signature_item} · F{h.furniture}
                                                </div>
                                            </div>
                                        </div>
                                        <div className="font-mono text-lg font-bold text-amber-50 tabular-nums">{h.power.toLocaleString('pt-BR')}</div>
                                    </Link>
                                );
                            })}
                        </div>
                    </section>
                )}

                {/* Heroes destaque no roster */}
                {(featuredInRoster.pvp_top.length > 0 || featuredInRoster.twisted_realm_top.length > 0) && (
                    <section className="grid gap-6 md:grid-cols-2">
                        <FeaturedHeroesCard
                            title="PvP Top no seu roster"
                            subtitle="Tier S em Arena/Esperia"
                            icon={<Target className="size-5" />}
                            heroes={featuredInRoster.pvp_top}
                            accent="from-rose-500/20 to-rose-500/0 border-rose-500/30 text-rose-300"
                        />
                        <FeaturedHeroesCard
                            title="Twisted Realm Top"
                            subtitle="Especialistas do modo boss"
                            icon={<Shield className="size-5" />}
                            heroes={featuredInRoster.twisted_realm_top}
                            accent="from-violet-500/20 to-violet-500/0 border-violet-500/30 text-violet-300"
                        />
                    </section>
                )}

                <section className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5 md:p-6">
                    <header className="mb-4 flex items-center justify-between">
                        <div>
                            <h2 className="text-lg font-semibold text-amber-100">Formações recentes</h2>
                            <p className="text-xs text-amber-100/50">Últimas 3 que você editou</p>
                        </div>
                        <Link
                            href={route('formations.index')}
                            className="inline-flex items-center gap-1 text-xs font-medium text-amber-300 hover:text-amber-200"
                        >
                            Ver todas <ChevronRight className="size-3" />
                        </Link>
                    </header>

                    {recentFormations.length === 0 ? (
                        <div className="rounded-lg border border-dashed border-amber-400/20 bg-black/20 p-8 text-center">
                            <Sword className="mx-auto size-8 text-amber-400/40" />
                            <p className="mt-3 text-sm text-amber-100/60">Nenhuma formação criada ainda.</p>
                            <Link
                                href={route('formations.create')}
                                className="mt-3 inline-flex items-center gap-1.5 text-sm font-medium text-amber-300 hover:text-amber-200"
                            >
                                Criar a primeira <ChevronRight className="size-3" />
                            </Link>
                        </div>
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {recentFormations.map((f) => (
                                <Link
                                    key={f.id}
                                    href={route('formations.edit', f.id)}
                                    className="group flex flex-col gap-2 rounded-lg border border-amber-400/15 bg-black/30 p-4 transition-all hover:border-amber-400/40 hover:bg-black/50"
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="font-semibold text-amber-100 group-hover:text-amber-50">{f.name}</div>
                                        <span
                                            className={`shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-medium tracking-wider uppercase ${
                                                f.mode === 'real' ? 'border-emerald-400/40 bg-emerald-400/10 text-emerald-300' : 'border-sky-400/40 bg-sky-400/10 text-sky-300'
                                            }`}
                                        >
                                            {f.mode === 'real' ? 'Real' : 'Alvo'}
                                        </span>
                                    </div>
                                    <div className="text-xs text-amber-100/50">
                                        {f.challenge_label ?? '—'} · {f.teams_count} eq.
                                    </div>
                                    {f.updated_at && <div className="text-[10px] tracking-wider text-amber-100/30 uppercase">Editado {f.updated_at}</div>}
                                </Link>
                            ))}
                        </div>
                    )}
                </section>
            </div>
        </AppLayout>
    );
}

interface StatCardProps {
    icon: React.ReactNode;
    label: string;
    value: number;
    sub: string;
    accent: string;
}

function StatCard({ icon, label, value, sub, accent }: StatCardProps) {
    return (
        <div className={`relative overflow-hidden rounded-xl border bg-gradient-to-br p-4 ${accent}`}>
            <div className="flex items-center gap-2 text-xs font-medium tracking-wider uppercase opacity-80">
                {icon}
                {label}
            </div>
            <div className="mt-2 text-3xl font-bold text-amber-50">{typeof value === 'number' ? value.toLocaleString('pt-BR') : value}</div>
            <div className="truncate text-[11px] text-amber-100/50">{sub}</div>
        </div>
    );
}

interface FeaturedHeroesCardProps {
    title: string;
    subtitle: string;
    icon: React.ReactNode;
    heroes: FeaturedHero[];
    accent: string;
}

function FeaturedHeroesCard({ title, subtitle, icon, heroes, accent }: FeaturedHeroesCardProps) {
    return (
        <div className={cn('rounded-2xl border bg-gradient-to-br p-5', accent)}>
            <header className="mb-3 flex items-center gap-2">
                {icon}
                <div>
                    <h3 className="text-sm font-semibold text-amber-100">{title}</h3>
                    <p className="text-[10px] text-amber-100/50">{subtitle}</p>
                </div>
                <Star className="ml-auto size-3.5 text-amber-300/70" />
            </header>
            {heroes.length === 0 && <p className="text-xs text-amber-100/40">Nenhum no seu roster ainda — boa oportunidade de progressão.</p>}
            <div className="flex flex-wrap gap-2">
                {heroes.map((h, idx) => (
                    <Link
                        key={h.slug ?? idx}
                        href={h.slug ? `${route('heroes.index')}?open=${h.slug}` : route('heroes.index')}
                        className="group flex items-center gap-1.5 rounded-full border border-amber-400/30 bg-black/30 py-1 pr-2 pl-1 text-xs transition-all hover:scale-105 hover:bg-black/50"
                    >
                        {h.portrait_url && <img src={h.portrait_url} alt={h.name ?? ''} className="size-5 rounded-full object-cover" />}
                        <span className="truncate text-amber-100 group-hover:text-amber-50">{h.name}</span>
                    </Link>
                ))}
            </div>
        </div>
    );
}
