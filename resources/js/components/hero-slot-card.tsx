import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

interface SlotCardProps {
    icon: LucideIcon;
    label: string;
    empty?: boolean;
    /** Imagem dominante quando preenchido (estilo equipamento). */
    imageUrl?: string | null;
    /** Texto compacto exibido no centro quando não há imagem (ex: "+40", "3/9"). */
    valueText?: ReactNode;
    /** Subtítulo / detalhe (estrelas, badges F3/F9, etc). */
    detail?: ReactNode;
    accentClass?: string;
    onClick?: () => void;
    className?: string;
}

/**
 * Slot quadrado estilo paperdoll: imagem do item dominando o slot, label sobreposta no topo.
 * Empty state mostra ícone placeholder + label.
 */
export function HeroSlotCard({ icon: Icon, label, empty = false, imageUrl, valueText, detail, accentClass, onClick, className }: SlotCardProps) {
    const Wrapper = onClick ? 'button' : 'div';

    return (
        <Wrapper
            type={onClick ? 'button' : undefined}
            onClick={onClick}
            className={cn(
                'group relative flex aspect-square w-full flex-col overflow-hidden rounded-lg border-2 bg-card text-left transition-all',
                empty
                    ? 'border-dashed border-muted-foreground/30 bg-muted/20 text-muted-foreground'
                    : 'border-foreground/10 shadow-sm',
                onClick && 'cursor-pointer hover:scale-[1.02] hover:border-foreground/40',
                accentClass,
                className,
            )}
        >
            {/* Label badge no topo */}
            <div className="absolute left-1 top-1 z-10 flex items-center gap-1 rounded bg-background/80 px-1.5 py-0.5 text-[9px] font-medium uppercase tracking-wide backdrop-blur-sm">
                <Icon className="size-3" />
                <span>{label}</span>
            </div>

            {/* Conteúdo */}
            <div className="flex size-full flex-col items-center justify-center gap-1 p-1">
                {empty ? (
                    <div className="flex flex-col items-center gap-1 opacity-40">
                        <Icon className="size-8" />
                    </div>
                ) : imageUrl ? (
                    <>
                        <img src={imageUrl} alt={label} className="max-h-[60%] w-auto object-contain" />
                        {detail && <div className="text-[10px] leading-tight">{detail}</div>}
                    </>
                ) : (
                    <>
                        {valueText !== undefined && (
                            <div className="text-xl font-bold leading-none tabular-nums">{valueText}</div>
                        )}
                        {detail && <div className="text-[10px] leading-tight">{detail}</div>}
                    </>
                )}
            </div>
        </Wrapper>
    );
}
