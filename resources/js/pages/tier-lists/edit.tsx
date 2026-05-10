import { DndContext, type DragEndEvent, PointerSensor, useDraggable, useDroppable, useSensor, useSensors } from '@dnd-kit/core';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { Check, Copy, ListChecks, Pencil, Plus, Save, Search, Share2, Trash2, X } from 'lucide-react';
import { type FormEvent, useMemo, useState } from 'react';
import { PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/components/ui/confirm-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { TIER_LIST_COLOR_BG, TIER_LIST_COLOR_BORDER, TIER_LIST_COLORS } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { Faction, Hero, HeroClass, TierListColor, UserTierList, UserTierListItem, UserTierListRow } from '@/types/afk';

interface PageProps {
    tierList: UserTierList & { rows: (UserTierListRow & { items: (UserTierListItem & { hero: Hero })[] })[] };
    heroes: Hero[];
    factions: Faction[];
    heroClasses: HeroClass[];
}

interface RowState extends UserTierListRow {
    items: (UserTierListItem & { hero: Hero })[];
}

export default function TierListEdit({ tierList, heroes, factions, heroClasses }: PageProps) {
    const [rows, setRows] = useState<RowState[]>(tierList.rows);
    const [name, setName] = useState(tierList.name);
    const [description, setDescription] = useState(tierList.description ?? '');
    const [editingMeta, setEditingMeta] = useState(false);
    const [search, setSearch] = useState('');
    const [filterFaction, setFilterFaction] = useState<string>('all');
    const [filterClass, setFilterClass] = useState<string>('all');
    const [shareToken, setShareToken] = useState<string | null>(tierList.share_token);
    const [shareCopied, setShareCopied] = useState(false);
    const [editingRow, setEditingRow] = useState<number | null>(null);

    const { confirm, dialog: confirmDialog } = useConfirm();
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 5 } }));

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Tier Lists', href: '/tier-lists' },
        { title: name, href: `/tier-lists/${tierList.id}/edit` },
    ];

    const placedHeroIds = useMemo(() => {
        const set = new Set<number>();
        for (const row of rows) {
            for (const item of row.items) set.add(item.hero_id);
        }
        return set;
    }, [rows]);

    const pool = useMemo(() => {
        const term = search.trim().toLowerCase();
        return heroes.filter((h) => {
            if (placedHeroIds.has(h.id)) return false;
            if (filterFaction !== 'all' && String(h.faction_id) !== filterFaction) return false;
            if (filterClass !== 'all' && String(h.hero_class_id) !== filterClass) return false;
            if (term && !h.name.toLowerCase().includes(term)) return false;
            return true;
        });
    }, [heroes, placedHeroIds, search, filterFaction, filterClass]);

    const handleDragEnd = async (event: DragEndEvent) => {
        const { active, over } = event;
        if (!over) return;

        const heroId = Number(active.id);
        const overId = String(over.id);
        let targetRowId: number | null = null;
        if (overId.startsWith('row-')) {
            targetRowId = Number(overId.replace('row-', ''));
        } else if (overId === 'pool') {
            targetRowId = null;
        } else {
            return;
        }

        // Optimistic update
        const hero = heroes.find((h) => h.id === heroId);
        if (!hero) return;

        const updated = rows.map((row) => ({
            ...row,
            items: row.items.filter((it) => it.hero_id !== heroId),
        }));
        if (targetRowId !== null) {
            const idx = updated.findIndex((r) => r.id === targetRowId);
            if (idx !== -1) {
                updated[idx] = {
                    ...updated[idx],
                    items: [
                        ...updated[idx].items,
                        { id: -Date.now(), user_tier_list_row_id: targetRowId, hero_id: heroId, position: updated[idx].items.length, hero },
                    ],
                };
            }
        }
        setRows(updated);

        try {
            await axios.post(`/tier-lists/${tierList.id}/place-hero`, {
                hero_id: heroId,
                row_id: targetRowId,
            });
        } catch {
            // Rollback simples: refetch
            router.reload({ only: ['tierList'] });
        }
    };

    const handleSaveMeta = async () => {
        await axios.patch(`/tier-lists/${tierList.id}`, { name, description: description || null });
        setEditingMeta(false);
    };

    const handleAddRow = async () => {
        const colors = TIER_LIST_COLORS;
        const usedColors = new Set(rows.map((r) => r.color));
        const nextColor = colors.find((c) => !usedColors.has(c)) ?? 'zinc';
        const res = await axios.post<{ row: UserTierListRow }>(`/tier-lists/${tierList.id}/rows`, {
            label: `T${rows.length + 1}`,
            color: nextColor,
        });
        setRows([...rows, { ...res.data.row, items: [] }]);
    };

    const handleUpdateRow = async (row: RowState, label: string, color: TierListColor) => {
        await axios.patch(`/tier-lists/${tierList.id}/rows/${row.id}`, { label, color });
        setRows(rows.map((r) => (r.id === row.id ? { ...r, label, color } : r)));
        setEditingRow(null);
    };

    const handleDeleteRow = async (row: RowState) => {
        const ok = await confirm({
            title: `Excluir tier '${row.label}'?`,
            description: `Os ${row.items.length} herói(s) deste tier voltam para o pool.`,
            confirmLabel: 'Excluir',
            variant: 'destructive',
        });
        if (!ok) return;
        await axios.delete(`/tier-lists/${tierList.id}/rows/${row.id}`);
        setRows(rows.filter((r) => r.id !== row.id));
    };

    const handleShare = async () => {
        const res = await axios.post<{ share_token: string; public_url: string }>(`/tier-lists/${tierList.id}/share`);
        setShareToken(res.data.share_token);
        navigator.clipboard.writeText(res.data.public_url).catch(() => {});
        setShareCopied(true);
        setTimeout(() => setShareCopied(false), 2000);
    };

    const handleUnshare = async () => {
        const ok = await confirm({
            title: 'Revogar link público?',
            description: 'A URL atual deixará de funcionar. Você pode gerar uma nova depois.',
            confirmLabel: 'Revogar',
            variant: 'destructive',
        });
        if (!ok) return;
        await axios.delete(`/tier-lists/${tierList.id}/share`);
        setShareToken(null);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            {confirmDialog}
            <Head title={`Tier List: ${name}`} />

            <PageShell
                title={editingMeta ? 'Editar info' : name}
                icon={ListChecks}
                description={editingMeta ? undefined : (description || 'Arraste heróis do pool para os tiers. Edits são salvos automaticamente.')}
                actions={
                    <div className="flex gap-1">
                        {!editingMeta && (
                            <Button size="sm" variant="outline" onClick={() => setEditingMeta(true)}>
                                <Pencil className="size-3" />
                                Editar info
                            </Button>
                        )}
                        {shareToken ? (
                            <Button size="sm" variant="secondary" onClick={handleUnshare} title={`/tier/${shareToken}`}>
                                {shareCopied ? <Check className="size-3" /> : <Share2 className="size-3" />}
                                {shareCopied ? 'Copiado!' : 'Pública (revogar)'}
                            </Button>
                        ) : (
                            <Button size="sm" variant="outline" onClick={handleShare}>
                                <Share2 className="size-3" />
                                Compartilhar
                            </Button>
                        )}
                    </div>
                }
            >
                {editingMeta && (
                    <div className="mb-4 rounded-lg border bg-card p-4">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="flex flex-col gap-1.5">
                                <Label>Nome</Label>
                                <Input value={name} onChange={(e) => setName(e.target.value)} maxLength={100} />
                            </div>
                            <div className="flex flex-col gap-1.5">
                                <Label>Descrição</Label>
                                <Input value={description} onChange={(e) => setDescription(e.target.value)} maxLength={5000} />
                            </div>
                        </div>
                        <div className="mt-3 flex justify-end gap-2">
                            <Button size="sm" variant="ghost" onClick={() => { setName(tierList.name); setDescription(tierList.description ?? ''); setEditingMeta(false); }}>
                                Cancelar
                            </Button>
                            <Button size="sm" onClick={handleSaveMeta}>
                                <Save className="size-3" />
                                Salvar
                            </Button>
                        </div>
                    </div>
                )}

                <DndContext sensors={sensors} onDragEnd={handleDragEnd}>
                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-[1fr_280px]">
                        {/* Tiers */}
                        <div className="flex flex-col gap-2">
                            {rows.map((row) => (
                                <TierRow
                                    key={row.id}
                                    row={row}
                                    isEditing={editingRow === row.id}
                                    onEdit={() => setEditingRow(row.id)}
                                    onCancelEdit={() => setEditingRow(null)}
                                    onSave={(label, color) => handleUpdateRow(row, label, color)}
                                    onDelete={() => handleDeleteRow(row)}
                                />
                            ))}
                            <Button variant="outline" size="sm" onClick={handleAddRow} className="self-start">
                                <Plus className="size-3" />
                                Adicionar tier
                            </Button>
                        </div>

                        {/* Pool */}
                        <HeroPool
                            heroes={pool}
                            search={search}
                            setSearch={setSearch}
                            filterFaction={filterFaction}
                            setFilterFaction={setFilterFaction}
                            filterClass={filterClass}
                            setFilterClass={setFilterClass}
                            factions={factions}
                            heroClasses={heroClasses}
                        />
                    </div>
                </DndContext>
            </PageShell>
        </AppLayout>
    );
}

