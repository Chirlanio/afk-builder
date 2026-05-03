import { AlertTriangle } from 'lucide-react';
import { useCallback, useState, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';

export interface ConfirmOptions {
    title: string;
    description?: ReactNode;
    confirmLabel?: string;
    cancelLabel?: string;
    variant?: 'default' | 'destructive';
}

interface ConfirmState extends ConfirmOptions {
    resolve: (value: boolean) => void;
}

/**
 * Substituto async/await para `window.confirm()` com UI consistente.
 *
 * Uso:
 *   const { confirm, dialog } = useConfirm();
 *   const ok = await confirm({ title: '...', description: '...', variant: 'destructive' });
 *   if (ok) { ... }
 *
 * Renderize `{dialog}` em algum lugar do JSX do componente.
 */
export function useConfirm() {
    const [state, setState] = useState<ConfirmState | null>(null);

    const confirm = useCallback((opts: ConfirmOptions): Promise<boolean> => {
        return new Promise((resolve) => {
            setState({ ...opts, resolve });
        });
    }, []);

    const close = useCallback((value: boolean) => {
        setState((current) => {
            current?.resolve(value);
            return null;
        });
    }, []);

    const isDestructive = state?.variant === 'destructive';

    const dialog = (
        <Dialog open={state !== null} onOpenChange={(open) => !open && close(false)}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        {isDestructive && <AlertTriangle className="size-5 text-destructive" />}
                        {state?.title}
                    </DialogTitle>
                    {state?.description && (
                        <DialogDescription asChild>
                            <div className="text-sm text-muted-foreground">{state.description}</div>
                        </DialogDescription>
                    )}
                </DialogHeader>
                <DialogFooter className="gap-2 sm:gap-0">
                    <Button type="button" variant="outline" onClick={() => close(false)}>
                        {state?.cancelLabel ?? 'Cancelar'}
                    </Button>
                    <Button
                        type="button"
                        variant={isDestructive ? 'destructive' : 'default'}
                        onClick={() => close(true)}
                    >
                        {state?.confirmLabel ?? 'Confirmar'}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );

    return { confirm, dialog };
}
