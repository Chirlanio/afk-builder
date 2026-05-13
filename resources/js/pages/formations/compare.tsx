import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, GitCompareArrows, Target } from 'lucide-react';
import { useMemo } from 'react';
import { HeroChip } from '@/components/formation-editor/hero-chip';
import { PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type {
    ClassCode,
    Faction,
    FactionCode,
    Hero,
    HeroClass,
    ScoreBreakdownPayload,
    Tier,
    TierCode,
} from '@/types/afk';

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

function ScoreRow({
    label,
    a,
    b,
}: {
    label: string;
    a: number;
    b: number;
}) {
    const winner = Math.abs(a - b) < 0.01 ? null : a > b ? 'a' : 'b';
    return (
        <div className="grid grid-cols-[1fr_auto_1fr] items-center gap-3 text-xs">
            <span className={cn('text-right tabular-nums', winner === 'a' && 'font-semibold text-emerald-600 dark:text-emerald-400')}>
                {a.toFixed(2)}
            </span>
            <span className="text-center text-muted-foreground">{label}</span>
            <span className={cn('tabular-nums', winner === 'b' && 'font-semibold text-emerald-600 dark:text-emerald-400')}>
                {b.toFixed(2)}
            </span>
        </div>
    );
}

function TeamColumn({
    team,
    heroById,
    factionById,
    classById,
    tierById,
}: {
    team: ComparedTeam;
    heroById: Record<number, Hero>;
    factionById: Record<number, Faction>;
    classById: Record<number, HeroClass>;
    tierById: Record<number, Tier>;
}) {
    return (
        <div className="rounded-lg border bg-card p-2.5">
            <div className="mb-2 flex items-center justify-between gap-2">
                <span className="text-xs font-semibold">
                    {team.name ?? `Equipe ${team.position}`}
                </span>
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
                                className="grid size-14 place-items-center rounded border-2 border-dashed border-muted-foreground/20 text-[9px] text-muted-foreground"
                            >
                                vazio
                            </div>
                        );
                    }
                    const faction = factionById[hero.faction_id];
                    const klass = classById[hero.hero_class_id];
                    const tier = tierById[hero.tier_id];
                    return (
                        <HeroChip
                            key={slotPos}
                            hero={hero}
                            factionCode={faction.code as FactionCode}
                            classCode={klass.code as ClassCode}
                            tierCode={tier.code as TierCode}
                            size="sm"
                        />
                    );
                })}
            </div>
        </div>
    );
}

export default function FormationsCompare({
    formationA,
    formationB,
    heroes,
    factions,
    heroClasses,
    tiers,
}: PageProps) {
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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Comparar: ${formationA.name} vs ${formationB.name}`} />

            <PageShell
                title="Comparação"
                icon={GitCompareArrows}
                description="Veja lado a lado a pontuação e a composição das duas formações selecionadas."
                actions={
                    <Button asChild variant="outline" size="sm">
                        <Link href="/formations">
                            <ArrowLeft className="size-4" />
                            Voltar
                        </Link>
                    </Button>
                }
            >
                <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                    {[formationA, formationB].map((f) => (
                        <div key={f.id} className="rounded-xl border bg-card p-3">
                            <Link href={`/formations/${f.id}/edit`} className="text-base font-semibold hover:underline">
                                {f.name}
                            </Link>
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

                <div className="rounded-xl border bg-card p-4">
                    <h2 className="mb-3 text-sm font-semibold">Pontuação total</h2>
                    <ScoreRow label="Total geral" a={formationA.total_score} b={formationB.total_score} />
                </div>

                <div className="flex flex-col gap-3">
                    {teamPairs.map(({ position, a, b }) => (
                        <div key={position} className="rounded-xl border bg-card p-3">
                            <div className="mb-3 flex items-center gap-2">
                                <span className="grid size-7 place-items-center rounded-md bg-primary/10 text-sm font-bold text-primary">
                                    {position}
                                </span>
                                <span className="text-sm font-semibold">Equipe {position}</span>
                            </div>

                            <div className="grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto_1fr]">
                                {a ? (
                                    <TeamColumn team={a} heroById={heroById} factionById={factionById} classById={classById} tierById={tierById} />
                                ) : (
                                    <div className="rounded-lg border border-dashed p-4 text-center text-xs text-muted-foreground">
                                        Não existe equipe nessa posição.
                                    </div>
                                )}

                                <div className="flex flex-col justify-center gap-1.5 px-2">
                                    {a && b ? (
                                        <>
                                            <ScoreRow label="Facção" a={a.score.faction_bonus} b={b.score.faction_bonus} />
                                            <ScoreRow label="Classes" a={a.score.class_balance} b={b.score.class_balance} />
                                            <ScoreRow label="Synergia" a={a.score.synergy} b={b.score.synergy} />
                                            <ScoreRow label="Power" a={a.score.power} b={b.score.power} />
                                            <ScoreRow label="Tier rating" a={a.score.tier_rating ?? 0} b={b.score.tier_rating ?? 0} />
                                            <ScoreRow label="Destaques" a={a.score.feature_bonus ?? 0} b={b.score.feature_bonus ?? 0} />
                                            <ScoreRow label="Móveis F3/F9" a={a.score.furniture_effects ?? 0} b={b.score.furniture_effects ?? 0} />
                                            <div className="my-1 border-t" />
                                            <ScoreRow label="Total" a={a.score.total} b={b.score.total} />
                                        </>
                                    ) : (
                                        <span className="text-center text-xs text-muted-foreground">—</span>
                                    )}
                                </div>

                                {b ? (
                                    <TeamColumn team={b} heroById={heroById} factionById={factionById} classById={classById} tierById={tierById} />
                                ) : (
                                    <div className="rounded-lg border border-dashed p-4 text-center text-xs text-muted-foreground">
                                        Não existe equipe nessa posição.
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                </div>
            </PageShell>
        </AppLayout>
    );
}
