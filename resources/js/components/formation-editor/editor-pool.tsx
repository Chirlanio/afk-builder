import { cn } from '@/lib/utils';
import type { Beast, Faction, Hero, HeroClass, Tier } from '@/types/afk';
import { PawPrint, Users } from 'lucide-react';
import { useState } from 'react';
import { BeastPool } from './beast-pool';
import { HeroPool } from './hero-pool';

interface EditorPoolProps {
    heroes: Hero[];
    beasts: Beast[];
    factions: Faction[];
    heroClasses: HeroClass[];
    factionById: Record<number, Faction>;
    classById: Record<number, HeroClass>;
    tierById: Record<number, Tier>;
    placedHeroIds: Set<number>;
    enforceUnique: boolean;
    onTapHero?: (heroId: number) => void;
    onTapBeast?: (beastId: number) => void;
}

type Tab = 'heroes' | 'beasts';

export function EditorPool({
    heroes,
    beasts,
    factions,
    heroClasses,
    factionById,
    classById,
    tierById,
    placedHeroIds,
    enforceUnique,
    onTapHero,
    onTapBeast,
}: EditorPoolProps) {
    const [tab, setTab] = useState<Tab>('heroes');

    return (
        <div className="flex h-full flex-col gap-2">
            <div className="bg-card flex shrink-0 rounded-lg border p-0.5">
                <button
                    type="button"
                    onClick={() => setTab('heroes')}
                    className={cn(
                        'flex flex-1 items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium transition-colors',
                        tab === 'heroes' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted',
                    )}
                >
                    <Users className="size-3.5" />
                    Heróis
                </button>
                <button
                    type="button"
                    onClick={() => setTab('beasts')}
                    className={cn(
                        'flex flex-1 items-center justify-center gap-1.5 rounded-md px-2 py-1.5 text-xs font-medium transition-colors',
                        tab === 'beasts' ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:bg-muted',
                    )}
                >
                    <PawPrint className="size-3.5" />
                    Bestas
                    <span className="bg-muted/60 ml-1 rounded-full px-1.5 py-0.5 text-[9px] tabular-nums">{beasts.length}</span>
                </button>
            </div>

            <div className="min-h-0 flex-1">
                {tab === 'heroes' ? (
                    <HeroPool
                        heroes={heroes}
                        factions={factions}
                        heroClasses={heroClasses}
                        factionById={factionById}
                        classById={classById}
                        tierById={tierById}
                        placedHeroIds={placedHeroIds}
                        enforceUnique={enforceUnique}
                        onTapHero={onTapHero}
                    />
                ) : (
                    <BeastPool beasts={beasts} onTapBeast={onTapBeast} />
                )}
            </div>
        </div>
    );
}
