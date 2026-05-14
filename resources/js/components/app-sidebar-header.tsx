import { Search } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { type BreadcrumbItem as BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
    onOpenSearch?: () => void;
}

const isMac = typeof navigator !== 'undefined' && /Mac|iPhone|iPad|iPod/.test(navigator.platform);

export function AppSidebarHeader({ breadcrumbs = [], onOpenSearch }: Props) {
    return (
        <header className="border-sidebar-border/50 flex h-16 shrink-0 items-center gap-2 border-b px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex flex-1 items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>
            {onOpenSearch && (
                <button
                    type="button"
                    onClick={onOpenSearch}
                    className="flex items-center gap-2 rounded-md border bg-background px-3 py-1.5 text-xs text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                    aria-label="Abrir busca global (atalho ⌘K)"
                >
                    <Search className="size-3.5" />
                    <span className="hidden sm:inline">Buscar…</span>
                    <kbd className="hidden rounded border bg-muted px-1.5 py-0.5 font-mono text-[10px] md:inline">
                        {isMac ? '⌘' : 'Ctrl'} K
                    </kbd>
                </button>
            )}
        </header>
    );
}