function TierRow({
    row,
    isEditing,
    onEdit,
    onCancelEdit,
    onSave,
    onDelete,
}: {
    row: RowState;
    isEditing: boolean;
    onEdit: () => void;
    onCancelEdit: () => void;
    onSave: (label: string, color: TierListColor) => void;
    onDelete: () => void;
}) {
    const [labelEdit, setLabelEdit] = useState(row.label);
    const [colorEdit, setColorEdit] = useState<TierListColor>(row.color);
    const { setNodeRef, isOver } = useDroppable({ id: `row-${row.id}` });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        onSave(labelEdit.trim() || row.label, colorEdit);
    };

    return (
        <div className={cn('flex overflow-hidden rounded-lg border-2 transition-colors', TIER_LIST_COLOR_BORDER[row.color], isOver && 'ring-2 ring-primary')}>
            <div className={cn('flex w-20 shrink-0 flex-col items-center justify-center gap-1 px-2 py-3', TIER_LIST_COLOR_BG[row.color])}>
                {isEditing ? (
                    <form onSubmit={handleSubmit} className="flex flex-col items-center gap-1">
                        <Input
                            value={labelEdit}
                            onChange={(e) => setLabelEdit(e.target.value)}
                            maxLength={16}
                            className="h-7 w-16 bg-background px-1 text-center text-sm font-bold text-foreground"
                            autoFocus
                        />
                        <select
                            value={colorEdit}
                            onChange={(e) => setColorEdit(e.target.value as TierListColor)}
                            className="h-6 w-16 rounded bg-background px-1 text-[10px] text-foreground"
                        >
                            {TIER_LIST_COLORS.map((c) => (
                                <option key={c} value={c}>{c}</option>
                            ))}
                        </select>
                        <div className="flex gap-1">
                            <button type="submit" className="rounded bg-emerald-500 p-0.5 text-white" aria-label="Salvar"><Check className="size-3" /></button>
                            <button type="button" onClick={onCancelEdit} className="rounded bg-zinc-500 p-0.5 text-white" aria-label="Cancelar"><X className="size-3" /></button>
                            <button type="button" onClick={onDelete} className="rounded bg-rose-500 p-0.5 text-white" aria-label="Excluir"><Trash2 className="size-3" /></button>
                        </div>
                    </form>
                ) : (
                    <button type="button" onClick={onEdit} className="flex flex-col items-center gap-0.5" aria-label={`Editar tier ${row.label}`}>
                        <span className="text-2xl font-black leading-none">{row.label}</span>
                        <span className="text-[9px] uppercase tracking-wide opacity-80">{row.items.length} {row.items.length === 1 ? 'herói' : 'heróis'}</span>
                    </button>
                )}
            </div>
            <div ref={setNodeRef} className="flex flex-1 flex-wrap items-start gap-1 p-2 min-h-[80px]">
                {row.items.map((item) => (
                    <DraggableHero key={item.hero_id} hero={item.hero} />
                ))}
                {row.items.length === 0 && (
                    <span className="self-center px-2 text-xs text-muted-foreground">Arraste heróis aqui</span>
                )}
            </div>
        </div>
    );
}

