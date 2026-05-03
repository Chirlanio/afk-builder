import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

interface SlotCardProps {
    icon: LucideIcon;
    label: string;
    empty?: boolean;
    children?: ReactNode;
    accentClass?: string;
    onClick?: () => void;
    className?: string;
}

/**
 * Card visual que representa um slot de equipamento/atributo do herói (estilo paperdoll).
 * Usado tanto na visualização (read-only) quanto na edição (com onClick interativo).
 */
export function HeroSlotCard({ icon: Icon, label, empty = false, children, accentClass, onClick, className }: SlotCardProps) {
    const Wrapper = onClick ? 'button' : 'div';

    return (
        <Wrapper
            type={onClick ? 'button' : undefined}
            onClick={onClick}
            className={cn(
                'flex min-h-[88px] flex-col gap-1 rounded-lg border bg-card p-2 text-left transition-colors',
                onClick && 'cursor-pointer hover:border-foreground/30 hover:bg-accent',
                empty && 'border-dashed bg-muted/30 text-muted-foreground',
                accentClass,
                className,
            )}
        >
            <div className="flex items-center gap-1.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                <Icon className="size-3" />
                <span>{label}</span>
            </div>
            <div className="flex flex-1 flex-col justify-center text-xs">
                {empty ? <span className="text-muted-foreground">vazio</span> : children}
            </div>
        </Wrapper>
    );
}
