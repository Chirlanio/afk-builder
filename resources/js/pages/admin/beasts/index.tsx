import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { PawPrint, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { BeastFormModal } from '@/components/beast-form-modal';
import { PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/components/ui/confirm-dialog';
import { beastStatCounts } from '@/lib/beast';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { Beast } from '@/types/afk';

interface PageProps {
    beasts: Beast[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Beasts', href: '/admin/beasts' }];

interface BeastTheme {
    cardGradient: string;
    cardBorder: string;
    cardHoverGlow: string;
}

function beastTheme(beast: Beast): BeastTheme {
    const counts = beastStatCounts(beast);
    const stats: Array<'str' | 'int' | 'agi'> = [];
    if (counts.strength > 0) stats.push('str');
    if (counts.intelligence > 0) stats.push('int');
    if (counts.agility > 0) stats.push('agi');

    if (stats.length === 0 && counts.team === 0) {
        return {
            cardGradient: 'bg-gradient-to-br from-muted/40 via-muted/20 to-background',
            cardBorder: 'border-dashed border-amber-500/40',
            cardHoverGlow: 'hover:shadow-amber-500/30',
        };
    }
    if (stats.length === 1) {
        if (stats[0] === 'str') return {
            cardGradient: 'bg-gradient-to-br from-rose-950/40 via-rose-900/15 to-background',
            cardBorder: 'border-rose-500/50',
            cardHoverGlow: 'hover:shadow-rose-500/40',
        };
        if (stats[0] === 'int') return {
            cardGradient: 'bg-gradient-to-br from-sky-950/40 via-sky-900/15 to-background',
            cardBorder: 'border-sky-500/50',
            cardHoverGlow: 'hover:shadow-sky-500/40',
        };
        return {
            cardGradient: 'bg-gradient-to-br from-emerald-950/40 via-emerald-900/15 to-background',
            cardBorder: 'border-emerald-500/50',
            cardHoverGlow: 'hover:shadow-emerald-500/40',
        };
    }
    return {
        cardGradient: 'bg-gradient-to-br from-rose-950/30 via-sky-950/20 to-emerald-950/30',
        cardBorder: 'border-purple-400/50',
        cardHoverGlow: 'hover:shadow-purple-400/40',
    };
}

export default function BeastsAdminIndex({ beasts }: PageProps) {
    const [editing, setEditing] = useState<Beast | null>(null);
    const [createOpen, setCreateOpen] = useState(false);
    const { confirm, dialog: confirmDialog } = useConfirm();

    const handleDelete = async (beast: Beast) => {
        const ok = await confirm({
            title: `Remover '${beast.name}'?`,
            description: 'O beast será removido do catálogo. Formações que o tinham equipado ficam sem beast.',
            confirmLabel: 'Remover',
            variant: 'destructive',
        });
        if (!ok) return;
        await axios.delete(`/admin/beasts/${beast.id}`);
        router.reload({ only: ['beasts'] });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            {confirmDialog}
            <Head title="Beasts (admin)" />

            <PageShell
                title="Beasts (Pets)"
                icon={PawPrint}
                description="Catálogo de beasts (sync de nomes via wiki, buffs cadastrados manualmente). Cada beast aplica buffs de Força/Inteligência/Agilidade aos heróis do tipo correspondente."
                actions={
                    <Button size="sm" onClick={() => setCreateOpen(true)}>
                        <Plus className="size-4" />
                        Novo beast
                    </Button>
                }
            >
                {beasts.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-center">
                        <PawPrint className="size-10 text-muted-foreground" />
                        <p className="mt-2 text-sm text-muted-foreground">Nenhum beast cadastrado ainda.</p>
                        <p className="mt-1 text-xs text-muted-foreground">Rode <code className="rounded bg-muted px-1">php artisan beasts:sync</code> ou crie manualmente.</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                        {beasts.map((beast) => {
                            const theme = beastTheme(beast);
                            const counts = beastStatCounts(beast);
                            const total = counts.total;
                            return (
                                <button
                                    key={beast.id}
                                    type="button"
                                    onClick={() => setEditing(beast)}
                                    className={cn(
                                        'group relative flex w-full flex-col overflow-hidden rounded-xl border-2 transition-all duration-200',
                                        theme.cardGradient,
                                        theme.cardBorder,
                                        theme.cardHoverGlow,
                                        'hover:scale-[1.03] hover:shadow-lg',
                                        !beast.is_active && 'opacity-60',
                                    )}
                                    aria-label={`Editar beast ${beast.name}`}
                                >
                                    <div className="absolute top-1.5 left-1.5 z-10 flex flex-col gap-1">
                                        {counts.strength > 0 && (
                                            <span className="rounded-md bg-rose-500/90 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white shadow-sm">
                                                For ({counts.strength})
                                            </span>
                                        )}
                                        {counts.intelligence > 0 && (
                                            <span className="rounded-md bg-sky-500/90 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white shadow-sm">
                                                Int ({counts.intelligence})
                                            </span>
                                        )}
                                        {counts.agility > 0 && (
                                            <span className="rounded-md bg-emerald-500/90 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white shadow-sm">
                                                Agi ({counts.agility})
                                            </span>
                                        )}
                                        {counts.team > 0 && (
                                            <span className="rounded-md bg-amber-500/90 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white shadow-sm">
                                                Eq ({counts.team})
                                            </span>
                                        )}
                                        {total === 0 && (
                                            <span className="rounded-md bg-amber-500/90 px-1.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-white shadow-sm">
                                                Sem buffs
                                            </span>
                                        )}
                                    </div>

                                    <div className="absolute top-1.5 right-1.5 z-10 flex flex-col gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                                        <span
                                            role="button"
                                            tabIndex={0}
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                setEditing(beast);
                                            }}
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter' || e.key === ' ') {
                                                    e.preventDefault();
                                                    e.stopPropagation();
                                                    setEditing(beast);
                                                }
                                            }}
                                            className="grid size-7 cursor-pointer place-items-center rounded-full bg-background/90 text-foreground shadow-sm hover:bg-background"
                                            aria-label="Editar"
                                        >
                                            <Pencil className="size-3" />
                                        </span>
                                        <span
                                            role="button"
                                            tabIndex={0}
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                handleDelete(beast);
                                            }}
                                            onKeyDown={(e) => {
                                                if (e.key === 'Enter' || e.key === ' ') {
                                                    e.preventDefault();
                                                    e.stopPropagation();
                                                    handleDelete(beast);
                                                }
                                            }}
                                            className="grid size-7 cursor-pointer place-items-center rounded-full bg-background/90 text-destructive shadow-sm hover:bg-background"
                                            aria-label="Remover"
                                        >
                                            <Trash2 className="size-3" />
                                        </span>
                                    </div>

                                    <div className="relative flex aspect-square w-full items-center justify-center overflow-hidden">
                                        {beast.portrait_url ? (
                                            <img
                                                src={beast.portrait_url}
                                                alt={beast.name}
                                                loading="lazy"
                                                onError={(e) => {
                                                    (e.currentTarget as HTMLImageElement).style.display = 'none';
                                                }}
                                                className="size-full object-contain transition-transform duration-300 group-hover:scale-105"
                                            />
                                        ) : (
                                            <PawPrint className="size-12 text-muted-foreground/60" />
                                        )}
                                    </div>

                                    <div className="flex flex-col gap-0.5 px-2 py-2 text-left">
                                        <div className="flex items-center gap-1">
                                            <span className="truncate text-sm font-semibold leading-tight">{beast.name}</span>
                                            {beast.tier && (
                                                <span
                                                    className={cn(
                                                        'shrink-0 rounded px-1 py-0.5 text-[9px] font-bold leading-none',
                                                        beast.tier === 'S+' && 'bg-gradient-to-br from-rose-500 to-amber-400 text-white',
                                                        beast.tier === 'S' && 'bg-rose-500 text-white',
                                                        beast.tier === 'A' && 'bg-orange-500 text-white',
                                                        beast.tier === 'B' && 'bg-amber-500 text-stone-900',
                                                        beast.tier === 'C' && 'bg-zinc-500 text-white',
                                                        beast.tier === 'D' && 'bg-zinc-700 text-white',
                                                        beast.tier === 'F' && 'bg-zinc-900 text-zinc-300',
                                                    )}
                                                    title={beast.tier_note ?? `Tier ${beast.tier}`}
                                                >
                                                    {beast.tier}
                                                </span>
                                            )}
                                        </div>
                                        {!beast.is_active && (
                                            <Badge variant="outline" className="mt-1 w-fit text-[10px]">Inativo</Badge>
                                        )}
                                    </div>
                                </button>
                            );
                        })}
                    </div>
                )}
            </PageShell>

            <BeastFormModal
                open={createOpen || editing !== null}
                onOpenChange={(o) => {
                    if (!o) {
                        setCreateOpen(false);
                        setEditing(null);
                    }
                }}
                beast={editing}
                onSaved={() => router.reload({ only: ['beasts'] })}
            />
        </AppLayout>
    );
}
