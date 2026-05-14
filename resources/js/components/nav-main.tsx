import { SidebarGroup, SidebarGroupLabel, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types';
import { Link, usePage } from '@inertiajs/react';
import { cn } from '@/lib/utils';

export function NavMain({ items = [] }: { items: NavItem[] }) {
    const page = usePage();
    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>Platform</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) => {
                    const showBadge = typeof item.badgeCount === 'number' && item.badgeCount > 0;
                    const badgeTone = item.badgeTone ?? 'amber';
                    return (
                        <SidebarMenuItem key={item.title}>
                            <SidebarMenuButton asChild isActive={item.url === page.url}>
                                <Link href={item.url} prefetch>
                                    {item.icon && <item.icon />}
                                    <span className="flex-1">{item.title}</span>
                                    {showBadge && (
                                        <span
                                            className={cn(
                                                'ml-auto inline-flex min-w-[20px] items-center justify-center rounded-full px-1.5 text-[10px] font-semibold tabular-nums',
                                                badgeTone === 'rose'
                                                    ? 'bg-rose-500/20 text-rose-600 dark:text-rose-300'
                                                    : 'bg-amber-500/20 text-amber-600 dark:text-amber-300',
                                            )}
                                            aria-label={`${item.badgeCount} pendente${item.badgeCount === 1 ? '' : 's'}`}
                                        >
                                            {item.badgeCount}
                                        </span>
                                    )}
                                </Link>
                            </SidebarMenuButton>
                        </SidebarMenuItem>
                    );
                })}
            </SidebarMenu>
        </SidebarGroup>
    );
}
