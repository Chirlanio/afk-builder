import { PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/components/ui/confirm-dialog';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { UserTierList } from '@/types/afk';
import { Head, Link, router } from '@inertiajs/react';
import axios from 'axios';
import { LayoutTemplate, ListChecks, Pencil, Plus, Share2, Sparkles, Trash2 } from 'lucide-react';

interface PageProps {
    tierLists: (UserTierList & { rows_count: number })[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Tier Lists', href: '/tier-lists' }];

export default function TierListsIndex({ tierLists }: PageProps) {
    const { confirm, dialog: confirmDialog } = useConfirm();

    const handleDelete = async (list: UserTierList) => {
        const ok = await confirm({
            title: `Excluir '${list.name}'?`,
            description: 'Esta tier list e todos os heróis posicionados serão removidos. Ação irreversível.',
            confirmLabel: 'Excluir',
            variant: 'destructive',
        });
        if (!ok) return;
        await axios.delete(`/tier-lists/${list.id}`);
        router.reload({ only: ['tierLists'] });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            {confirmDialog}
            <Head title="Tier Lists" />

            <PageShell
                title="Tier Lists"
                icon={ListChecks}
                description="Crie suas próprias tier lists de heróis. Arraste-os para organizar em tiers customizáveis."
                actions={
                    <>
                        <Button size="sm" variant="outline" asChild>
                            <Link href="/tier-lists/templates">
                                <LayoutTemplate className="size-4" />
                                Templates
                            </Link>
                        </Button>
                        <Button size="sm" asChild>
                            <Link href="/tier-lists/create">
                                <Plus className="size-4" />
                                Nova tier list
                            </Link>
                        </Button>
                    </>
                }
            >
                {tierLists.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-center">
                        <ListChecks className="text-muted-foreground size-10" />
                        <p className="text-muted-foreground mt-2 text-sm">Nenhuma tier list criada ainda.</p>
                        <Button asChild className="mt-4" size="sm">
                            <Link href="/tier-lists/create">
                                <Plus className="size-4" />
                                Criar primeira
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {tierLists.map((list) => (
                            <div key={list.id} className="bg-card flex flex-col gap-2 rounded-xl border p-4">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0 flex-1">
                                        <Link href={`/tier-lists/${list.id}/edit`} className="block truncate text-base font-semibold hover:underline">
                                            {list.name}
                                        </Link>
                                        <div className="mt-1 flex flex-wrap items-center gap-1">
                                            <Badge variant="outline" className="text-[10px]">
                                                {list.rows_count} tiers
                                            </Badge>
                                            {list.share_token && (
                                                <Badge variant="secondary" className="gap-1 text-[10px]">
                                                    <Share2 className="size-3" />
                                                    Pública
                                                </Badge>
                                            )}
                                            {list.is_template && (
                                                <Badge variant="default" className="gap-1 bg-amber-500 text-[10px] hover:bg-amber-500/90">
                                                    <Sparkles className="size-3" />
                                                    Template
                                                </Badge>
                                            )}
                                        </div>
                                    </div>
                                </div>
                                {list.description && <p className="text-muted-foreground line-clamp-2 text-xs">{list.description}</p>}
                                <div className="mt-auto flex justify-end gap-1 border-t pt-2">
                                    <Button asChild size="sm" variant="outline">
                                        <Link href={`/tier-lists/${list.id}/edit`}>
                                            <Pencil className="size-3" />
                                            Editar
                                        </Link>
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() => handleDelete(list)}
                                        className="text-destructive hover:bg-destructive/10"
                                    >
                                        <Trash2 className="size-3" />
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </PageShell>
        </AppLayout>
    );
}
