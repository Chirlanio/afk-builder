import { Head, Link, usePage } from '@inertiajs/react';
import {
    BookOpen,
    ChevronRight,
    Crown,
    LayoutGrid,
    Sparkles,
    Sword,
    Users,
} from 'lucide-react';

import AppLayout from '@/layouts/app-layout';
import { FACTION_THEME } from '@/lib/afk';
import { type SharedData, type BreadcrumbItem } from '@/types';
import type { FactionCode } from '@/types/afk';

interface DashboardStats {
    roster_total: number;
    roster_ascended: number;
    roster_awakened: number;
    catalog_total: number;
    factions_count: number;
    formations_total: number;
}

interface FactionRosterRow {
    code: FactionCode;
    name_pt: string;
    total: number;
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
    rosterByFaction: FactionRosterRow[];
    recentFormations: RecentFormationRow[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Dashboard', href: '/dashboard' }];

export default function Dashboard({ stats, rosterByFaction, recentFormations }: DashboardProps) {
    const { auth } = usePage<SharedData>().props;
    const userName = auth.user?.name?.split(' ')[0] ?? 'Herói';

    const rosterPct = stats.catalog_total > 0
        ? Math.round((stats.roster_total / stats.catalog_total) * 100)
        : 0;

    const factionMap = new Map(rosterByFaction.map((f) => [f.code, f.total]));
    const allFactions: FactionCode[] = [
        'lightbearer', 'mauler', 'wilder', 'graveborn',
        'celestial', 'hypogean', 'dimensional', 'draconis',
    ];

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
                            <h1 className="mt-3 text-2xl font-bold text-amber-50 md:text-3xl">
                                Olá, {userName}!
                            </h1>
                            <p className="mt-1 max-w-xl text-sm text-amber-100/60">
                                Acompanhe seu progresso, monte formações e conquiste cada modo de Esperia.
                            </p>
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

                <section className="grid gap-6 lg:grid-cols-3">
                    <div className="lg:col-span-2">
                        <div className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5 md:p-6">
                            <header className="mb-5 flex items-center justify-between">
                                <div>
                                    <h2 className="text-lg font-semibold text-amber-100">Roster por facção</h2>
                                    <p className="text-xs text-amber-100/50">
                                        Distribuição dos seus heróis pelas 8 facções
                                    </p>
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
                                            <div className={`text-xs font-semibold ${theme.headerText}`}>
                                                {theme.label}
                                            </div>
                                            <div className="mt-1 text-2xl font-bold text-amber-50">
                                                {total}
                                            </div>
                                            <div className="text-[10px] tracking-wider text-amber-100/40 uppercase">
                                                {total === 1 ? 'herói' : 'heróis'}
                                            </div>
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
                                    <div className="text-xs text-amber-100/50">
                                        {stats.catalog_total} disponíveis em {stats.factions_count} facções
                                    </div>
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
                                    <div className="text-xs text-amber-100/50">
                                        {stats.formations_total} salvas
                                    </div>
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
                                    <div className="text-xs text-amber-100/50">
                                        Hotel Oak, Inscrições, Atributos…
                                    </div>
                                </div>
                                <ChevronRight className="size-4 text-amber-100/30 transition-transform group-hover:translate-x-1 group-hover:text-amber-300" />
                            </div>
                        </Link>
                    </div>
                </section>

                <section className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5 md:p-6">
                    <header className="mb-4 flex items-center justify-between">
                        <div>
                            <h2 className="text-lg font-semibold text-amber-100">Formações recentes</h2>
                            <p className="text-xs text-amber-100/50">
                                Últimas 3 que você editou
                            </p>
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
                            <p className="mt-3 text-sm text-amber-100/60">
                                Nenhuma formação criada ainda.
                            </p>
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
                                        <div className="font-semibold text-amber-100 group-hover:text-amber-50">
                                            {f.name}
                                        </div>
                                        <span
                                            className={`shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-medium tracking-wider uppercase ${
                                                f.mode === 'real'
                                                    ? 'border-emerald-400/40 bg-emerald-400/10 text-emerald-300'
                                                    : 'border-sky-400/40 bg-sky-400/10 text-sky-300'
                                            }`}
                                        >
                                            {f.mode === 'real' ? 'Real' : 'Alvo'}
                                        </span>
                                    </div>
                                    <div className="text-xs text-amber-100/50">
                                        {f.challenge_label ?? '—'} · {f.teams_count} eq.
                                    </div>
                                    {f.updated_at && (
                                        <div className="text-[10px] tracking-wider text-amber-100/30 uppercase">
                                            Editado {f.updated_at}
                                        </div>
                                    )}
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
        <div
            className={`relative overflow-hidden rounded-xl border bg-gradient-to-br p-4 ${accent}`}
        >
            <div className="flex items-center gap-2 text-xs font-medium tracking-wider uppercase opacity-80">
                {icon}
                {label}
            </div>
            <div className="mt-2 text-3xl font-bold text-amber-50">{value}</div>
            <div className="text-[11px] text-amber-100/50">{sub}</div>
        </div>
    );
}
