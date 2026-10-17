import { PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/components/ui/confirm-dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { useAutoScrollOnDrag } from '@/hooks/use-auto-scroll-on-drag';
import AppLayout from '@/layouts/app-layout';
import { TIER_LIST_COLOR_BG, TIER_LIST_COLOR_BORDER, TIER_LIST_COLORS } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, SharedData } from '@/types';
import type { Faction, Hero, HeroClass, TierListColor, UserTierList, UserTierListItem, UserTierListRow } from '@/types/afk';
import {
    closestCorners,
    DndContext,
    type DragEndEvent,
    type DragOverEvent,
    DragOverlay,
    type DragStartEvent,
    PointerSensor,
    useDraggable,
    useDroppable,
    useSensor,
    useSensors,
} from '@dnd-kit/core';
import { arrayMove, horizontalListSortingStrategy, SortableContext, useSortable } from '@dnd-kit/sortable';
import { CSS } from '@dnd-kit/utilities';
import { Head, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import { Check, ListChecks, Pencil, Plus, Save, Search, Share2, Sparkles, Trash2, X } from 'lucide-react';
import { type FormEvent, useMemo, useState } from 'react';

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
    const [isTemplate, setIsTemplate] = useState<boolean>(Boolean(tierList.is_template));
    const isAdmin = Boolean(usePage<SharedData>().props.auth.user?.is_admin);
    const [editingRow, setEditingRow] = useState<number | null>(null);
    const [activeDrag, setActiveDrag] = useState<{ kind: 'hero'; id: number } | null>(null);
    const [dragOriginRowId, setDragOriginRowId] = useState<number | null | undefined>(undefined);

    const { confirm, dialog: confirmDialog } = useConfirm();
    const sensors = useSensors(useSensor(PointerSensor, { activationConstraint: { distance: 5 } }));
    const autoScroll = useAutoScrollOnDrag();

    /**
     * Localiza em qual row o herói está atualmente. Retorna null se está no pool.
     */
    const findRowOfHero = (heroId: number, source: RowState[] = rows): number | null => {
        for (const row of source) {
            if (row.items.some((it) => it.hero_id === heroId)) return row.id;
        }
        return null;
    };

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

    const handleDragStart = (event: DragStartEvent) => {
        const data = event.active.data.current;
        if (data?.kind !== 'hero') return;
        const heroId = Number(data.heroId);
        setActiveDrag({ kind: 'hero', id: heroId });
        setDragOriginRowId(findRowOfHero(heroId));
        autoScroll.setActive(true);
    };

    /**
     * Move herói entre containers em tempo real conforme o cursor passa por eles.
     * Não persiste; apenas atualiza o state visual. O commit acontece em handleDragEnd.
     */
    const handleDragOver = (event: DragOverEvent) => {
        const { active, over } = event;
        if (!over) return;

        const activeKind = active.data.current?.kind;
        if (activeKind !== 'hero') return;

        const heroId = Number(active.data.current?.heroId);
        const overData = over.data.current;

        // Determina o container destino via data.current.kind (IDs prefixados evitam colisão)
        let toRowId: number | null | undefined;
        if (overData?.kind === 'hero') {
            toRowId = (overData.fromRowId ?? null) as number | null;
        } else if (overData?.kind === 'row-body') {
            toRowId = Number(overData.rowId);
        } else if (overData?.kind === 'pool') {
            toRowId = null;
        } else {
            return;
        }

        const fromRowId = findRowOfHero(heroId);
        if (fromRowId === toRowId) return; // mesmo container, sortable lida

        // Move o hero entre rows no state local
        setRows((current) => {
            const movedHero = (() => {
                if (fromRowId === null) return heroes.find((h) => h.id === heroId) ?? null;
                const srcRow = current.find((r) => r.id === fromRowId);
                return srcRow?.items.find((it) => it.hero_id === heroId)?.hero ?? null;
            })();
            if (!movedHero) return current;

            return current.map((row) => {
                if (row.id === fromRowId) {
                    return { ...row, items: row.items.filter((it) => it.hero_id !== heroId) };
                }
                if (row.id === toRowId) {
                    if (row.items.some((it) => it.hero_id === heroId)) return row;
                    return {
                        ...row,
                        items: [
                            ...row.items,
                            { id: -Date.now(), user_tier_list_row_id: row.id, hero_id: heroId, position: row.items.length, hero: movedHero },
                        ],
                    };
                }
                return row;
            });
        });
    };

    const handleDragEnd = async (event: DragEndEvent) => {
        const { active, over } = event;
        const data = active.data.current;
        const originRowId = dragOriginRowId;
        setActiveDrag(null);
        setDragOriginRowId(undefined);
        autoScroll.setActive(false);

        if (!over || data?.kind !== 'hero') return;

        const heroId = Number(data.heroId);
        const overData = over.data.current;

        // Determina destino final via data.current.kind
        let targetRowId: number | null;
        if (overData?.kind === 'hero') {
            targetRowId = (overData.fromRowId ?? null) as number | null;
        } else if (overData?.kind === 'row-body') {
            targetRowId = Number(overData.rowId);
        } else if (overData?.kind === 'pool') {
            targetRowId = null;
        } else {
            return;
        }

        const currentRowId = findRowOfHero(heroId);

        // Caso 1: reorder dentro da mesma row
        if (originRowId !== undefined && originRowId !== null && targetRowId === originRowId && overData?.kind === 'hero') {
            const overHeroId = Number(overData.heroId);
            if (overHeroId === heroId) return;

            const rowIdx = rows.findIndex((r) => r.id === targetRowId);
            if (rowIdx === -1) return;

            const oldIdx = rows[rowIdx].items.findIndex((it) => it.hero_id === heroId);
            const newIdx = rows[rowIdx].items.findIndex((it) => it.hero_id === overHeroId);
            if (oldIdx === -1 || newIdx === -1 || oldIdx === newIdx) return;

            const reorderedItems = arrayMove(rows[rowIdx].items, oldIdx, newIdx);
            const updated = [...rows];
            updated[rowIdx] = { ...updated[rowIdx], items: reorderedItems };
            setRows(updated);

            try {
                await axios.post(`/tier-lists/${tierList.id}/rows/${targetRowId}/reorder-items`, {
                    hero_ids: reorderedItems.map((it) => it.hero_id),
                });
            } catch {
                router.reload({ only: ['tierList'] });
            }
            return;
        }

        // Caso 2: move entre containers (já feito visualmente em handleDragOver)
        if (currentRowId !== originRowId) {
            try {
                await axios.post(`/tier-lists/${tierList.id}/place-hero`, {
                    hero_id: heroId,
                    row_id: currentRowId,
                });
            } catch {
                router.reload({ only: ['tierList'] });
            }
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

    const handleToggleTemplate = async () => {
        const next = !isTemplate;
        const res = await axios.patch<{ ok: boolean; is_template: boolean }>(`/tier-lists/${tierList.id}/template`, {
            is_template: next,
        });
        setIsTemplate(res.data.is_template);
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
                description={editingMeta ? undefined : description || 'Arraste heróis do pool para os tiers. Edits são salvos automaticamente.'}
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
                        {isAdmin && (
                            <Button
                                size="sm"
                                variant={isTemplate ? 'default' : 'outline'}
                                onClick={handleToggleTemplate}
                                className={isTemplate ? 'bg-amber-500 hover:bg-amber-500/90' : undefined}
                                title={isTemplate ? 'Remover como template público' : 'Publicar como template'}
                            >
                                <Sparkles className="size-3" />
                                {isTemplate ? 'Template ativo' : 'Marcar template'}
                            </Button>
                        )}
                    </div>
                }
            >
                {editingMeta && (
                    <div className="bg-card mb-4 rounded-lg border p-4">
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
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => {
                                    setName(tierList.name);
                                    setDescription(tierList.description ?? '');
                                    setEditingMeta(false);
                                }}
                            >
                                Cancelar
                            </Button>
                            <Button size="sm" onClick={handleSaveMeta}>
                                <Save className="size-3" />
                                Salvar
                            </Button>
                        </div>
                    </div>
                )}

                <DndContext
                    sensors={sensors}
                    collisionDetection={closestCorners}
                    onDragStart={handleDragStart}
                    onDragOver={handleDragOver}
                    onDragEnd={handleDragEnd}
                    onDragCancel={() => {
                        setActiveDrag(null);
                        setDragOriginRowId(undefined);
                        autoScroll.setActive(false);
                    }}
                >
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

                    {/* Preview do elemento sendo arrastado, segue o cursor */}
                    <DragOverlay dropAnimation={null}>
                        {activeDrag?.kind === 'hero' &&
                            (() => {
                                const hero = heroes.find((h) => h.id === activeDrag.id);
                                return hero ? <HeroPreviewCard hero={hero} /> : null;
                            })()}
                    </DragOverlay>
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

    // Droppable no corpo da row para receber heróis
    const { setNodeRef: setDroppableRef, isOver } = useDroppable({
        id: `row-body-${row.id}`,
        data: { kind: 'row-body', rowId: row.id },
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        onSave(labelEdit.trim() || row.label, colorEdit);
    };

    return (
        <div
            className={cn(
                'flex overflow-hidden rounded-lg border-2 transition-all',
                TIER_LIST_COLOR_BORDER[row.color],
                isOver && 'ring-primary scale-[1.01] shadow-lg ring-4',
            )}
        >
            <div className={cn('flex w-20 shrink-0 flex-col items-center justify-center gap-1 px-2 py-3', TIER_LIST_COLOR_BG[row.color])}>
                {isEditing ? (
                    <form onSubmit={handleSubmit} className="flex flex-col items-center gap-1">
                        <Input
                            value={labelEdit}
                            onChange={(e) => setLabelEdit(e.target.value)}
                            maxLength={16}
                            className="bg-background text-foreground h-7 w-16 px-1 text-center text-sm font-bold"
                            autoFocus
                        />
                        <select
                            value={colorEdit}
                            onChange={(e) => setColorEdit(e.target.value as TierListColor)}
                            className="bg-background text-foreground h-6 w-16 rounded px-1 text-[10px]"
                        >
                            {TIER_LIST_COLORS.map((c) => (
                                <option key={c} value={c}>
                                    {c}
                                </option>
                            ))}
                        </select>
                        <div className="flex gap-1">
                            <button type="submit" className="rounded bg-emerald-500 p-0.5 text-white" aria-label="Salvar">
                                <Check className="size-3" />
                            </button>
                            <button type="button" onClick={onCancelEdit} className="rounded bg-zinc-500 p-0.5 text-white" aria-label="Cancelar">
                                <X className="size-3" />
                            </button>
                            <button type="button" onClick={onDelete} className="rounded bg-rose-500 p-0.5 text-white" aria-label="Excluir">
                                <Trash2 className="size-3" />
                            </button>
                        </div>
                    </form>
                ) : (
                    <button type="button" onClick={onEdit} className="flex flex-col items-center gap-0.5" aria-label={`Editar tier ${row.label}`}>
                        <span className="text-2xl leading-none font-black">{row.label}</span>
                        <span className="text-[9px] tracking-wide uppercase opacity-80">
                            {row.items.length} {row.items.length === 1 ? 'herói' : 'heróis'}
                        </span>
                    </button>
                )}
            </div>
            {/* Corpo droppable + sortable horizontal */}
            <div
                ref={setDroppableRef}
                className={cn('flex min-h-[80px] flex-1 flex-wrap items-start gap-1 p-2 transition-colors', isOver && 'bg-primary/5')}
            >
                <SortableContext items={row.items.map((it) => `hero-${it.hero_id}`)} strategy={horizontalListSortingStrategy}>
                    {row.items.map((item) => (
                        <SortableHero key={item.hero_id} hero={item.hero} rowId={row.id} />
                    ))}
                </SortableContext>
                {row.items.length === 0 && (
                    <span className={cn('self-center px-2 text-xs', isOver ? 'text-primary font-semibold' : 'text-muted-foreground')}>
                        {isOver ? '⬇ Solte aqui' : 'Arraste heróis aqui'}
                    </span>
                )}
            </div>
        </div>
    );
}

function SortableHero({ hero, rowId }: { hero: Hero; rowId: number }) {
    const { attributes, listeners, setNodeRef, transform, transition, isDragging } = useSortable({
        id: `hero-${hero.id}`,
        data: { kind: 'hero', heroId: hero.id, fromRowId: rowId },
    });
    const style: React.CSSProperties = {
        transform: CSS.Transform.toString(transform),
        transition,
    };
    return (
        <button
            ref={setNodeRef}
            style={style}
            {...attributes}
            {...listeners}
            type="button"
            className={cn(
                'bg-background flex w-14 shrink-0 cursor-grab flex-col items-center gap-0.5 rounded-md border p-1 transition-shadow active:cursor-grabbing',
                isDragging && 'opacity-30 shadow-lg',
            )}
            title={hero.name}
        >
            {hero.portrait_url ? (
                <img
                    src={hero.portrait_url}
                    alt={hero.name}
                    className="pointer-events-none size-12 rounded object-cover object-top"
                    loading="lazy"
                    draggable={false}
                />
            ) : (
                <div className="bg-muted text-muted-foreground grid size-12 place-items-center rounded text-[10px]">?</div>
            )}
            <span className="line-clamp-1 max-w-full text-center text-[9px] leading-none">{hero.name}</span>
        </button>
    );
}

function HeroPreviewCard({ hero }: { hero: Hero }) {
    return (
        <div
            className="border-primary bg-card flex w-14 cursor-grabbing flex-col items-center gap-0.5 rounded-md border-2 p-1 shadow-2xl"
            style={{ transform: 'rotate(-3deg)' }}
        >
            {hero.portrait_url ? (
                <img src={hero.portrait_url} alt={hero.name} className="pointer-events-none size-12 rounded object-cover object-top" />
            ) : (
                <div className="bg-muted text-muted-foreground grid size-12 place-items-center rounded text-[10px]">?</div>
            )}
            <span className="line-clamp-1 max-w-full text-center text-[9px] leading-none">{hero.name}</span>
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
    const { setNodeRef, isOver } = useDroppable({ id: 'pool', data: { kind: 'pool' } });

    return (
        <aside
            ref={setNodeRef}
            className={cn(
                'bg-card flex flex-col gap-2 rounded-lg border-2 p-3 transition-all',
                isOver ? 'border-primary bg-primary/5 ring-primary shadow-lg ring-4' : 'border-border',
            )}
        >
            <div
                className={cn(
                    'flex items-center gap-1 text-xs font-semibold tracking-wide uppercase',
                    isOver ? 'text-primary' : 'text-muted-foreground',
                )}
            >
                <ListChecks className="size-3" />
                {isOver ? '⬇ Solte para remover do tier' : `Pool (${heroes.length})`}
            </div>
            <div className="flex items-center gap-1">
                <Search className="text-muted-foreground size-3" />
                <Input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Buscar..." className="h-7 text-xs" />
            </div>
            <div className="grid grid-cols-2 gap-1">
                <Select value={filterFaction} onValueChange={setFilterFaction}>
                    <SelectTrigger className="h-7 text-xs">
                        <SelectValue placeholder="Facção" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todas facções</SelectItem>
                        {factions.map((f) => (
                            <SelectItem key={f.id} value={String(f.id)}>
                                {f.name_pt ?? f.code}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <Select value={filterClass} onValueChange={setFilterClass}>
                    <SelectTrigger className="h-7 text-xs">
                        <SelectValue placeholder="Classe" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="all">Todas classes</SelectItem>
                        {heroClasses.map((c) => (
                            <SelectItem key={c.id} value={String(c.id)}>
                                {c.name_pt ?? c.code}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
            </div>
            <div className="flex flex-wrap gap-1 overflow-y-auto" style={{ maxHeight: '60vh' }}>
                {heroes.map((hero) => (
                    <DraggableHero key={hero.id} hero={hero} />
                ))}
                {heroes.length === 0 && <span className="text-muted-foreground px-2 py-4 text-center text-xs">Nenhum herói no pool.</span>}
            </div>
            {heroes.length === 0 && search === '' && filterFaction === 'all' && filterClass === 'all' && (
                <p className="text-muted-foreground text-[10px]">Todos os heróis estão posicionados em algum tier.</p>
            )}
        </aside>
    );
}

function DraggableHero({ hero }: { hero: Hero }) {
    const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
        id: `hero-${hero.id}`,
        data: { kind: 'hero', heroId: hero.id, fromRowId: null },
    });
    const style = transform ? { transform: `translate3d(${transform.x}px, ${transform.y}px, 0)` } : undefined;
    return (
        <button
            ref={setNodeRef}
            {...listeners}
            {...attributes}
            style={style}
            type="button"
            className={cn(
                'bg-background flex w-14 shrink-0 cursor-grab flex-col items-center gap-0.5 rounded-md border p-1 transition-shadow active:cursor-grabbing',
                isDragging && 'opacity-40 shadow-lg',
            )}
            title={hero.name}
        >
            {hero.portrait_url ? (
                <img src={hero.portrait_url} alt={hero.name} className="size-12 rounded object-cover object-top" loading="lazy" draggable={false} />
            ) : (
                <div className="bg-muted text-muted-foreground grid size-12 place-items-center rounded text-[10px]">?</div>
            )}
            <span className="line-clamp-1 max-w-full text-center text-[9px] leading-none">{hero.name}</span>
        </button>
    );
}
