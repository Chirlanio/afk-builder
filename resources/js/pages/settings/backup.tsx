import { Head } from '@inertiajs/react';
import { Database, Download, ListChecks, Star, Swords, Tag, Users, Weight } from 'lucide-react';

import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import type { BreadcrumbItem } from '@/types';

interface Summary {
    roster_count: number;
    formations_count: number;
    tier_lists_count: number;
    formation_tags_count: number;
    favorites_count: number;
    scoring_weight_presets_count: number;
}

interface PageProps {
    summary: Summary;
    schema_version: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Backup',
        href: '/settings/backup',
    },
];

export default function BackupPage({ summary, schema_version }: PageProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Backup" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Backup dos seus dados"
                        description="Baixe um arquivo JSON com todo o conteúdo da sua conta. Útil para portabilidade ou backup pessoal."
                    />

                    <div className="rounded-lg border bg-card p-4">
                        <div className="mb-3 flex items-center gap-2 text-sm font-semibold">
                            <Database className="size-4 text-muted-foreground" />
                            O que está incluído
                        </div>

                        <div className="grid gap-2 sm:grid-cols-2">
                            <SummaryRow icon={Users} label="Heróis no roster" count={summary.roster_count} />
                            <SummaryRow icon={Swords} label="Formações" count={summary.formations_count} />
                            <SummaryRow icon={ListChecks} label="Tier lists" count={summary.tier_lists_count} />
                            <SummaryRow icon={Tag} label="Tags de formação" count={summary.formation_tags_count} />
                            <SummaryRow icon={Star} label="Heróis favoritos" count={summary.favorites_count} />
                            <SummaryRow icon={Weight} label="Presets de pesos" count={summary.scoring_weight_presets_count} />
                        </div>

                        <p className="mt-3 text-[11px] text-muted-foreground">
                            Inclui também stats personalizadas, equipamento, anotações por hero e por formação, beasts, artifacts/coleções.
                        </p>
                    </div>

                    <div className="rounded-lg border bg-card p-4">
                        <p className="mb-3 text-xs text-muted-foreground">
                            O arquivo segue o schema versão <span className="font-mono font-semibold">{schema_version}</span>.
                            Use IDs externos onde possível (slugs de heróis, código de challenge type) para que o arquivo continue válido se você importar em outra instância.
                        </p>
                        <Button asChild>
                            <a href={route('backup.download')} download>
                                <Download className="size-4" />
                                Baixar JSON
                            </a>
                        </Button>
                    </div>

                    <div className="rounded-lg border border-amber-500/30 bg-amber-500/5 p-3 text-xs text-amber-700 dark:text-amber-300">
                        <p className="font-semibold">Heads up</p>
                        <p className="mt-1">
                            Este export <strong>não inclui</strong> senha, e-mail de notificação ou qualquer informação sensível. Cole o arquivo onde quiser — é só seus dados de jogo.
                        </p>
                    </div>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

interface SummaryRowProps {
    icon: React.ComponentType<{ className?: string }>;
    label: string;
    count: number;
}

function SummaryRow({ icon: Icon, label, count }: SummaryRowProps) {
    return (
        <div className="flex items-center gap-2 rounded-md bg-muted/30 px-3 py-2 text-xs">
            <Icon className="size-3.5 text-muted-foreground" />
            <span className="flex-1">{label}</span>
            <span className="font-mono font-semibold tabular-nums">{count}</span>
        </div>
    );
}
