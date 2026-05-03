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
    children?: ReactNode;
}

/**
 * Slot quadrado estilo paperdoll: imagem do item dominando o slot, ícone discreto no canto.
 * Empty state mostra ícone placeholder centralizado.
 *
 * Tamanho típico: ~96-112px (configurado pelo container).
 */
export function HeroSlotCard({ icon: Icon, label, empty = false, imageUrl, valueText, detail, accentClass, onClick, className, children }: SlotCardProps) {
    const Wrapper = onClick ? 'button' : 'div';

    return (
        <Wrapper
            type={onClick ? 'button' : undefined}
            onClick={onClick}
            title={label}
            className={cn(
                'group relative flex aspect-square w-full flex-col items-center justify-center overflow-hidden rounded-md border-2 bg-card text-center transition-all',
                empty
                    ? 'border-dashed border-muted-foreground/30 bg-muted/20 text-muted-foreground'
                    : 'border-foreground/15 shadow-sm',
                onClick && 'cursor-pointer hover:scale-[1.03] hover:border-foreground/50',
                accentClass,
                className,
            )}
        >
            {/* Ícone discreto no canto superior esquerdo */}
            <Icon className="absolute left-0.5 top-0.5 size-3 text-muted-foreground/70" />

            {/* Conteúdo central */}
            {empty ? (
                <Icon className="size-7 opacity-30" />
            ) : imageUrl ? (
                <div className="flex size-full flex-col items-center justify-center gap-0.5 p-1.5">
                    <img src={imageUrl} alt={label} className="max-h-[70%] w-auto object-contain" />
                    {detail && <div className="text-[9px] leading-none">{detail}</div>}
                </div>
            ) : children ? (
                <div className="flex size-full flex-col items-center justify-center p-1">{children}</div>
            ) : (
                <div className="flex flex-col items-center justify-center gap-0.5 p-1">
                    {valueText !== undefined && (
                        <div className="text-base font-bold leading-none tabular-nums">{valueText}</div>
                    )}
                    {detail && <div className="text-[9px] leading-tight">{detail}</div>}
                </div>
            )}
        </Wrapper>
    );
}
