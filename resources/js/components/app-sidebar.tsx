import { LocaleSwitcher } from '@/components/locale-switcher';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem, type SharedData } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { BookOpen, Folder, LayoutGrid, Library, Package, PawPrint, RefreshCw, ShieldCheck, Sparkles, Swords, Users } from 'lucide-react';
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
    const { auth } = usePage<SharedData>().props;
    const mainNavItems = auth.user?.is_admin ? [...baseNavItems, ...adminNavItems] : baseNavItems;

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
