import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { GlobalSearchDialog } from '@/components/global-search-dialog';
import { KeyboardShortcutsHelp } from '@/components/keyboard-shortcuts-help';
import { useGlobalShortcuts } from '@/hooks/use-global-shortcuts';
import { type BreadcrumbItem } from '@/types';
import { useState } from 'react';

export default function AppSidebarLayout({ children, breadcrumbs = [] }: { children: React.ReactNode; breadcrumbs?: BreadcrumbItem[] }) {
    const [searchOpen, setSearchOpen] = useState(false);
    const [helpOpen, setHelpOpen] = useState(false);

    // Hook centraliza Cmd+K (search), `?` (help) e leader G+letra (nav).
    // Desabilitado quando search ou help estão abertos pra não roubar tecla `g` lá dentro.
    useGlobalShortcuts({
        onOpenSearch: () => setSearchOpen(true),
        onOpenHelp: () => setHelpOpen(true),
        disabled: searchOpen || helpOpen,
    });

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar">
                <AppSidebarHeader breadcrumbs={breadcrumbs} onOpenSearch={() => setSearchOpen(true)} onOpenHelp={() => setHelpOpen(true)} />
                {children}
            </AppContent>
            <GlobalSearchDialog open={searchOpen} onOpenChange={setSearchOpen} />
            <KeyboardShortcutsHelp open={helpOpen} onOpenChange={setHelpOpen} />
        </AppShell>
    );
}
