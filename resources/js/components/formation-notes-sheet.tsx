import { Eye, FileText, Pencil } from 'lucide-react';
import { useState } from 'react';
import { MarkdownContent } from '@/components/markdown-content';
import { Button } from '@/components/ui/button';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { cn } from '@/lib/utils';

interface Props {
    value: string;
    maxLength?: number;
    onChange: (value: string) => void;
    /** Modo controlled: passar `open` + `onOpenChange` ao invés do trigger interno. */
    open?: boolean;
    onOpenChange?: (open: boolean) => void;
    /** Esconde o trigger interno (útil quando o open é controlado externamente). */
    hideTrigger?: boolean;
}

const PLACEHOLDER = `# Estratégia

- Tank na frente, controles atrás
- Trocar Skreg → Lyca se o oponente tiver Ezio

## Quando usar
Counter de comp Wilders top arena.

## Riscos
- DPS baixo contra boss; evitar Dream Realm.`;

/**
 * Sheet pra editar notas em markdown da formação. Lateral à direita com
 * 2 modos: "Editar" (textarea) e "Preview" (markdown renderizado).
 * Em mobile o sheet ocupa quase a tela toda.
 */
export function FormationNotesSheet({
    value,
    maxLength = 2000,
    onChange,
    open: openProp,
    onOpenChange,
    hideTrigger,
}: Props) {
    const [internalOpen, setInternalOpen] = useState(false);
    const open = openProp ?? internalOpen;
    const setOpen = onOpenChange ?? setInternalOpen;
    const [mode, setMode] = useState<'edit' | 'preview'>('edit');
    const charCount = value.length;
    const isOverLimit = charCount > maxLength;
    const hasNotes = value.trim().length > 0;

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            {!hideTrigger && (
                <SheetTrigger asChild>
                    <Button variant="outline" size="sm" className="gap-1.5">
                        <FileText className="size-3.5" />
                        Notas
                        {hasNotes && <span className="ml-1 size-1.5 rounded-full bg-amber-400" aria-hidden />}
                    </Button>
                </SheetTrigger>
            )}
            <SheetContent side="right" className="flex w-full flex-col sm:max-w-xl">
                <SheetHeader>
                    <SheetTitle className="flex items-center gap-2">
                        <FileText className="size-4" />
                        Notas da formação
                    </SheetTitle>
                    <SheetDescription>
                        Anote estratégia, contras, quando usar. Aceita markdown ({maxLength.toLocaleString('pt-BR')} caracteres). Visível na página pública quando você compartilhar.
                    </SheetDescription>
                </SheetHeader>

                <div className="flex items-center gap-1 rounded-md border bg-muted/30 p-1">
                    <button
                        type="button"
                        onClick={() => setMode('edit')}
                        className={cn(
                            'flex flex-1 items-center justify-center gap-1.5 rounded px-3 py-1.5 text-xs font-medium transition-colors',
                            mode === 'edit' ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground',
                        )}
                        aria-pressed={mode === 'edit'}
                    >
                        <Pencil className="size-3" />
                        Editar
                    </button>
                    <button
                        type="button"
                        onClick={() => setMode('preview')}
                        className={cn(
                            'flex flex-1 items-center justify-center gap-1.5 rounded px-3 py-1.5 text-xs font-medium transition-colors',
                            mode === 'preview' ? 'bg-background shadow-sm' : 'text-muted-foreground hover:text-foreground',
                        )}
                        aria-pressed={mode === 'preview'}
                    >
                        <Eye className="size-3" />
                        Preview
                    </button>
                </div>

                <div className="flex min-h-0 flex-1 flex-col gap-2 overflow-hidden">
                    {mode === 'edit' ? (
                        <textarea
                            value={value}
                            onChange={(e) => onChange(e.target.value)}
                            placeholder={PLACEHOLDER}
                            className={cn(
                                'min-h-0 flex-1 resize-none rounded-md border bg-background p-3 font-mono text-xs leading-relaxed focus:outline-none focus:ring-2 focus:ring-ring',
                                isOverLimit && 'border-destructive',
                            )}
                            aria-invalid={isOverLimit}
                            aria-describedby="formation-notes-counter"
                        />
                    ) : (
                        <div className="min-h-0 flex-1 overflow-y-auto rounded-md border bg-background p-3">
                            {value.trim().length === 0 ? (
                                <p className="text-sm text-muted-foreground italic">Sem conteúdo. Escreva alguma coisa na aba "Editar".</p>
                            ) : (
                                <MarkdownContent>{value}</MarkdownContent>
                            )}
                        </div>
                    )}

                    <div
                        id="formation-notes-counter"
                        className={cn(
                            'shrink-0 text-right text-[11px] tabular-nums',
                            isOverLimit ? 'font-semibold text-destructive' : 'text-muted-foreground',
                        )}
                    >
                        {charCount.toLocaleString('pt-BR')} / {maxLength.toLocaleString('pt-BR')}
                        {isOverLimit && <span className="ml-2">— acima do limite</span>}
                    </div>
                </div>

                <SheetFooter className="mt-2">
                    <SheetClose asChild>
                        <Button variant="default" size="sm">Fechar</Button>
                    </SheetClose>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
