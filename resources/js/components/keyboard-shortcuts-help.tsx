import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { SHORTCUTS, type ShortcutBinding } from '@/hooks/use-global-shortcuts';
import { Keyboard } from 'lucide-react';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

/**
 * Lista todos os atalhos disponíveis agrupados por categoria. Disparado por `?`.
 * Inclui também os atalhos não navegacionais (search, help) com hardcode aqui pra
 * mostrar pro user — não estão na lista SHORTCUTS porque são gerenciados em outros
 * lugares (Cmd+K no AppSidebarLayout, `?` no hook).
 */
export function KeyboardShortcutsHelp({ open, onOpenChange }: Props) {
    const navItems = SHORTCUTS.filter((s) => s.group === 'Navegação');
    const generalItems = SHORTCUTS.filter((s) => s.group === 'Geral');

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Keyboard className="size-4" />
                        Atalhos de teclado
                    </DialogTitle>
                    <DialogDescription>Pressione as teclas em sequência (não simultaneamente). `G` é a leader key.</DialogDescription>
                </DialogHeader>

                <div className="flex flex-col gap-4">
                    <ShortcutGroup title="Navegação" items={navItems} />
                    <ShortcutGroup
                        title="Geral"
                        items={[
                            ...generalItems,
                            { combo: 'cmd k / ctrl k', group: 'Geral', description: 'Busca global', action: () => {} },
                            { combo: '?', group: 'Geral', description: 'Abrir este painel', action: () => {} },
                            { combo: 'esc', group: 'Geral', description: 'Fechar modais e overlays', action: () => {} },
                        ]}
                    />
                </div>

                <p className="text-muted-foreground text-[11px]">Atalhos são desativados enquanto você está digitando em um campo de texto.</p>
            </DialogContent>
        </Dialog>
    );
}

function ShortcutGroup({ title, items }: { title: string; items: ShortcutBinding[] }) {
    return (
        <div>
            <h3 className="text-muted-foreground mb-2 text-[11px] font-semibold tracking-wide uppercase">{title}</h3>
            <div className="bg-card flex flex-col divide-y rounded-lg border">
                {items.map((item) => (
                    <div key={item.combo} className="flex items-center justify-between gap-3 px-3 py-2 text-sm">
                        <span>{item.description}</span>
                        <ComboKbd combo={item.combo} />
                    </div>
                ))}
            </div>
        </div>
    );
}

function ComboKbd({ combo }: { combo: string }) {
    // Separa por whitespace pra renderizar cada tecla como `<kbd>` distinto: "g h" → [g][h]
    const keys = combo.split(/\s+/);
    return (
        <span className="flex items-center gap-1">
            {keys.map((key, i) => (
                <span key={i} className="flex items-center gap-1">
                    {i > 0 && <span className="text-muted-foreground text-[10px]">depois</span>}
                    <kbd className="bg-muted rounded border px-1.5 py-0.5 font-mono text-[10px] uppercase">{key}</kbd>
                </span>
            ))}
        </span>
    );
}
