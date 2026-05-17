import { LocaleSwitcher } from '@/components/locale-switcher';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BarChart3, BookOpen, Compass, Folder, Gauge, Gem, LayoutGrid, Library, ListChecks, Package, PawPrint, RefreshCw, ShieldCheck, Sparkles, Swords, Users } from 'lucide-react';
import AppLogo from './app-logo';

const baseNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        url: '/dashboard',
        icon: LayoutGrid,
    },
    {
        title: 'Heróis',
        url: '/heroes',
        icon: Users,
    },
    {
        title: 'Uso de heróis',
        url: '/heroes/usage',
        icon: BarChart3,
    },
    {
        title: 'Análise do roster',
        url: '/roster/analytics',
        icon: Gauge,
    },
    {
        title: 'Planner de investimento',
        url: '/roster/planner',
        icon: Compass,
    },
    {
        title: 'Cristal Ressonante',
        url: '/cristal',
        icon: Gem,
    },
    {
        title: 'Formações',
        url: '/formations',
        icon: Swords,
    },
    {
        title: 'Comps prontos',
        url: '/formations/recommended',
        icon: Sparkles,
    },
    {
        title: 'Tier Lists',
        url: '/tier-lists',
        icon: ListChecks,
    },
    {
        title: 'Guias',
        url: '/guias',
        icon: Library,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Aprovar heróis',
        url: '/admin/heroes/pending',
        icon: ShieldCheck,
    },
    {
        title: 'Coleções',
        url: '/admin/collections',
        icon: Package,
    },
    {
        title: 'Beasts',
        url: '/admin/beasts',
        icon: PawPrint,
    },
    {
        title: 'Sincronizações',
        url: '/admin/sync',
        icon: RefreshCw,
    },
];

const footerNavItems: NavItem[] = [
    {
        title: 'Repositório',
        url: 'https://github.com/Chirlanio/afk-builder',
        icon: Folder,
    },
    {
        title: 'Documentação',
        url: 'https://github.com/Chirlanio/afk-builder/blob/main/docs/IMPLEMENTATION.md',
        icon: BookOpen,
    },
];

export function AppSidebar() {
    const { auth, admin } = usePage<SharedData>().props;
    const pendingCount = admin?.pending_heroes_count ?? null;

    // Decorar item "Aprovar heróis" com badge dinâmico baseado em shared data
    const decoratedAdminItems = adminNavItems.map((item) =>
        item.url === '/admin/heroes/pending'
            ? { ...item, badgeCount: pendingCount, badgeTone: 'rose' as const }
            : item,
    );

    const mainNavItems = auth.user?.is_admin ? [...baseNavItems, ...decoratedAdminItems] : baseNavItems;

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <div className="flex items-center justify-end px-2">
                    <LocaleSwitcher />
                </div>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
