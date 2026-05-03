import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { Package, Pencil, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { CollectionFormModal } from '@/components/collection-form-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/components/ui/confirm-dialog';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Collection } from '@/types/afk';

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

            <div className="flex flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <h1 className="flex items-center gap-2 text-2xl font-semibold">
                            <Package className="size-6 text-primary" />
                            Coleções da Galeria Tenebrosa
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Catálogo de coleções (manual, dado que a Fandom não tem dados estruturados). Cada coleção fornece bônus de HP/ATK/DEF e ocupa o slot de artefato do herói.
                        </p>
                    </div>
                    <Button size="sm" onClick={() => setCreateOpen(true)}>
                        <Plus className="size-4" />
                        Nova coleção
                    </Button>
                </div>

                {collections.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-center">
                        <Package className="size-10 text-muted-foreground" />
                        <p className="mt-2 text-sm text-muted-foreground">Nenhuma coleção cadastrada ainda.</p>
                        <Button variant="link" onClick={() => setCreateOpen(true)}>Cadastrar primeira</Button>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {collections.map((collection) => (
                            <div key={collection.id} className="flex gap-3 rounded-lg border bg-card p-3">
                                {collection.portrait_url ? (
                                    <img src={collection.portrait_url} alt={collection.name} className="size-16 shrink-0 rounded-lg border bg-muted/30 object-contain" />
                                ) : (
                                    <div className="grid size-16 shrink-0 place-items-center rounded-lg border border-dashed bg-muted/30 text-muted-foreground">
                                        <Package className="size-6" />
                                    </div>
                                )}
                                <div className="min-w-0 flex-1">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0">
                                            <h3 className="truncate text-sm font-semibold">{collection.name}</h3>
                                            {!collection.is_active && <Badge variant="outline" className="mt-1 text-[10px]">Inativa</Badge>}
                                        </div>
                                        <div className="flex shrink-0 gap-1">
                                            <Button size="icon" variant="ghost" onClick={() => setEditing(collection)} className="size-7">
                                                <Pencil className="size-3" />
                                            </Button>
                                            <Button size="icon" variant="ghost" onClick={() => handleDelete(collection)} className="size-7 text-destructive hover:bg-destructive/10">
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
                                        <p className="mt-2 line-clamp-2 text-xs text-muted-foreground">{collection.description}</p>
                                    )}
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </div>

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
