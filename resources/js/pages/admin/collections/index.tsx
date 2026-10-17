import { CollectionFormModal } from '@/components/collection-form-modal';
import { PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/components/ui/confirm-dialog';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Collection } from '@/types/afk';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { Package, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';

interface PageProps {
    collections: Collection[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Coleções', href: '/admin/collections' }];

export default function CollectionsAdminIndex({ collections }: PageProps) {
    const [editing, setEditing] = useState<Collection | null>(null);
    const [createOpen, setCreateOpen] = useState(false);
    const { confirm, dialog: confirmDialog } = useConfirm();

    const handleDelete = async (collection: Collection) => {
        const ok = await confirm({
            title: `Remover '${collection.name}'?`,
            description: 'A coleção será removida do catálogo. Heróis que a tinham equipada ficarão sem coleção.',
            confirmLabel: 'Remover',
            variant: 'destructive',
        });
        if (!ok) return;
        await axios.delete(`/admin/collections/${collection.id}`);
        router.reload({ only: ['collections'] });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            {confirmDialog}
            <Head title="Coleções (admin)" />

            <PageShell
                title="Coleções da Galeria Tenebrosa"
                icon={Package}
                description="Catálogo de coleções (manual, dado que a Fandom não tem dados estruturados). Cada coleção fornece bônus de HP/ATK/DEF e ocupa o slot de artefato do herói."
                actions={
                    <Button size="sm" onClick={() => setCreateOpen(true)}>
                        <Plus className="size-4" />
                        Nova coleção
                    </Button>
                }
            >
                {collections.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-center">
                        <Package className="text-muted-foreground size-10" />
                        <p className="text-muted-foreground mt-2 text-sm">Nenhuma coleção cadastrada ainda.</p>
                        <Button variant="link" onClick={() => setCreateOpen(true)}>
                            Cadastrar primeira
                        </Button>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {collections.map((collection) => (
                            <div key={collection.id} className="bg-card flex gap-3 rounded-lg border p-3">
                                {collection.portrait_url ? (
                                    <img
                                        src={collection.portrait_url}
                                        alt={collection.name}
                                        className="bg-muted/30 size-16 shrink-0 rounded-lg border object-contain"
                                    />
                                ) : (
                                    <div className="bg-muted/30 text-muted-foreground grid size-16 shrink-0 place-items-center rounded-lg border border-dashed">
                                        <Package className="size-6" />
                                    </div>
                                )}
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <h3 className="truncate text-sm font-semibold">{collection.name}</h3>
                                            {!collection.is_active && (
                                                <Badge variant="outline" className="mt-1 text-[10px]">
                                                    Inativa
                                                </Badge>
                                            )}
                                        </div>
                                        <div className="flex shrink-0 gap-1">
                                            <Button size="icon" variant="ghost" onClick={() => setEditing(collection)} className="size-7">
                                                <Pencil className="size-3" />
                                            </Button>
                                            <Button
                                                size="icon"
                                                variant="ghost"
                                                onClick={() => handleDelete(collection)}
                                                className="text-destructive hover:bg-destructive/10 size-7"
                                            >
                                                <Trash2 className="size-3" />
                                            </Button>
                                        </div>
                                    </div>
                                    <div className="mt-1 flex flex-wrap items-center gap-1 text-[10px]">
                                        {collection.hp_bonus > 0 && <Badge variant="secondary">HP +{collection.hp_bonus}</Badge>}
                                        {collection.attack_bonus > 0 && <Badge variant="secondary">ATK +{collection.attack_bonus}</Badge>}
                                        {collection.defense_bonus > 0 && <Badge variant="secondary">DEF +{collection.defense_bonus}</Badge>}
                                    </div>
                                    {collection.description && (
                                        <p className="text-muted-foreground mt-2 line-clamp-2 text-xs">{collection.description}</p>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </PageShell>

            <CollectionFormModal
                open={createOpen || editing !== null}
                onOpenChange={(o) => {
                    if (!o) {
                        setCreateOpen(false);
                        setEditing(null);
                    }
                }}
                collection={editing}
                onSaved={() => router.reload({ only: ['collections'] })}
            />
        </AppLayout>
    );
}
