import { CheckCircle2, Flame } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { CLASS_ICON, CLASS_LABEL, FACTION_THEME, TIER_THEME } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { ClassCode, FactionCode, Hero, TierCode, UserHero } from '@/types/afk';

interface HeroCardProps {
    hero: Hero;
    factionCode: FactionCode;
    classCode: ClassCode;
    tierCode: TierCode;
    userHero?: UserHero;
    onClick: () => void;
}

export function HeroCard({ hero, factionCode, classCode, tierCode, userHero, onClick }: HeroCardProps) {
    const factionTheme = FACTION_THEME[factionCode];
    const tierTheme = TIER_THEME[tierCode];
    const ClassIconComponent = CLASS_ICON[classCode];

    const owned = userHero !== undefined;
    const awakened = hero.is_awakened;

    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'group relative flex w-full flex-col overflow-hidden rounded-xl transition-all duration-200',
                factionTheme.cardGradient,
                awakened
                    ? 'border-[3px] border-amber-400/90 shadow-[0_0_18px_-2px_rgba(251,191,36,0.55)] hover:shadow-[0_0_24px_-1px_rgba(251,191,36,0.7)]'
                    : cn('border-2', factionTheme.cardBorder, factionTheme.cardHoverGlow, 'hover:shadow-lg'),
                'hover:scale-[1.03]',
                owned ? 'ring-2 ring-emerald-500/70 ring-offset-1 ring-offset-background' : 'opacity-90 hover:opacity-100',
            )}
            aria-label={`${hero.name}${hero.title ? ` — ${hero.title}` : ''}${awakened ? ' (despertado)' : ''}`}
        >
            <div className="absolute top-1.5 left-1.5 z-10">
                {awakened ? (
                    <span className="flex items-center gap-1 rounded-md bg-gradient-to-r from-rose-500 via-amber-400 to-rose-500 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white shadow-md">
                        <Flame className="size-3" />
                        Desperto
                    </span>
                ) : (
                    <span className={cn('rounded-md px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide', tierTheme.badge)}>
                        {tierTheme.label}
                    </span>
                )}
            </div>

            <div className={cn('absolute top-1.5 right-1.5 z-10 rounded-full bg-background/80 p-1', factionTheme.accent)}>
                <ClassIconComponent className="size-3.5" aria-label={CLASS_LABEL[classCode]} />
            </div>

            {owned && (
                <div className="absolute right-1.5 bottom-12 z-10 rounded-full bg-emerald-500 p-1 text-white shadow-md">
                    <CheckCircle2 className="size-4" aria-label="No roster" />
                </div>
            )}

            {awakened && (
                <div
                    aria-hidden="true"
                    className="pointer-events-none absolute inset-0 z-[1] bg-gradient-to-t from-amber-500/20 via-transparent to-transparent"
                />
            )}

            <div className={cn('relative flex aspect-[3/4] w-full items-end justify-center overflow-hidden', factionTheme.cardGradient)}>
                {hero.portrait_url ? (
                    <img
                        src={hero.portrait_url}
                        alt={hero.name}
                        loading="lazy"
                        onError={(e) => {
                            (e.currentTarget as HTMLImageElement).style.display = 'none';
                        }}
                        className="size-full object-contain object-bottom transition-transform duration-300 group-hover:scale-105"
                    />
                ) : (
                    <div className="flex size-full items-center justify-center text-xs text-muted-foreground">
                        sem imagem
                    </div>
                )}
            </div>

            <div className="flex flex-col gap-0.5 px-2 py-2 text-left">
                <div
                    className={cn(
                        'truncate text-sm font-semibold leading-tight',
                        awakened && 'bg-gradient-to-r from-amber-400 via-orange-400 to-rose-500 bg-clip-text text-transparent',
                    )}
                >
                    {hero.name}
                </div>
                {hero.title && (
                    <div className="truncate text-[11px] leading-tight text-muted-foreground">{hero.title}</div>
                )}
                {owned && userHero && (
                    <div className="mt-1 flex items-center gap-1">
                        <Badge variant="secondary" className="h-5 px-1.5 text-[10px]">
                            Nv {userHero.level}
                        </Badge>
                        {userHero.signature_item > 0 && (
                            <Badge variant="outline" className="h-5 px-1.5 text-[10px]" title="Assinatura">
                                AS {userHero.signature_item}
                            </Badge>
                        )}
                    </div>
                )}
            </div>
        </button>
    );
}
