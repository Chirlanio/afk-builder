import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';

import { cn } from '@/lib/utils';

interface PageShellProps {
    title: string;
    description?: ReactNode;
    icon?: LucideIcon;
    actions?: ReactNode;
    children: ReactNode;
    width?: 'full' | 'narrow';
    contentClassName?: string;
}

export function PageShell({
    title,
    description,
    icon: Icon,
    actions,
    children,
    width = 'full',
    contentClassName,
}: PageShellProps) {
    return (
        <div className="flex flex-1 flex-col gap-6 p-4 md:p-6">
            <header className="flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                <div className="flex items-start gap-3">
                    {Icon && (
                        <div className="mt-0.5 flex size-11 shrink-0 items-center justify-center rounded-lg border border-amber-400/20 bg-gradient-to-br from-amber-500/15 to-amber-500/0 text-amber-300 shadow-[0_0_18px_-4px_rgba(251,191,36,0.4)]">
                            <Icon className="size-5" />
                        </div>
                    )}
                    <div className="min-w-0">
                        <h1 className="text-2xl font-bold tracking-tight text-amber-50 md:text-3xl">
                            {title}
                        </h1>
                        {description && (
                            <p className="mt-1 max-w-2xl text-sm text-amber-100/60">{description}</p>
                        )}
                    </div>
                </div>
                {actions && <div className="flex flex-wrap items-center gap-2">{actions}</div>}
            </header>

            <div
                className={cn(
                    'flex flex-1 flex-col gap-6',
                    width === 'narrow' && 'mx-auto w-full max-w-3xl',
                    contentClassName,
                )}
            >
                {children}
            </div>
        </div>
    );
}
