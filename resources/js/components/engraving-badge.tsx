import { ENGRAVING_MILESTONE_BADGE, ENGRAVING_MILESTONE_LABEL, engravingMilestone } from '@/lib/afk';
import { cn } from '@/lib/utils';

interface EngravingBadgeProps {
    engraving: number | null | undefined;
    /** "xs" = ~9px texto, "sm" = 10px (default), "md" = 11px */
    size?: 'xs' | 'sm' | 'md';
    /** Mostra "E30" mesmo quando level está entre milestones (default true). */
    showLabel?: boolean;
    className?: string;
}

export function EngravingBadge({ engraving, size = 'sm', showLabel = true, className }: EngravingBadgeProps) {
    const milestone = engravingMilestone(engraving);
    if (!milestone) return null;

    const sizeClasses = {
        xs: 'h-4 px-1 text-[9px]',
        sm: 'h-5 px-1.5 text-[10px]',
        md: 'h-6 px-2 text-[11px]',
    } as const;

    return (
        <span
            className={cn(
                'inline-flex items-center rounded-md border font-bold uppercase tracking-wide',
                sizeClasses[size],
                ENGRAVING_MILESTONE_BADGE[milestone],
                className,
            )}
            title={`Inscrições ${engraving}`}
        >
            {showLabel ? ENGRAVING_MILESTONE_LABEL[milestone] : ''}
        </span>
    );
}
