import { useEffect, useState } from 'react';
import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { GlobalSearchDialog } from '@/components/global-search-dialog';
import { type BreadcrumbItem } from '@/types';

export default function AppSidebarLayout({ children, breadcrumbs = [] }: { children: React.ReactNode; breadcrumbs?: BreadcrumbItem[] }) {
    const [searchOpen, setSearchOpen] = useState(false);

    // Atalho global Cmd+K / Ctrl+K abre o GlobalSearchDialog em qualquer rota.
    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setSearchOpen(true);
            }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, []);

    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent variant="sidebar">
                <AppSidebarHeader breadcrumbs={breadcrumbs} onOpenSearch={() => setSearchOpen(true)} />
                {children}
            </AppContent>
            <GlobalSearchDialog open={searchOpen} onOpenChange={setSearchOpen} />
        </AppShell>
    );
}
