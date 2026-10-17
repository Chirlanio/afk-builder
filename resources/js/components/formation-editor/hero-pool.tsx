import { Input } from '@/components/ui/input';
import { CLASS_ICON, FACTION_THEME } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { ClassCode, Faction, FactionCode, Hero, HeroClass, Tier, TierCode } from '@/types/afk';
import { useDraggable } from '@dnd-kit/core';
import { CSS } from '@dnd-kit/utilities';
import { Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { HeroChip } from './hero-chip';

interface HeroPoolProps {
    heroes: Hero[];
    factions: Faction[];
    heroClasses: HeroClass[];
    factionById: Record<number, Faction>;
    classById: Record<number, HeroClass>;
    tierById: Record<number, Tier>;
    placedHeroIds: Set<number>;
    enforceUnique: boolean;
    onTapHero?: (heroId: number) => void;
}

interface DraggableChipProps {
    hero: Hero;
    factionCode: FactionCode;
    classCode: ClassCode;
    tierCode: TierCode;
    disabled: boolean;
    onTap?: () => void;
}

function DraggableChip({ hero, factionCode, classCode, tierCode, disabled, onTap }: DraggableChipProps) {
    const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
        id: `pool:${hero.id}`,
        data: { kind: 'pool', heroId: hero.id },
        disabled,
    });

    // Quando onTap está definido, o chip também responde a click rápido (não-drag).
    // O TouchSensor exige delay de 150ms pra começar drag, então um tap simples
    // (<150ms) cai aqui sem disparar o DnD.
    const handleClick = onTap && !disabled && !isDragging ? onTap : undefined;

    return (
        <div
            ref={setNodeRef}
            {...listeners}
            {...attributes}
            onClick={handleClick}
            style={transform ? { transform: CSS.Translate.toString(transform) } : undefined}
            className={cn(
                'touch-none',
                onTap && !disabled ? 'cursor-pointer transition-transform active:scale-95' : 'cursor-grab active:cursor-grabbing',
                isDragging && 'z-50 opacity-50',
                disabled && 'cursor-not-allowed',
            )}
            aria-label={onTap ? `Adicionar ${hero.name} ao próximo slot` : undefined}
            role={onTap ? 'button' : undefined}
        >
            <HeroChip hero={hero} factionCode={factionCode} classCode={classCode} tierCode={tierCode} size="sm" dimmed={disabled} />
        </div>
    );
}

export function HeroPool({
    heroes,
    factions,
    heroClasses,
    factionById,
    classById,
    tierById,
    placedHeroIds,
    enforceUnique,
    onTapHero,
}: HeroPoolProps) {
    const [search, setSearch] = useState('');
    const [factionFilter, setFactionFilter] = useState<Set<number>>(new Set());
    const [classFilter, setClassFilter] = useState<Set<number>>(new Set());

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();
        return heroes.filter((hero) => {
            if (factionFilter.size > 0 && !factionFilter.has(hero.faction_id)) return false;
            if (classFilter.size > 0 && !classFilter.has(hero.hero_class_id)) return false;
            if (term) {
                const haystack = `${hero.name} ${hero.title ?? ''}`.toLowerCase();
                if (!haystack.includes(term)) return false;
            }
            return true;
        });
    }, [heroes, search, factionFilter, classFilter]);

    const toggle = (set: Set<number>, id: number, setter: (s: Set<number>) => void) => {
        const next = new Set(set);
        if (next.has(id)) next.delete(id);
        else next.add(id);
        setter(next);
    };

    return (
        <div className="bg-card flex h-full flex-col gap-2 rounded-xl border p-3">
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-semibold">Heróis disponíveis</h3>
                <span className="text-muted-foreground text-xs">
                    {filtered.length} / {heroes.length}
                </span>
            </div>

            <div className="relative">
                <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2" />
                <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Buscar..." className="h-8 pl-8 text-sm" />
            </div>

            <div className="flex flex-wrap gap-1">
                {factions.map((faction) => {
                    const code = faction.code as FactionCode;
                    const theme = FACTION_THEME[code];
                    const active = factionFilter.has(faction.id);
                    return (
                        <button
                            key={faction.id}
                            type="button"
                            onClick={() => toggle(factionFilter, faction.id, setFactionFilter)}
                            className={cn(
                                'min-h-7 rounded-full border px-2 py-1 text-[10px] font-medium transition-colors lg:py-0.5',
                                active
                                    ? cn(theme.cardGradient, theme.cardBorder, 'text-foreground')
                                    : 'border-border text-muted-foreground hover:border-foreground/30',
                            )}
                        >
                            {faction.name_pt}
                        </button>
                    );
                })}
            </div>

            <div className="flex flex-wrap gap-1">
                {heroClasses.map((heroClass) => {
                    const Icon = CLASS_ICON[heroClass.code as ClassCode];
                    const active = classFilter.has(heroClass.id);
                    return (
                        <button
                            key={heroClass.id}
                            type="button"
                            onClick={() => toggle(classFilter, heroClass.id, setClassFilter)}
                            className={cn(
                                'flex min-h-7 items-center gap-1 rounded-full border px-2 py-1 text-[10px] font-medium transition-colors lg:py-0.5',
                                active
                                    ? 'border-primary bg-primary text-primary-foreground'
                                    : 'border-border text-muted-foreground hover:border-foreground/30',
                            )}
                        >
                            <Icon className="size-2.5" />
                            {heroClass.name_pt}
                        </button>
                    );
                })}
            </div>

            <div className="grid flex-1 auto-rows-max grid-cols-[repeat(auto-fill,minmax(64px,1fr))] gap-1.5 overflow-y-auto pt-1">
                {filtered.map((hero) => {
                    const faction = factionById[hero.faction_id];
                    const klass = classById[hero.hero_class_id];
                    const tier = tierById[hero.tier_id];
                    const disabled = enforceUnique && placedHeroIds.has(hero.id);

                    return (
                        <DraggableChip
                            key={hero.id}
                            hero={hero}
                            factionCode={faction.code as FactionCode}
                            classCode={klass.code as ClassCode}
                            tierCode={tier.code as TierCode}
                            disabled={disabled}
                            onTap={onTapHero ? () => onTapHero(hero.id) : undefined}
                        />
                    );
                })}
            </div>
        </div>
    );
}
