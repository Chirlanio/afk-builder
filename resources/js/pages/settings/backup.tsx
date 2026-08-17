import { Head, router, useForm, usePage } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2, Database, Download, ListChecks, Loader2, Star, Swords, Tag, Upload, Users, Weight } from 'lucide-react';
import type { ChangeEvent, FormEvent } from 'react';
import { useRef, useState } from 'react';

import HeadingSmall from '@/components/heading-small';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';

interface AccountSummary {
    id: number;
    name: string;
    is_default: boolean;
    roster_count: number;
    formations_count: number;
    tier_lists_count: number;
    formation_tags_count: number;
    favorites_count: number;
    scoring_weight_presets_count: number;
}

interface FlashProps {
    status?: string;
    errors?: Record<string, string>;
}

interface PageProps {
    accounts: AccountSummary[];
    schema_version: number;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Backup', href: '/settings/backup' },
];

export default function BackupPage({ accounts, schema_version }: PageProps) {
    const flash = (usePage().props.flash ?? {}) as FlashProps;
    const errors = (usePage().props.errors ?? {}) as Record<string, string>;
    const fileInputRef = useRef<HTMLInputElement>(null);
    const [filename, setFilename] = useState<string | null>(null);
    const { data, setData, post, processing, reset } = useForm<{
        file: File | null;
        strategy: 'merge' | 'replace';
    }>({
        file: null,
        strategy: 'merge',
    });

    const totals = accounts.reduce(
        (acc, a) => ({
            roster: acc.roster + a.roster_count,
            formations: acc.formations + a.formations_count,
            tier_lists: acc.tier_lists + a.tier_lists_count,
            formation_tags: acc.formation_tags + a.formation_tags_count,
            favorites: acc.favorites + a.favorites_count,
            scoring_weight_presets: acc.scoring_weight_presets + a.scoring_weight_presets_count,
        }),
        { roster: 0, formations: 0, tier_lists: 0, formation_tags: 0, favorites: 0, scoring_weight_presets: 0 },
    );

    const onFileChange = (e: ChangeEvent<HTMLInputElement>) => {
        const f = e.target.files?.[0] ?? null;
        setData('file', f);
        setFilename(f?.name ?? null);
    };

    const onSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (!data.file) return;
        post(route('backup.import'), {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setFilename(null);
                if (fileInputRef.current) fileInputRef.current.value = '';
                router.reload({ only: ['accounts'] });
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Backup" />

            <SettingsLayout>
                <div className="space-y-6">
                    <HeadingSmall
                        title="Backup dos seus dados"
                        description="Baixe um arquivo JSON com o conteúdo de TODAS as suas contas do jogo. Ideal para portabilidade ou backup pessoal."
                    />

                    <div className="rounded-lg border bg-card p-4">
                        <div className="mb-3 flex items-center gap-2 text-sm font-semibold">
                            <Database className="size-4 text-muted-foreground" />
                            O que está incluído · {accounts.length} conta{accounts.length !== 1 ? 's' : ''}
                        </div>

                        <div className="space-y-3">
                            {accounts.map((account) => (
                                <div key={account.id} className="rounded-md border bg-muted/20 p-3">
                                    <div className="mb-2 flex items-center gap-2">
                                        <span className="font-medium">{account.name}</span>
                                        {account.is_default && (
                                            <span className="inline-flex items-center gap-1 rounded-md bg-amber-500/20 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:text-amber-300">
                                                <Star className="size-2.5" /> Padrão
                                            </span>
                                        )}
                                    </div>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        <SummaryRow icon={Users} label="Heróis no roster" count={account.roster_count} />
                                        <SummaryRow icon={Swords} label="Formações" count={account.formations_count} />
                                        <SummaryRow icon={ListChecks} label="Tier lists" count={account.tier_lists_count} />
                                        <SummaryRow icon={Tag} label="Tags" count={account.formation_tags_count} />
                                        <SummaryRow icon={Star} label="Favoritos" count={account.favorites_count} />
                                        <SummaryRow icon={Weight} label="Presets" count={account.scoring_weight_presets_count} />
                                    </div>
                                </div>
                            ))}
                        </div>

                        <p className="mt-3 text-[11px] text-muted-foreground">
                            Inclui stats personalizadas, equipamento, anotações por hero e por formação, beasts, artifacts/coleções.
                        </p>
                    </div>

                    <div className="rounded-lg border bg-card p-4">
                        <p className="mb-3 text-xs text-muted-foreground">
                            Schema versão <span className="font-mono font-semibold">v{schema_version}</span> (multi-conta). Arquivos v1 antigos ainda são aceitos no import (retro-compat).
                        </p>
                        <Button asChild>
                            <a href={route('backup.download')} download>
                                <Download className="size-4" />
                                Baixar JSON
                            </a>
                        </Button>
                    </div>

                    <HeadingSmall
                        title="Importar backup"
                        description="Restaure dados de um arquivo JSON. Contas do arquivo entram como novas contas; nomes duplicados ganham sufixo (importada)."
                    />

                    {flash.status && (
                        <div className="flex items-start gap-2 rounded-lg border border-emerald-500/40 bg-emerald-500/10 p-3 text-xs text-emerald-700 dark:text-emerald-300">
                            <CheckCircle2 className="mt-0.5 size-4 shrink-0" />
                            <p>{flash.status}</p>
                        </div>
                    )}

                    {errors.file && (
                        <div className="flex items-start gap-2 rounded-lg border border-destructive/40 bg-destructive/10 p-3 text-xs text-destructive">
                            <AlertTriangle className="mt-0.5 size-4 shrink-0" />
                            <p>{errors.file}</p>
                        </div>
                    )}

                    <form onSubmit={onSubmit} className="space-y-4 rounded-lg border bg-card p-4">
                        <div className="space-y-2">
                            <p className="text-xs font-medium">Estratégia</p>
                            <div className="flex flex-col gap-2 sm:flex-row">
                                <StrategyOption
                                    active={data.strategy === 'merge'}
                                    onClick={() => setData('strategy', 'merge')}
                                    title="Merge"
                                    description="Mantém suas contas atuais. Contas do backup entram como novas (com sufixo se nome colidir)."
                                    tone="emerald"
                                />
                                <StrategyOption
                                    active={data.strategy === 'replace'}
                                    onClick={() => setData('strategy', 'replace')}
                                    title="Replace"
                                    description="Apaga TODAS as contas existentes e recria do zero com o backup. Ação destrutiva."
                                    tone="rose"
                                />
                            </div>
                        </div>

                        <div className="space-y-2">
                            <label htmlFor="backup-file" className="text-xs font-medium">
                                Arquivo (.json, máx 5MB)
                            </label>
                            <input
                                ref={fileInputRef}
                                id="backup-file"
                                type="file"
                                accept="application/json,.json"
                                onChange={onFileChange}
                                className="block w-full cursor-pointer rounded-md border bg-background text-xs file:mr-3 file:cursor-pointer file:border-0 file:bg-muted file:px-3 file:py-1.5 file:text-xs file:font-medium hover:file:bg-muted/80"
                            />
                            {filename && (
                                <p className="text-[11px] text-muted-foreground">Selecionado: <span className="font-mono">{filename}</span></p>
                            )}
                        </div>

                        {data.strategy === 'replace' && data.file && (
                            <div className="flex items-start gap-2 rounded-md border border-rose-500/40 bg-rose-500/5 p-2.5 text-[11px] text-rose-700 dark:text-rose-300">
                                <AlertTriangle className="mt-0.5 size-3.5 shrink-0" />
                                <p>
                                    <strong>Modo replace</strong>: TODAS as {accounts.length} conta{accounts.length !== 1 ? 's' : ''} atuais, com seus {totals.roster} heroes, {totals.formations} formações, {totals.tier_lists} tier lists, {totals.formation_tags} tags e {totals.favorites} favoritos, serão <strong>apagadas</strong> antes do import.
                                </p>
                            </div>
                        )}

                        <Button type="submit" disabled={!data.file || processing}>
                            {processing ? <Loader2 className="size-4 animate-spin" /> : <Upload className="size-4" />}
                            {processing ? 'Importando...' : 'Importar JSON'}
                        </Button>
                    </form>
                </div>
            </SettingsLayout>
        </AppLayout>
    );
}

interface StrategyOptionProps {
    active: boolean;
    onClick: () => void;
    title: string;
    description: string;
    tone: 'emerald' | 'rose';
}

function StrategyOption({ active, onClick, title, description, tone }: StrategyOptionProps) {
    const toneClass = tone === 'emerald'
        ? 'border-emerald-500/40 bg-emerald-500/5 text-emerald-700 dark:text-emerald-300'
        : 'border-rose-500/40 bg-rose-500/5 text-rose-700 dark:text-rose-300';
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex-1 rounded-md border p-3 text-left transition-colors',
                active ? toneClass : 'border-border bg-background hover:bg-muted/50',
            )}
            aria-pressed={active}
        >
            <p className={cn('text-sm font-semibold', !active && 'text-foreground')}>{title}</p>
            <p className={cn('mt-1 text-[11px]', !active && 'text-muted-foreground')}>{description}</p>
        </button>
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
