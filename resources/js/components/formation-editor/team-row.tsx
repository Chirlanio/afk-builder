import { AlertCircle, CheckCircle2, Loader2, Sparkles } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { CLASS_LABEL, FACTION_THEME } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type {
    ClassCode,
    Faction,
    FactionCode,
    Hero,
    HeroClass,
    Tier,
    TierCode,
} from '@/types/afk';
import { TeamSlot } from './team-slot';

export interface TeamSlotsState {
    position: number;
    name: string | null;
    slots: Array<{ heroId: number | null }>;
}

interface TeamRowProps {
    team: TeamSlotsState;
    heroById: Record<number, Hero>;
    factionById: Record<number, Faction>;
    classById: Record<number, HeroClass>;
    tierById: Record<number, Tier>;
    onRemoveHero: (slotPosition: number) => void;
    onSuggest?: () => void;
    isSuggesting?: boolean;
}

interface FactionBonus {
    label: string;
    color: string;
}

function computeFactionBonus(
    heroes: Hero[],
    factionById: Record<number, Faction>,
): FactionBonus | null {
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

    const regular: Array<[FactionCode, number]> = (Object.entries(counts) as Array<[FactionCode, number]>)
        .filter(([code]) => code !== 'celestial')
        .sort((a, b) => b[1] - a[1]);

    const top = regular[0];
    const second = regular[1];

    if (!top || top[1] === 0) return null;

    const factionLabel = (code: FactionCode) => FACTION_THEME[code].label;

    if (top[1] === 5 || (top[1] >= 4 && top[1] + celestials >= 5)) {
        return {
            label: `5x ${factionLabel(top[0])} ativo`,
            color: FACTION_THEME[top[0]].headerText,
        };
    }

    if ((top[1] === 3 && second?.[1] === 2) ||
        (top[1] === 3 && celestials >= 2) ||
        (top[1] === 2 && second?.[1] === 2 && celestials >= 1)) {
        const partnerLabel = second && second[1] >= 2 ? factionLabel(second[0]) : factionLabel('celestial');
        return {
            label: `3+2: ${factionLabel(top[0])} + ${partnerLabel}`,
            color: FACTION_THEME[top[0]].headerText,
        };
    }

    return null;
}

export function TeamRow({ team, heroById, factionById, classById, tierById, onRemoveHero, onSuggest, isSuggesting }: TeamRowProps) {
    const heroesInTeam: Hero[] = team.slots
        .map((s) => (s.heroId ? heroById[s.heroId] : null))
        .filter((h): h is Hero => h !== null);

    const bonus = computeFactionBonus(heroesInTeam, factionById);

    const filledCount = heroesInTeam.length;

    const classesPresent = new Set<ClassCode>();
    for (const hero of heroesInTeam) {
        const klass = classById[hero.hero_class_id];
        if (klass) classesPresent.add(klass.code as ClassCode);
    }

    const hasTank = classesPresent.has('tank');
    const hasSupport = classesPresent.has('support');
    const hasEmptySlots = filledCount < 5;

    return (
        <div className="flex flex-col gap-2 rounded-xl border bg-card p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <div className="flex items-center gap-2">
                    <span className="grid size-7 place-items-center rounded-md bg-primary/10 text-sm font-bold text-primary">
                        {team.position}
                    </span>
                    <span className="text-sm font-semibold">Equipe {team.position}</span>
                    <Badge variant="secondary" className="text-[10px]">
                        {filledCount}/5
                    </Badge>
                </div>

                <div className="flex flex-wrap items-center gap-1.5">
                    {bonus && (
                        <Badge className={cn('gap-1 bg-amber-500/15 text-foreground', bonus.color)}>
                            <CheckCircle2 className="size-3" />
                            {bonus.label}
                        </Badge>
                    )}
                    {filledCount === 5 && !hasTank && (
                        <Badge variant="outline" className="gap-1 border-amber-500/40 text-amber-600 dark:text-amber-400">
                            <AlertCircle className="size-3" />
                            Sem {CLASS_LABEL.tank}
                        </Badge>
                    )}
                    {filledCount === 5 && !hasSupport && (
                        <Badge variant="outline" className="gap-1 border-amber-500/40 text-amber-600 dark:text-amber-400">
                            <AlertCircle className="size-3" />
                            Sem {CLASS_LABEL.support}
                        </Badge>
                    )}
                    {onSuggest && hasEmptySlots && (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            onClick={onSuggest}
                            disabled={isSuggesting}
                            className="h-7 gap-1 text-xs"
                        >
                            {isSuggesting ? (
                                <Loader2 className="size-3 animate-spin" />
                            ) : (
                                <Sparkles className="size-3" />
                            )}
                            {isSuggesting ? 'Sugerindo...' : 'Sugerir'}
                        </Button>
                    )}
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                {team.slots.map((slot, idx) => {
                    const slotPosition = idx + 1;
                    const hero = slot.heroId ? heroById[slot.heroId] : null;
                    const faction = hero ? factionById[hero.faction_id] : null;
                    const klass = hero ? classById[hero.hero_class_id] : null;
                    const tier = hero ? tierById[hero.tier_id] : null;

                    return (
                        <TeamSlot
                            key={slotPosition}
                            teamPosition={team.position}
                            slotPosition={slotPosition}
                            hero={hero}
                            factionCode={(faction?.code ?? null) as FactionCode | null}
                            classCode={(klass?.code ?? null) as ClassCode | null}
                            tierCode={(tier?.code ?? null) as TierCode | null}
                            onRemove={() => onRemoveHero(slotPosition)}
                        />
                    );
                })}
            </div>
        </div>
    );
}
