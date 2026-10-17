import { Head } from '@inertiajs/react';
import { Moon } from 'lucide-react';

import HeadingSmall from '@/components/heading-small';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { type BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Aparência',
        href: '/settings/appearance',
    },
];

export default function Appearance() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Aparência" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall title="Aparência" description="O AFK Builder usa um tema escuro fixo, inspirado nas noites de Esperia." />

                    <div className="flex items-center gap-3 rounded-lg border border-amber-400/20 bg-gradient-to-br from-amber-500/10 to-purple-500/5 p-4">
                        <div className="flex size-10 items-center justify-center rounded-md bg-amber-500/15 text-amber-300">
                            <Moon className="size-5" />
                        </div>
                        <div>
                            <p className="text-sm font-semibold text-amber-100">Tema AFK ativo</p>
                            <p className="text-xs text-amber-100/60">
                                Cores escuras com accent dourado, pensadas para combinar com a identidade visual de AFK Arena.
                            </p>
                        </div>
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}
