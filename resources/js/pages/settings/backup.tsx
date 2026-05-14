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

interface Summary {
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
                // Reload props pra refrescar summary
                router.reload({ only: ['summary'] });
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

                    <HeadingSmall
                        title="Importar backup"
                        description="Restaure dados de um arquivo JSON gerado anteriormente. Escolha a estratégia antes de continuar."
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
                                    description="Preserva o que já existe. Adiciona o novo. Conflitos (nome de tag/preset duplicado, hero já no roster) ficam de fora."
                                    tone="emerald"
                                />
                                <StrategyOption
                                    active={data.strategy === 'replace'}
                                    onClick={() => setData('strategy', 'replace')}
                                    title="Replace"
                                    description="Apaga TUDO antes de importar. Use só se quiser substituir totalmente seus dados."
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
                                    <strong>Modo replace</strong>: todos os {summary.roster_count} heroes do roster, {summary.formations_count} formações, {summary.tier_lists_count} tier lists, {summary.formation_tags_count} tags, {summary.favorites_count} favoritos e {summary.scoring_weight_presets_count} presets serão <strong>apagados</strong> antes do import.
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
