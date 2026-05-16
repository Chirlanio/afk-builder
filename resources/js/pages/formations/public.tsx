import { HeroChip } from '@/components/formation-editor/hero-chip';
import { MarkdownContent } from '@/components/markdown-content';
import { Badge } from '@/components/ui/badge';
import { FACTION_THEME } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { ChallengeType, ClassCode, Faction, FactionCode, Formation, FormationTeam, Hero, HeroClass, Tier, TierCode } from '@/types/afk';
import { Head, Link } from '@inertiajs/react';
import { CheckCircle2, Eye, PawPrint, Target } from 'lucide-react';
import { useMemo } from 'react';
import { summarizeScope } from '@/lib/beast';

interface PageProps {
    formation: Formation & {
        challenge_type: ChallengeType;
        teams: FormationTeam[];
        user: { id: number; name: string };
    };
    heroes: Hero[];
    factions: Faction[];
    heroClasses: HeroClass[];
    tiers: Tier[];
}

interface FactionBonus {
    label: string;
    color: string;
}

function computeFactionBonus(heroes: Hero[], factionById: Record<number, Faction>): FactionBonus | null {
    if (heroes.length < 5) return null;

    const counts: Record<FactionCode, number> = {
        lightbearer: 0,
        mauler: 0,
        wilder: 0,
        graveborn: 0,
        celestial: 0,
        hypogean: 0,
        dimensional: 0,
        draconis: 0,
    };

    for (const hero of heroes) {
        const faction = factionById[hero.faction_id];
        if (faction) counts[faction.code as FactionCode]++;
    }

    const celestials = counts.celestial;
    const regular = (Object.entries(counts) as Array<[FactionCode, number]>)
        .filter(([code]) => code !== 'celestial' && code !== 'dimensional')
        .sort((a, b) => b[1] - a[1]);

    const top = regular[0];
    const second = regular[1];
    if (!top || top[1] === 0) return null;

    const factionLabel = (code: FactionCode) => FACTION_THEME[code].label;

    if (top[1] + celestials >= 5 && top[1] >= 1) {
        return { label: `5x ${factionLabel(top[0])} ativo`, color: FACTION_THEME[top[0]].headerText };
    }

    if (top[1] >= 3 && (second?.[1] ?? 0) + celestials >= 5 - top[1] && (second?.[1] ?? 0) >= 1) {
        return { label: `3+2: ${factionLabel(top[0])} + ${factionLabel(second[0])}`, color: FACTION_THEME[top[0]].headerText };
    }

    return null;
}

