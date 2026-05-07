import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { PawPrint, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { BeastFormModal } from '@/components/beast-form-modal';
import { PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/components/ui/confirm-dialog';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Beast } from '@/types/afk';

interface PageProps {
    beasts: Beast[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Beasts', href: '/admin/beasts' }];

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
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {beasts.map((beast) => {
                            const total = beast.strength_buff + beast.intelligence_buff + beast.agility_buff;
                            return (
                                <div key={beast.id} className="flex gap-3 rounded-lg border bg-card p-3">
                                    {beast.portrait_url ? (
                                        <img src={beast.portrait_url} alt={beast.name} className="size-16 shrink-0 rounded-lg border bg-muted/30 object-contain" />
                                    ) : (
                                        <div className="grid size-16 shrink-0 place-items-center rounded-lg border border-dashed bg-muted/30 text-muted-foreground">
                                            <PawPrint className="size-6" />
                                        </div>
                                    )}
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-start justify-between gap-2">
                                            <div className="min-w-0">
                                                <h3 className="truncate text-sm font-semibold">{beast.name}</h3>
                                                <div className="flex flex-wrap gap-1">
                                                    {!beast.is_active && <Badge variant="outline" className="text-[10px]">Inativo</Badge>}
                                                    {total === 0 && <Badge variant="outline" className="border-amber-500/40 text-[10px] text-amber-600 dark:text-amber-400">Buffs não cadastrados</Badge>}
                                                </div>
                                            </div>
                                            <div className="flex shrink-0 gap-1">
                                                <Button size="icon" variant="ghost" onClick={() => setEditing(beast)} className="size-7">
                                                    <Pencil className="size-3" />
                                                </Button>
                                                <Button size="icon" variant="ghost" onClick={() => handleDelete(beast)} className="size-7 text-destructive hover:bg-destructive/10">
                                                    <Trash2 className="size-3" />
                                                </Button>
                                            </div>
                                        </div>
                                        <div className="mt-1 flex flex-wrap items-center gap-1 text-[10px]">
                                            {beast.strength_buff > 0 && <Badge variant="secondary" className="border-rose-500/40 bg-rose-500/10 text-rose-700 dark:text-rose-300">Força +{beast.strength_buff}</Badge>}
                                            {beast.intelligence_buff > 0 && <Badge variant="secondary" className="border-sky-500/40 bg-sky-500/10 text-sky-700 dark:text-sky-300">Int +{beast.intelligence_buff}</Badge>}
                                            {beast.agility_buff > 0 && <Badge variant="secondary" className="border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300">Agi +{beast.agility_buff}</Badge>}
                                        </div>
                                        {beast.description && (
                                            <p className="mt-2 line-clamp-2 text-xs text-muted-foreground">{beast.description}</p>
                                        )}
                                    </div>
                                </div>
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
