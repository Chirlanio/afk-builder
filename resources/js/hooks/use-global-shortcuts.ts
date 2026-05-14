import { router } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

export interface ShortcutBinding {
    /** Combo final mostrado no help: ex. "g h", "?", "esc". */
    combo: string;
    /** Categoria pra agrupar visualmente no modal. */
    group: 'Navegação' | 'Geral';
    /** Descrição curta exibida no help. */
    description: string;
    /** Ação ao disparar — string = router.visit, função = custom. */
    action: string | (() => void);
}

/**
 * Bindings declarativos. Mudar aqui propaga pro hook + pro help modal automaticamente.
 */
export const SHORTCUTS: ShortcutBinding[] = [
    { combo: 'g d', group: 'Navegação', description: 'Ir para Dashboard', action: '/dashboard' },
    { combo: 'g h', group: 'Navegação', description: 'Ir para Heróis', action: '/heroes' },
    { combo: 'g u', group: 'Navegação', description: 'Ir para Uso de heróis', action: '/heroes/usage' },
    { combo: 'g f', group: 'Navegação', description: 'Ir para Formações', action: '/formations' },
    { combo: 'g c', group: 'Navegação', description: 'Ir para Comps prontos', action: '/formations/recommended' },
    { combo: 'g t', group: 'Navegação', description: 'Ir para Tier Lists', action: '/tier-lists' },
    { combo: 'g g', group: 'Navegação', description: 'Ir para Guias', action: '/guias' },
    { combo: 'g s', group: 'Geral', description: 'Abrir Configurações', action: '/settings/profile' },
];

const LEADER_KEY = 'g';
const LEADER_TIMEOUT_MS = 1500;

interface UseGlobalShortcutsOptions {
    onOpenHelp: () => void;
    onOpenSearch: () => void;
    /** Quando true, o hook não responde a eventos (ex: modal de help já aberto). */
    disabled?: boolean;
}

/**
 * Registra listener global de keydown que implementa atalhos estilo Linear/GitHub:
 * - Leader key `G` seguida de letra (h/f/t/d/etc) navega via router.visit
 * - `?` abre modal de ajuda
 * - Cmd+K / Ctrl+K abre o search global (já existente; aqui repassa pro callback)
 *
 * Ignora eventos quando o foco está em input/textarea/contenteditable — não rouba
 * tecla `g` enquanto user digita.
 */
export function useGlobalShortcuts({ onOpenHelp, onOpenSearch, disabled }: UseGlobalShortcutsOptions) {
    const leaderRef = useRef<{ active: boolean; timeoutId: number | null }>({
        active: false,
        timeoutId: null,
    });

    useEffect(() => {
        if (disabled) return;

        const isTypingInForm = (target: EventTarget | null): boolean => {
            if (!(target instanceof HTMLElement)) return false;
            const tag = target.tagName.toLowerCase();
            if (tag === 'input' || tag === 'textarea' || tag === 'select') return true;
            if (target.isContentEditable) return true;
            return false;
        };

        const clearLeader = () => {
            leaderRef.current.active = false;
            if (leaderRef.current.timeoutId !== null) {
                window.clearTimeout(leaderRef.current.timeoutId);
                leaderRef.current.timeoutId = null;
            }
        };

        const handler = (e: KeyboardEvent) => {
            // Permite Cmd+K / Ctrl+K em qualquer lugar (já tratado pelo AppSidebarLayout,
            // este branch é defensivo caso o hook seja usado isolado).
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                onOpenSearch();
                return;
            }

            // Ignora atalhos com modifier — não queremos confundir com shortcut do browser
            if (e.metaKey || e.ctrlKey || e.altKey) return;

            if (isTypingInForm(e.target)) return;

            const key = e.key.toLowerCase();

            // `?` (Shift+/) abre o help modal
            if (key === '?') {
                e.preventDefault();
                onOpenHelp();
                clearLeader();
                return;
            }

            // Esperando segunda tecla do leader G+letra
            if (leaderRef.current.active) {
                const combo = `${LEADER_KEY} ${key}`;
                const binding = SHORTCUTS.find((s) => s.combo === combo);
                clearLeader();
                if (binding) {
                    e.preventDefault();
                    if (typeof binding.action === 'string') {
                        router.visit(binding.action);
                    } else {
                        binding.action();
                    }
                }
                return;
            }

            // Acionando o leader
            if (key === LEADER_KEY) {
                e.preventDefault();
                leaderRef.current.active = true;
                leaderRef.current.timeoutId = window.setTimeout(clearLeader, LEADER_TIMEOUT_MS);
            }
        };

        window.addEventListener('keydown', handler);
        return () => {
            window.removeEventListener('keydown', handler);
            clearLeader();
        };
    }, [onOpenHelp, onOpenSearch, disabled]);
}