export default function FormationsPublic({ formation, heroes, factions, heroClasses, tiers }: PageProps) {
    const heroById = useMemo(() => Object.fromEntries(heroes.map((h) => [h.id, h])) as Record<number, Hero>, [heroes]);
    const factionById = useMemo(() => Object.fromEntries(factions.map((f) => [f.id, f])) as Record<number, Faction>, [factions]);
    const classById = useMemo(() => Object.fromEntries(heroClasses.map((c) => [c.id, c])) as Record<number, HeroClass>, [heroClasses]);
    const tierById = useMemo(() => Object.fromEntries(tiers.map((t) => [t.id, t])) as Record<number, Tier>, [tiers]);

    return (
        <>
            <Head title={formation.name} />

            <div className="bg-background min-h-screen">
                <header className="bg-background/95 supports-[backdrop-filter]:bg-background/80 sticky top-0 z-10 border-b backdrop-blur">
                    <div className="mx-auto flex max-w-5xl flex-wrap items-center justify-between gap-2 px-4 py-3">
                        <div>
                            <Link href="/" className="text-sm font-semibold">
                                AFK Builder
                            </Link>
                            <Badge variant="outline" className="ml-2 gap-1 text-xs">
                                <Eye className="size-3" />
                                Visualização pública
                            </Badge>
                        </div>
                        <div className="text-muted-foreground text-xs">
                            por <span className="text-foreground font-medium">{formation.user?.name ?? '—'}</span>
                        </div>
                    </div>
                </header>

                <main className="mx-auto flex max-w-5xl flex-col gap-4 p-4">
                    <div className="flex flex-col gap-1">
                        <h1 className="text-2xl font-semibold">{formation.name}</h1>
                        <div className="flex flex-wrap items-center gap-1.5">
                            <Badge variant="secondary">{formation.challenge_type.name_pt}</Badge>
                            {formation.mode === 'target' && (
                                <Badge variant="outline" className="gap-1">
                                    <Target className="size-3" />
                                    Alvo
                                </Badge>
                            )}
                            {formation.challenge_type.is_pvp && (
                                <Badge variant="outline" className="border-rose-500/40 text-rose-600 dark:text-rose-400">
                                    PvP
                                </Badge>
                            )}
                        </div>
                        {formation.notes && (
                            <div className="mt-3 rounded-lg border bg-muted/30 p-3 text-muted-foreground">
                                <MarkdownContent>{formation.notes}</MarkdownContent>
                            </div>
                        )}
                    </div>

                    <div className="flex flex-col gap-3">
                        {formation.teams.map((team) => {
                            const heroesInTeam = team.team_heroes
                                .sort((a, b) => a.slot - b.slot)
                                .map((th) => heroById[th.hero_id])
                                .filter((h): h is Hero => Boolean(h));

                            const bonus = computeFactionBonus(heroesInTeam, factionById);

                            return (
                                <div key={team.id} className="bg-card flex flex-col gap-2 rounded-xl border p-3">
                                    <div className="flex flex-wrap items-center justify-between gap-2">
                                        <div className="flex items-center gap-2">
                                            <span className="bg-primary/10 text-primary grid size-7 place-items-center rounded-md text-sm font-bold">
                                                {team.position}
                                            </span>
                                            <span className="text-sm font-semibold">{team.name ?? `Equipe ${team.position}`}</span>
                                            <Badge variant="secondary" className="text-[10px]">
                                                {heroesInTeam.length}/5
                                            </Badge>
                                        </div>
                                        {bonus && (
                                            <Badge className={cn('text-foreground gap-1 bg-amber-500/15', bonus.color)}>
                                                <CheckCircle2 className="size-3" />
                                                {bonus.label}
                                            </Badge>
                                        )}
                                    </div>

                                    {team.beast && (
                                        <div className="flex items-center gap-2 rounded-md border border-emerald-500/30 bg-emerald-500/5 px-2 py-1.5 text-xs">
                                            {team.beast.portrait_url ? (
                                                <img src={team.beast.portrait_url} alt={team.beast.name} className="size-7 rounded object-cover object-top" loading="lazy" />
                                            ) : (
                                                <div className="grid size-7 place-items-center rounded bg-muted text-muted-foreground">
                                                    <PawPrint className="size-3.5" />
                                                </div>
                                            )}
                                            <span className="font-medium">{team.beast.name}</span>
                                            {team.beast_level !== undefined && team.beast_level !== null && (
                                                <span className="rounded bg-muted/60 px-1 text-[10px] tabular-nums">Nv {team.beast_level}</span>
                                            )}
                                            <span className="text-muted-foreground">
                                                {summarizeScope(team.beast.buffs_team, 3) || summarizeScope(team.beast.buffs_strength, 2) || summarizeScope(team.beast.buffs_intelligence, 2) || summarizeScope(team.beast.buffs_agility, 2)}
                                            </span>
                                        </div>
                                    )}

                                    <div className="flex flex-wrap items-center gap-2">
                                        {Array.from({ length: 5 }, (_, idx) => {
                                            const slotPos = idx + 1;
                                            const teamHero = team.team_heroes.find((th) => th.slot === slotPos);
                                            const hero = teamHero ? heroById[teamHero.hero_id] : null;

                                            if (!hero) {
                                                return (
                                                    <div
                                                        key={slotPos}
                                                        className="border-muted-foreground/30 text-muted-foreground grid size-20 place-items-center rounded-lg border-2 border-dashed text-xs"
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
                                                    size="md"
                                                />
                                            );
                                        })}
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    <p className="text-muted-foreground mt-4 text-center text-xs">
                        Esta visualização é read-only. Para criar suas próprias formações,{' '}
                        <Link href="/login" className="font-medium underline">
                            faça login
                        </Link>
                        .
                    </p>
                </main>
            </div>
        </>
    );
}
