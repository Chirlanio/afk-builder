import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import type { Locale, SharedData } from '@/types';
import { router, usePage } from '@inertiajs/react';
import { Languages } from 'lucide-react';

const LABELS: Record<Locale, string> = {
    en: 'English',
    pt: 'Português (BR)',
};

const FLAGS: Record<Locale, string> = {
    en: '🇺🇸',
    pt: '🇧🇷',
};

export function LocaleSwitcher() {
    const { auth } = usePage<SharedData>().props;
    const currentLocale = (auth.user?.locale ?? 'en') as Locale;

    const setLocale = (locale: Locale) => {
        if (locale === currentLocale) return;
        router.patch('/settings/locale', { locale }, { preserveScroll: true, preserveState: false });
    };

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="size-9" aria-label="Trocar idioma">
                    <Languages className="size-4" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="min-w-[180px]">
                {(Object.keys(LABELS) as Locale[]).map((locale) => (
                    <DropdownMenuItem
                        key={locale}
                        onClick={() => setLocale(locale)}
                        className={cn('flex cursor-pointer items-center gap-2', locale === currentLocale && 'font-semibold')}
                    >
                        <span className="text-base">{FLAGS[locale]}</span>
                        <span>{LABELS[locale]}</span>
                        {locale === currentLocale && <span className="text-muted-foreground ml-auto text-xs">atual</span>}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