function HeroPool({
    heroes,
    search,
    setSearch,
    filterFaction,
    setFilterFaction,
    filterClass,
    setFilterClass,
    factions,
    heroClasses,
}: {
    heroes: Hero[];
    search: string;
    setSearch: (s: string) => void;
    filterFaction: string;
    setFilterFaction: (s: string) => void;
    filterClass: string;
    setFilterClass: (s: string) => void;
    factions: Faction[];
    heroClasses: HeroClass[];
}) {
    const { setNodeRef, isOver } = useDroppable({ id: 'pool' });

    return (
        <aside ref={setNodeRef} className={cn('flex flex-col gap-2 rounded-lg border bg-card p-3', isOver && 'ring-2 ring-primary')}>
            <div className="flex items-center gap-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">
                <ListChecks className="size-3" />
                Pool ({heroes.length})
            </div>
            <div className="flex items-center gap-1">
                <Search className="size-3 text-muted-foreground" />
                <Input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Buscar..."
                    className="h-7 text-xs"
                />
            </div>
            <div className="grid grid-cols-2 gap-1">
                <Select value={filterFaction} onValueChange={setFilterFaction}>
                    <SelectTrigger className="h-7 text-xs"><SelectValue placeholder="Facção" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todas facções</SelectItem>
                        {factions.map((f) => (
                            <SelectItem key={f.id} value={String(f.id)}>{f.name_pt ?? f.code}</SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Select value={filterClass} onValueChange={setFilterClass}>
                    <SelectTrigger className="h-7 text-xs"><SelectValue placeholder="Classe" /></SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todas classes</SelectItem>
                        {heroClasses.map((c) => (
                            <SelectItem key={c.id} value={String(c.id)}>{c.name_pt ?? c.code}</SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div className="flex flex-wrap gap-1 overflow-y-auto" style={{ maxHeight: '60vh' }}>
                {heroes.map((hero) => (
                    <DraggableHero key={hero.id} hero={hero} />
                ))}
                {heroes.length === 0 && (
                    <span className="px-2 py-4 text-center text-xs text-muted-foreground">Nenhum herói no pool.</span>
                )}
            </div>
            {heroes.length === 0 && search === '' && filterFaction === 'all' && filterClass === 'all' && (
                <p className="text-[10px] text-muted-foreground">Todos os heróis estão posicionados em algum tier.</p>
            )}
        </aside>
    );
}

function DraggableHero({ hero }: { hero: Hero }) {
    const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({ id: hero.id });
    const style = transform ? { transform: `translate3d(${transform.x}px, ${transform.y}px, 0)` } : undefined;
    return (
        <button
            ref={setNodeRef}
            {...listeners}
            {...attributes}
            style={style}
            type="button"
            className={cn(
                'flex w-14 shrink-0 cursor-grab flex-col items-center gap-0.5 rounded-md border bg-background p-1 transition-shadow active:cursor-grabbing',
                isDragging && 'opacity-40 shadow-lg',
            )}
            title={hero.name}
        >
            {hero.portrait_url ? (
                <img src={hero.portrait_url} alt={hero.name} className="size-12 rounded object-cover object-top" loading="lazy" draggable={false} />
            ) : (
                <div className="grid size-12 place-items-center rounded bg-muted text-[10px] text-muted-foreground">?</div>
            )}
            <span className="line-clamp-1 max-w-full text-center text-[9px] leading-none">{hero.name}</span>
        </button>
    );
}
