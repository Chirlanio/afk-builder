import { CLASS_ICON, FACTION_THEME, TIER_THEME } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { ClassCode, FactionCode, Hero, TierCode } from '@/types/afk';
import { Flame } from 'lucide-react';

interface HeroChipProps {
    hero: Hero;
    factionCode: FactionCode;
    classCode: ClassCode;
    tierCode: TierCode;
    size?: 'sm' | 'md' | 'lg';
    dimmed?: boolean;
    className?: string;
}

/**
 * Compact, square hero portrait used inside slots and inside the pool.
 * Pure presentational — drag/drop wiring lives in the wrapper components.
 */
export function HeroChip({ hero, factionCode, classCode, tierCode, size = 'md', dimmed, className }: HeroChipProps) {
    const factionTheme = FACTION_THEME[factionCode];
    const tierTheme = TIER_THEME[tierCode];
    const ClassIconComponent = CLASS_ICON[classCode];
    const awakened = hero.is_awakened;

    const sizeClasses = {
        sm: 'h-16 w-16 text-[9px]',
        md: 'h-20 w-20 text-[10px]',
        lg: 'h-24 w-24 text-xs',
    }[size];

    return (
        <div
            className={cn(
                'relative flex flex-col overflow-hidden rounded-lg border-2 transition-opacity',
                sizeClasses,
                factionTheme.cardGradient,
                awakened ? 'border-amber-400/90 shadow-[0_0_10px_-2px_rgba(251,191,36,0.55)]' : factionTheme.cardBorder,
                dimmed && 'opacity-30 grayscale',
                className,
            )}
            title={`${hero.name}${hero.title ? ` — ${hero.title}` : ''}`}
        >
            <div className={cn('bg-background/80 absolute top-0.5 right-0.5 z-10 rounded-full p-0.5', factionTheme.accent)}>
                <ClassIconComponent className="size-2.5" />
            </div>

            {awakened && (
                <div className="absolute top-0.5 left-0.5 z-10 rounded bg-gradient-to-r from-rose-500 to-amber-400 p-0.5 text-white">
                    <Flame className="size-2.5" />
                </div>
            )}

            {!awakened && (
                <div className="absolute top-0.5 left-0.5 z-10">
                    <span className={cn('rounded px-1 py-px text-[8px] font-bold uppercase', tierTheme.badge)}>{tierTheme.label.slice(0, 3)}</span>
                </div>
            )}

            <div className="relative flex flex-1 items-end justify-center overflow-hidden">
                {hero.portrait_url ? (
                    <img
                        src={hero.portrait_url}
                        alt={hero.name}
                        loading="lazy"
                        draggable={false}
                        onError={(e) => {
                            (e.currentTarget as HTMLImageElement).style.display = 'none';
                        }}
                        className="size-full object-contain object-bottom"
                    />
                ) : null}
            </div>

            <div className="bg-background/70 truncate px-0.5 text-center leading-tight font-medium">{hero.name}</div>
        </div>
    );
}
