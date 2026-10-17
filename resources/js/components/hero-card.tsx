import { EngravingBadge } from '@/components/engraving-badge';
import { Badge } from '@/components/ui/badge';
import { CLASS_ICON, CLASS_LABEL, FACTION_THEME, TIER_THEME, tierFromAscension } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { ClassCode, FactionCode, Hero, TierCode, UserHero } from '@/types/afk';
import { Check, CheckCircle2, Flame } from 'lucide-react';

interface HeroCardProps {
    hero: Hero;
    factionCode: FactionCode;
    classCode: ClassCode;
    tierCode: TierCode;
    userHero?: UserHero;
    onClick: () => void;
    /** Quando true, o card atua como selecionável: click vira toggle de seleção e mostra checkbox overlay. */
    selectionMode?: boolean;
    isSelected?: boolean;
    onToggleSelect?: () => void;
}

export function HeroCard({
    hero,
    factionCode,
    classCode,
    tierCode,
    userHero,
    onClick,
    selectionMode = false,
    isSelected = false,
    onToggleSelect,
}: HeroCardProps) {
    const factionTheme = FACTION_THEME[factionCode];
    // Quando o herói está no roster, mostramos o tier ATINGIDO (baseado na
    // ascensão atual). Sem roster, mostramos o max potencial do herói.
    const displayTierCode = userHero ? tierFromAscension(userHero.ascension_value) : tierCode;
    const tierTheme = TIER_THEME[displayTierCode];
    const ClassIconComponent = CLASS_ICON[classCode];

    const owned = userHero !== undefined;
    const awakened = hero.is_awakened;

    const handleClick = selectionMode && onToggleSelect ? onToggleSelect : onClick;

    return (
        <button
            type="button"
            onClick={handleClick}
            className={cn(
                'group relative flex w-full flex-col overflow-hidden rounded-xl transition-all duration-200',
                factionTheme.cardGradient,
                awakened
                    ? 'border-[3px] border-amber-400/90 shadow-[0_0_18px_-2px_rgba(251,191,36,0.55)] hover:shadow-[0_0_24px_-1px_rgba(251,191,36,0.7)]'
                    : cn('border-2', factionTheme.cardBorder, factionTheme.cardHoverGlow, 'hover:shadow-lg'),
                'hover:scale-[1.03]',
                owned ? 'ring-offset-background ring-2 ring-emerald-500/70 ring-offset-1' : 'opacity-90 hover:opacity-100',
                selectionMode && isSelected && 'ring-primary ring-offset-background scale-[1.02] ring-4 ring-offset-2',
                selectionMode && !isSelected && 'opacity-70',
            )}
            aria-label={`${hero.name}${hero.title ? ` — ${hero.title}` : ''}${awakened ? ' (despertado)' : ''}`}
            aria-pressed={selectionMode ? isSelected : undefined}
        >
            {/* Checkbox overlay no modo seleção — substitui visualmente o feedback de owned */}
            {selectionMode && (
                <div
                    className={cn(
                        'absolute top-1.5 right-1.5 z-20 grid size-6 place-items-center rounded-full border-2 transition-colors',
                        isSelected ? 'border-primary bg-primary text-primary-foreground' : 'border-white/60 bg-black/50 text-transparent',
                    )}
                    aria-hidden
                >
                    <Check className="size-3.5" />
                </div>
            )}
            <div className="absolute top-1.5 left-1.5 z-10">
                {awakened ? (
                    <span className="flex items-center gap-1 rounded-md bg-gradient-to-r from-rose-500 via-amber-400 to-rose-500 px-1.5 py-0.5 text-[10px] font-bold tracking-wide text-white uppercase shadow-md">
                        <Flame className="size-3" />
                        Desperto
                    </span>
                ) : (
                    <span className={cn('rounded-md px-1.5 py-0.5 text-[10px] font-bold tracking-wide uppercase', tierTheme.badge)}>
                        {tierTheme.label}
                    </span>
                )}
            </div>

            <div className={cn('bg-background/80 absolute top-1.5 right-1.5 z-10 rounded-full p-1', factionTheme.accent)}>
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
                    <div className="text-muted-foreground flex size-full items-center justify-center text-xs">sem imagem</div>
                )}
            </div>

            <div className="flex flex-col gap-0.5 px-2 py-2 text-left">
                <div
                    className={cn(
                        'truncate text-sm leading-tight font-semibold',
                        awakened && 'bg-gradient-to-r from-amber-400 via-orange-400 to-rose-500 bg-clip-text text-transparent',
                    )}
                >
                    {hero.name}
                </div>
                {hero.title && <div className="text-muted-foreground truncate text-[11px] leading-tight">{hero.title}</div>}
                {owned && userHero && (
                    <div className="mt-1 flex flex-wrap items-center gap-1">
                        <Badge variant="secondary" className="h-5 px-1.5 text-[10px]">
                            Nv {userHero.level}
                        </Badge>
                        {userHero.signature_item > 0 && (
                            <Badge variant="outline" className="h-5 px-1.5 text-[10px]" title="Assinatura">
                                AS {userHero.signature_item}
                            </Badge>
                        )}
                        <EngravingBadge engraving={userHero.engraving} size="sm" />
                    </div>
                )}
            </div>
        </button>
    );
}
