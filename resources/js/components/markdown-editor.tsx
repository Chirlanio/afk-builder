import { MarkdownContent } from '@/components/markdown-content';
import { cn } from '@/lib/utils';
import { Eye, Pencil } from 'lucide-react';
import { useState } from 'react';

interface Props {
    value: string;
    onChange: (next: string) => void;
    maxLength?: number;
    placeholder?: string;
    /** Altura mínima do textarea/preview. Default `min-h-[120px]`. */
    minHeight?: string;
    /** Renderiza compacto (sem cabeçalho com label, só tabs + textarea). */
    compact?: boolean;
    /** Label opcional acima do editor. */
    label?: React.ReactNode;
    /** Ajuste de tamanho do texto. Default `text-xs` (compacto). */
    textSize?: 'xs' | 'sm';
    /** Quando true, o textarea/preview usa flex-1 + min-h-0 (preenche o container).
     *  Útil dentro de Sheet/Modal de altura total. */
    fillHeight?: boolean;
}

/**
 * Editor inline com tabs Editar/Preview. Reusável dentro de modais e sheets.
 * Mais simples que FormationNotesSheet — não cria seu próprio container; pode ser
 * encaixado em qualquer layout.
 */
export function MarkdownEditor({
    value,
    onChange,
    maxLength = 1000,
    placeholder,
    minHeight = 'min-h-[120px]',
    compact = false,
    label,
    textSize = 'xs',
    fillHeight = false,
}: Props) {
    const [mode, setMode] = useState<'edit' | 'preview'>('edit');
    const charCount = value.length;
    const isOverLimit = charCount > maxLength;
    const textSizeClass = textSize === 'sm' ? 'text-sm' : 'text-xs';
    const containerClass = fillHeight ? 'flex min-h-0 flex-1 flex-col gap-1.5 overflow-hidden' : 'flex flex-col gap-1.5';
    const fieldClass = fillHeight ? 'min-h-0 flex-1' : minHeight;

    return (
        <div className={containerClass}>
            {label && <div className={cn('text-muted-foreground', textSizeClass)}>{label}</div>}

            <div className="bg-muted/30 flex items-center gap-1 rounded-md border p-0.5">
                <button
                    type="button"
                    onClick={() => setMode('edit')}
                    className={cn(
                        'flex flex-1 items-center justify-center gap-1.5 rounded px-2 py-1 text-[11px] font-medium transition-colors',
                        mode === 'edit' ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground',
                    )}
                    aria-pressed={mode === 'edit'}
                >
                    <Pencil className="size-2.5" />
                    Editar
                </button>
                <button
                    type="button"
                    onClick={() => setMode('preview')}
                    className={cn(
                        'flex flex-1 items-center justify-center gap-1.5 rounded px-2 py-1 text-[11px] font-medium transition-colors',
                        mode === 'preview' ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground',
                    )}
                    aria-pressed={mode === 'preview'}
                >
                    <Eye className="size-2.5" />
                    Preview
                </button>
            </div>

            {mode === 'edit' ? (
                <textarea
                    value={value}
                    onChange={(e) => onChange(e.target.value)}
                    placeholder={placeholder}
                    maxLength={maxLength + 200}
                    className={cn(
                        'bg-background focus:ring-ring resize-none rounded-md border px-2 py-1.5 font-mono leading-relaxed focus:ring-2 focus:outline-none',
                        textSizeClass,
                        fieldClass,
                        isOverLimit && 'border-destructive',
                    )}
                    aria-invalid={isOverLimit}
                />
            ) : (
                <div className={cn('bg-background overflow-y-auto rounded-md border px-2 py-1.5', fieldClass)}>
                    {value.trim().length === 0 ? (
                        <p className={cn('text-muted-foreground italic', textSizeClass)}>Sem conteúdo. Volte para "Editar" para escrever.</p>
                    ) : (
                        <MarkdownContent>{value}</MarkdownContent>
                    )}
                </div>
            )}

            {!compact && (
                <div
                    className={cn(
                        'text-right tabular-nums',
                        textSize === 'sm' ? 'text-xs' : 'text-[10px]',
                        isOverLimit ? 'text-destructive font-semibold' : 'text-muted-foreground',
                    )}
                >
                    {charCount.toLocaleString('pt-BR')} / {maxLength.toLocaleString('pt-BR')}
                    {isOverLimit && <span className="ml-2">— acima do limite</span>}
                </div>
            )}
        </div>
    );
}
