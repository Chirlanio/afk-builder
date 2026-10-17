import { HeroChip } from '@/components/formation-editor/hero-chip';
import { PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { ClassCode, Faction, FactionCode, Hero, HeroClass, ScoreBreakdownPayload, Tier, TierCode } from '@/types/afk';
import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, GitCompareArrows, Minus, Plus, Target, Trophy, Users } from 'lucide-react';
import { useMemo } from 'react';

interface ComparedTeam {
    id: number;
    position: number;
    name: string | null;
    hero_slots: Array<{ slot: number; hero_id: number }>;
    score: ScoreBreakdownPayload;
}

interface ComparedFormation {
    id: number;
    name: string;
    mode: 'real' | 'target';
    teams_count: number;
    challenge_type: { name_pt: string; is_pvp: boolean; teams_must_be_unique: boolean };
    teams: ComparedTeam[];
    total_score: number;
}

interface PageProps {
    formationA: ComparedFormation;
    formationB: ComparedFormation;
    heroes: Hero[];
    factions: Faction[];
    heroClasses: HeroClass[];
    tiers: Tier[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Formações', href: '/formations' },
    { title: 'Comparar', href: '#' },
];

interface ComponentMetric {
    key: keyof ScoreBreakdownPayload;
    label: string;
}

const SCORE_COMPONENTS: ComponentMetric[] = [
    { key: 'power', label: 'Power' },
    { key: 'synergy', label: 'Synergia' },
    { key: 'faction_bonus', label: 'Facção' },
    { key: 'class_balance', label: 'Classes' },
    { key: 'tier_rating', label: 'Tier rating' },
    { key: 'feature_bonus', label: 'Destaques' },
    { key: 'furniture_effects', label: 'Móveis F3/F9' },
    { key: 'engraving_bonus', label: 'Inscrições' },
];

const EPSILON = 0.01;

function compareValues(a: number, b: number): 'a' | 'b' | 'tie' {
    if (Math.abs(a - b) < EPSILON) return 'tie';
    return a > b ? 'a' : 'b';
}

function formatDelta(value: number): string {
    const abs = Math.abs(value);
    if (abs < EPSILON) return '0';
    return abs.toFixed(2);
}

interface ScoreRowProps {
    label: string;
    a: number;
    b: number;
    bold?: boolean;
}

function ScoreRow({ label, a, b, bold }: ScoreRowProps) {
    const winner = compareValues(a, b);
    const delta = Math.abs(a - b);
    const max = Math.max(a, b, 0.01);
    const aPct = (a / max) * 100;
    const bPct = (b / max) * 100;

    return (
        <div className="flex flex-col gap-0.5">
            <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-2 text-xs">
                <div
                    className={cn(
                        'flex items-center justify-end gap-1 tabular-nums',
                        winner === 'a' && 'font-semibold text-emerald-600 dark:text-emerald-400',
                        bold && 'font-bold',
                    )}
                >
                    {winner === 'a' && <Plus className="size-2.5 text-emerald-500" />}
                    <span>{a.toFixed(2)}</span>
                </div>
                <span className={cn('text-muted-foreground text-center', bold && 'text-foreground font-semibold')}>{label}</span>
                <div
                    className={cn(
                        'flex items-center gap-1 tabular-nums',
                        winner === 'b' && 'font-semibold text-emerald-600 dark:text-emerald-400',
                        bold && 'font-bold',
                    )}
                >
                    <span>{b.toFixed(2)}</span>
                    {winner === 'b' && <Plus className="size-2.5 text-emerald-500" />}
                </div>
            </div>
            {/* Barra de progresso ratio A vs B */}
            <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-2">
                <div className="flex h-1 justify-end">
                    <div
                        className={cn(
                            'h-full rounded-l-full transition-all',
                            winner === 'a' ? 'bg-emerald-500' : winner === 'tie' ? 'bg-muted-foreground/40' : 'bg-muted-foreground/30',
                        )}
                        style={{ width: `${aPct}%` }}
                    />
                </div>
                <span className="text-muted-foreground min-w-[36px] text-center font-mono text-[10px] tabular-nums">
                    {winner === 'tie' ? '=' : `Δ${formatDelta(delta)}`}
                </span>
                <div className="flex h-1 justify-start">
                    <div
                        className={cn(
                            'h-full rounded-r-full transition-all',
                            winner === 'b' ? 'bg-emerald-500' : winner === 'tie' ? 'bg-muted-foreground/40' : 'bg-muted-foreground/30',
                        )}
                        style={{ width: `${bPct}%` }}
                    />
                </div>
            </div>
        </div>
    );
}

function TeamColumn({
    team,
    heroById,
    factionById,
    classById,
    tierById,
    highlightHeroIds,
    side,
}: {
    team: ComparedTeam;
    heroById: Record<number, Hero>;
    factionById: Record<number, Faction>;
    classById: Record<number, HeroClass>;
    tierById: Record<number, Tier>;
    highlightHeroIds: Set<number>;
    side: 'a' | 'b';
}) {
    return (
        <div className="bg-card rounded-lg border p-2.5">
            <div className="mb-2 flex items-center justify-between gap-2">
                <span className="text-xs font-semibold">{team.name ?? `Equipe ${team.position}`}</span>
                <Badge variant="secondary" className="text-[10px]">
                    {team.score.total.toFixed(2)}
                </Badge>
            </div>
            <div className="flex flex-wrap gap-1.5">
                {Array.from({ length: 5 }, (_, i) => {
                    const slotPos = i + 1;
                    const slotData = team.hero_slots.find((s) => s.slot === slotPos);
                    const hero = slotData ? heroById[slotData.hero_id] : null;
                    if (!hero) {
                        return (
                            <div
                                key={slotPos}
                                className="border-muted-foreground/20 text-muted-foreground grid size-14 place-items-center rounded border-2 border-dashed text-[9px]"
                            >
                                vazio
                            </div>
                        );
                    }
                    const faction = factionById[hero.faction_id];
                    const klass = classById[hero.hero_class_id];
                    const tier = tierById[hero.tier_id];
                    const isExclusive = highlightHeroIds.has(hero.id);
                    return (
                        <div
                            key={slotPos}
                            className={cn(
                                'rounded transition-all',
                                isExclusive &&
                                    (side === 'a'
                                        ? 'ring-offset-card ring-2 ring-sky-400 ring-offset-1'
                                        : 'ring-offset-card ring-2 ring-amber-400 ring-offset-1'),
                            )}
                            title={isExclusive ? `Exclusivo de ${side === 'a' ? 'A' : 'B'}` : undefined}
                        >
                            <HeroChip
                                hero={hero}
                                factionCode={faction.code as FactionCode}
                                classCode={klass.code as ClassCode}
                                tierCode={tier.code as TierCode}
                                size="sm"
                            />
                        </div>
                    );
                })}
            </div>
        </div>
    );
}

function HeroBadge({
    hero,
    factionById,
    classById,
    tierById,
}: {
    hero: Hero;
    factionById: Record<number, Faction>;
    classById: Record<number, HeroClass>;
    tierById: Record<number, Tier>;
}) {
    const faction = factionById[hero.faction_id];
    const klass = classById[hero.hero_class_id];
    const tier = tierById[hero.tier_id];
    return (
        <HeroChip
            hero={hero}
            factionCode={faction.code as FactionCode}
            classCode={klass.code as ClassCode}
            tierCode={tier.code as TierCode}
            size="sm"
        />
    );
}

export default function FormationsCompare({ formationA, formationB, heroes, factions, heroClasses, tiers }: PageProps) {
    const heroById = useMemo(() => Object.fromEntries(heroes.map((h) => [h.id, h])) as Record<number, Hero>, [heroes]);
    const factionById = useMemo(() => Object.fromEntries(factions.map((f) => [f.id, f])) as Record<number, Faction>, [factions]);
    const classById = useMemo(() => Object.fromEntries(heroClasses.map((c) => [c.id, c])) as Record<number, HeroClass>, [heroClasses]);
    const tierById = useMemo(() => Object.fromEntries(tiers.map((t) => [t.id, t])) as Record<number, Tier>, [tiers]);

    const teamPairs = useMemo(() => {
        const max = Math.max(formationA.teams.length, formationB.teams.length);
        return Array.from({ length: max }, (_, i) => ({
            position: i + 1,
            a: formationA.teams[i],
            b: formationB.teams[i],
        }));
    }, [formationA.teams, formationB.teams]);

    // Hero ids únicos de cada formação (geral, todos os teams)
    const heroIdsByFormation = useMemo(() => {
        const collect = (f: ComparedFormation) => {
            const set = new Set<number>();
            f.teams.forEach((t) => t.hero_slots.forEach((s) => set.add(s.hero_id)));
            return set;
        };
        return {
            a: collect(formationA),
            b: collect(formationB),
        };
    }, [formationA, formationB]);

    const heroDiff = useMemo(() => {
        const onlyA = [...heroIdsByFormation.a].filter((id) => !heroIdsByFormation.b.has(id));
        const onlyB = [...heroIdsByFormation.b].filter((id) => !heroIdsByFormation.a.has(id));
        const common = [...heroIdsByFormation.a].filter((id) => heroIdsByFormation.b.has(id));
        return { onlyA, onlyB, common };
    }, [heroIdsByFormation]);

    // Heroes exclusivos da formação A vs B — para destaque nos chips dos teams
    const exclusiveIds = useMemo(
        () => ({
            a: new Set(heroDiff.onlyA),
            b: new Set(heroDiff.onlyB),
        }),
        [heroDiff],
    );

    // Wins por componente — calcula sobre o agregado das teams
    const componentWins = useMemo(() => {
        let aWins = 0;
        let bWins = 0;
        let ties = 0;

        for (const { key } of SCORE_COMPONENTS) {
            // soma do componente em todos os teams (proxy razoável pro "geral")
            const aSum = formationA.teams.reduce((acc, t) => acc + Number(t.score[key] ?? 0), 0);
            const bSum = formationB.teams.reduce((acc, t) => acc + Number(t.score[key] ?? 0), 0);
            const result = compareValues(aSum, bSum);
            if (result === 'a') aWins += 1;
            else if (result === 'b') bWins += 1;
            else ties += 1;
        }

        return { aWins, bWins, ties, total: SCORE_COMPONENTS.length };
    }, [formationA.teams, formationB.teams]);

    const totalWinner = compareValues(formationA.total_score, formationB.total_score);
    const totalDelta = Math.abs(formationA.total_score - formationB.total_score);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Comparar: ${formationA.name} vs ${formationB.name}`} />

            <PageShell
                title="Comparação"
                icon={GitCompareArrows}
                description="Diff visual entre 2 formações: pontuação por componente, vencedores e composição de heróis."
                actions={
                    <Button asChild variant="outline" size="sm">
                        <Link href="/formations">
                            <ArrowLeft className="size-4" />
                            Voltar
                        </Link>
                    </Button>
                }
            >
                {/* Cabeçalho das 2 formações */}
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    {[
                        { f: formationA, side: 'a' as const, color: 'sky' as const },
                        { f: formationB, side: 'b' as const, color: 'amber' as const },
                    ].map(({ f, side, color }) => (
                        <div
                            key={f.id}
                            className={cn(
                                'rounded-xl border p-3',
                                totalWinner === side
                                    ? color === 'sky'
                                        ? 'border-sky-500/40 bg-sky-500/5'
                                        : 'border-amber-500/40 bg-amber-500/5'
                                    : 'bg-card',
                            )}
                        >
                            <div className="flex items-center gap-1.5">
                                <span
                                    className={cn(
                                        'inline-flex size-5 items-center justify-center rounded-full text-[10px] font-bold uppercase',
                                        color === 'sky'
                                            ? 'bg-sky-500/20 text-sky-700 dark:text-sky-300'
                                            : 'bg-amber-500/20 text-amber-700 dark:text-amber-300',
                                    )}
                                    aria-label={`Formação ${side.toUpperCase()}`}
                                >
                                    {side.toUpperCase()}
                                </span>
                                <Link href={`/formations/${f.id}/edit`} className="text-base font-semibold hover:underline">
                                    {f.name}
                                </Link>
                                {totalWinner === side && (
                                    <Badge variant="outline" className="ml-auto gap-1 border-emerald-500/40 text-emerald-600 dark:text-emerald-400">
                                        <Trophy className="size-3" />
                                        Total maior
                                    </Badge>
                                )}
                            </div>
                            <div className="mt-1 flex flex-wrap items-center gap-1.5">
                                <Badge variant="secondary">{f.challenge_type.name_pt}</Badge>
                                {f.mode === 'target' && (
                                    <Badge variant="outline" className="gap-1 text-xs">
                                        <Target className="size-3" />
                                        Alvo
                                    </Badge>
                                )}
                                <Badge className="ml-auto" variant="outline">
                                    Total: <span className="ml-1 font-mono">{f.total_score.toFixed(2)}</span>
                                </Badge>
                            </div>
                        </div>
                    ))}
                </div>

                {/* Resumo: total + componentes vencedores */}
                <div className="bg-card rounded-xl border p-4">
                    <h2 className="mb-3 text-sm font-semibold">Pontuação total e componentes</h2>
                    <ScoreRow label="Total geral" a={formationA.total_score} b={formationB.total_score} bold />

                    <div className="mt-4 grid grid-cols-3 gap-2 text-center text-xs">
                        <div className="rounded-md border bg-sky-500/5 p-2">
                            <div className="text-[10px] tracking-wide text-sky-700 uppercase dark:text-sky-300">A venceu</div>
                            <div className="font-mono text-lg font-bold tabular-nums">
                                {componentWins.aWins}/{componentWins.total}
                            </div>
                        </div>
                        <div className="bg-muted/50 rounded-md border p-2">
                            <div className="text-muted-foreground text-[10px] tracking-wide uppercase">Empate</div>
                            <div className="font-mono text-lg font-bold tabular-nums">
                                {componentWins.ties}/{componentWins.total}
                            </div>
                        </div>
                        <div className="rounded-md border bg-amber-500/5 p-2">
                            <div className="text-[10px] tracking-wide text-amber-700 uppercase dark:text-amber-300">B venceu</div>
                            <div className="font-mono text-lg font-bold tabular-nums">
                                {componentWins.bWins}/{componentWins.total}
                            </div>
                        </div>
                    </div>

                    {totalWinner !== 'tie' && (
                        <p className="text-muted-foreground mt-3 text-center text-xs">
                            <span
                                className={cn(
                                    'font-semibold',
                                    totalWinner === 'a' ? 'text-sky-600 dark:text-sky-400' : 'text-amber-600 dark:text-amber-400',
                                )}
                            >
                                {totalWinner === 'a' ? formationA.name : formationB.name}
                            </span>{' '}
                            tem total <span className="font-mono">+{totalDelta.toFixed(2)}</span> pontos a mais.
                        </p>
                    )}
                </div>

                {/* Composição: heróis em comum vs exclusivos */}
                <div className="bg-card rounded-xl border p-4">
                    <h2 className="mb-3 flex items-center gap-2 text-sm font-semibold">
                        <Users className="size-4" />
                        Composição geral
                    </h2>
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-3">
                        <HeroGroup
                            title="Só na A"
                            count={heroDiff.onlyA.length}
                            heroIds={heroDiff.onlyA}
                            heroById={heroById}
                            factionById={factionById}
                            classById={classById}
                            tierById={tierById}
                            tone="sky"
                        />
                        <HeroGroup
                            title="Em ambas"
                            count={heroDiff.common.length}
                            heroIds={heroDiff.common}
                            heroById={heroById}
                            factionById={factionById}
                            classById={classById}
                            tierById={tierById}
                            tone="emerald"
                        />
                        <HeroGroup
                            title="Só na B"
                            count={heroDiff.onlyB.length}
                            heroIds={heroDiff.onlyB}
                            heroById={heroById}
                            factionById={factionById}
                            classById={classById}
                            tierById={tierById}
                            tone="amber"
                        />
                    </div>
                </div>

                {/* Comparação team a team */}
                <div className="flex flex-col gap-3">
                    {teamPairs.map(({ position, a, b }) => (
                        <div key={position} className="bg-card rounded-xl border p-3">
                            <div className="mb-3 flex items-center gap-2">
                                <span className="bg-primary/10 text-primary grid size-7 place-items-center rounded-md text-sm font-bold">
                                    {position}
                                </span>
                                <span className="text-sm font-semibold">Equipe {position}</span>
                                {a && b && (
                                    <Badge variant="outline" className="ml-auto gap-1 text-xs">
                                        Δ total: <span className="font-mono">{formatDelta(a.score.total - b.score.total)}</span>
                                    </Badge>
                                )}
                            </div>

                            <div className="grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto_1fr]">
                                {a ? (
                                    <TeamColumn
                                        team={a}
                                        heroById={heroById}
                                        factionById={factionById}
                                        classById={classById}
                                        tierById={tierById}
                                        highlightHeroIds={exclusiveIds.a}
                                        side="a"
                                    />
                                ) : (
                                    <div className="text-muted-foreground rounded-lg border border-dashed p-4 text-center text-xs">
                                        Não existe equipe nessa posição.
                                    </div>
                                )}

                                <div className="flex flex-col justify-center gap-2 px-2">
                                    {a && b ? (
                                        <>
                                            {SCORE_COMPONENTS.map(({ key, label }) => (
                                                <ScoreRow key={key} label={label} a={Number(a.score[key] ?? 0)} b={Number(b.score[key] ?? 0)} />
                                            ))}
                                            <div className="my-1 border-t" />
                                            <ScoreRow label="Total" a={a.score.total} b={b.score.total} bold />
                                        </>
                                    ) : (
                                        <span className="text-muted-foreground text-center text-xs">—</span>
                                    )}
                                </div>

                                {b ? (
                                    <TeamColumn
                                        team={b}
                                        heroById={heroById}
                                        factionById={factionById}
                                        classById={classById}
                                        tierById={tierById}
                                        highlightHeroIds={exclusiveIds.b}
                                        side="b"
                                    />
                                ) : (
                                    <div className="text-muted-foreground rounded-lg border border-dashed p-4 text-center text-xs">
                                        Não existe equipe nessa posição.
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                </div>

                <p className="text-muted-foreground text-center text-[11px]">
                    Heróis com anel <span className="inline-block size-2 rounded-full bg-sky-400 align-middle" /> ou{' '}
                    <span className="inline-block size-2 rounded-full bg-amber-400 align-middle" /> são exclusivos daquela formação.
                </p>
            </PageShell>
        </AppLayout>
    );
}

interface HeroGroupProps {
    title: string;
    count: number;
    heroIds: number[];
    heroById: Record<number, Hero>;
    factionById: Record<number, Faction>;
    classById: Record<number, HeroClass>;
    tierById: Record<number, Tier>;
    tone: 'sky' | 'amber' | 'emerald';
}

function HeroGroup({ title, count, heroIds, heroById, factionById, classById, tierById, tone }: HeroGroupProps) {
    const toneClasses =
        tone === 'sky'
            ? 'border-sky-500/30 bg-sky-500/5'
            : tone === 'amber'
              ? 'border-amber-500/30 bg-amber-500/5'
              : 'border-emerald-500/30 bg-emerald-500/5';

    return (
        <div className={cn('rounded-lg border p-2.5', toneClasses)}>
            <div className="mb-2 flex items-center justify-between">
                <span className="text-xs font-semibold">{title}</span>
                <Badge variant="outline" className="text-[10px]">
                    {count}
                </Badge>
            </div>
            {heroIds.length === 0 ? (
                <p className="text-muted-foreground flex items-center gap-1 text-[11px]">
                    <Minus className="size-3" />
                    Nenhum
                </p>
            ) : (
                <div className="flex flex-wrap gap-1.5">
                    {heroIds.map((id) => {
                        const hero = heroById[id];
                        if (!hero) return null;
                        return <HeroBadge key={id} hero={hero} factionById={factionById} classById={classById} tierById={tierById} />;
                    })}
                </div>
            )}
        </div>
    );
}
