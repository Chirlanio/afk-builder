import { Link, router, usePage } from '@inertiajs/react';
import { Check, ChevronsUpDown, Plus, User as UserIcon } from 'lucide-react';

import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type SharedData } from '@/types';

export function AccountSwitcher() {
    const { gameAccount } = usePage<SharedData>().props;

    if (!gameAccount || gameAccount.accounts.length === 0) {
        return null;
    }

    const { active, accounts } = gameAccount;
    const hasMultiple = accounts.length > 1;

    const handleSwitch = (id: number) => {
        if (id === active.id) return;
        router.post(`/game-accounts/${id}/switch`, {}, { preserveScroll: true });
    };

    return (
        <SidebarMenuItem>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <SidebarMenuButton
                        size="lg"
                        className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
                    >
                        <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                            <UserIcon className="size-4" />
                        </div>
                        <div className="grid flex-1 text-left text-sm leading-tight">
                            <span className="truncate font-semibold">{active.name}</span>
                            {active.server && <span className="truncate text-xs text-muted-foreground">{active.server}</span>}
                        </div>
                        {hasMultiple && <ChevronsUpDown className="ml-auto size-4" />}
                    </SidebarMenuButton>
                </DropdownMenuTrigger>

                <DropdownMenuContent align="start" className="w-64" side="bottom">
                    <DropdownMenuLabel className="text-xs text-muted-foreground">Contas</DropdownMenuLabel>
                    {accounts.map((account) => (
                        <DropdownMenuItem
                            key={account.id}
                            onClick={() => handleSwitch(account.id)}
                            className="cursor-pointer"
                        >
                            <div className="flex flex-1 items-center gap-2">
                                <div className="flex aspect-square size-6 items-center justify-center rounded-md border">
                                    <UserIcon className="size-3" />
                                </div>
                                <div className="flex-1 truncate">
                                    <div className="truncate font-medium">{account.name}</div>
                                    {account.server && <div className="truncate text-xs text-muted-foreground">{account.server}</div>}
                                </div>
                            </div>
                            {account.id === active.id && <Check className="ml-auto size-4" />}
                        </DropdownMenuItem>
                    ))}
                    <DropdownMenuSeparator />
                    <DropdownMenuItem asChild>
                        <Link href="/settings/accounts" className="cursor-pointer">
                            <Plus className="mr-2 size-4" />
                            Gerenciar contas
                        </Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </SidebarMenuItem>
    );
}
