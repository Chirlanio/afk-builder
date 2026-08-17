import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
    /** Badge count opcional renderizado à direita do item (ex: pending review). Esconde se 0/null. */
    badgeCount?: number | null;
    /** Tom do badge (default amber). */
    badgeTone?: 'amber' | 'rose';
}

export interface GameAccountShared {
    id: number;
    name: string;
    server: string | null;
    avatar_hero_id: number | null;
    is_default?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    admin?: {
        pending_heroes_count: number | null;
    };
    gameAccount?: {
        active: GameAccountShared;
        accounts: GameAccountShared[];
    } | null;
    [key: string]: unknown;
}

export type Locale = 'en' | 'pt';

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    is_admin?: boolean;
    locale?: Locale;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}
