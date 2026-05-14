import { FileText } from 'lucide-react';
import { useState } from 'react';
import { MarkdownEditor } from '@/components/markdown-editor';
import { Button } from '@/components/ui/button';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';

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
 * Sheet pra editar notas em markdown da formação. Lateral à direita.
 * Reusa MarkdownEditor (Fase 40) — antes tinha lógica duplicada de edit/preview.
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

                <MarkdownEditor
                    value={value}
                    onChange={onChange}
                    maxLength={maxLength}
                    placeholder={PLACEHOLDER}
                    fillHeight
                    textSize="sm"
                />

                <SheetFooter className="mt-2">
                    <SheetClose asChild>
                        <Button variant="default" size="sm">Fechar</Button>
                    </SheetClose>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
