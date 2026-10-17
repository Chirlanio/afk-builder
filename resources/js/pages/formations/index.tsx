import { tagColorClasses, type FormationTagPayload } from '@/components/formation-tags-menu';
import { PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { useConfirm } from '@/components/ui/confirm-dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { ChallengeType, Formation, FormationMode } from '@/types/afk';
import { Head, Link, router } from '@inertiajs/react';
import { FileText, Filter, GitCompareArrows, PawPrint, Plus, Search, Share2, Swords, Tag, Target, Trash2, X } from 'lucide-react';
import { useMemo, useState } from 'react';

interface FormationListItem extends Formation {
    challenge_type: ChallengeType;
    has_notes: boolean;
    has_beast: boolean;
    tags: FormationTagPayload[];
}

interface AvailableChallengeType {
    id: number;
    code: string;
    name_pt: string;
}

interface PageProps {
    formations: FormationListItem[];
    availableChallengeTypes: AvailableChallengeType[];
    availableTags: FormationTagPayload[];
}

type ModeFilter = FormationMode | 'all';
type BoolFilter = 'all' | 'yes' | 'no';

interface FilterState {
    search: string;
    challengeTypeIds: Set<number>;
    tagIds: Set<number>;
    mode: ModeFilter;
    hasBeast: BoolFilter;
    hasShare: BoolFilter;
    hasNotes: BoolFilter;
}

const INITIAL_FILTERS: FilterState = {
    search: '',
    challengeTypeIds: new Set(),
    tagIds: new Set(),
    mode: 'all',
    hasBeast: 'all',
    hasShare: 'all',
    hasNotes: 'all',
};

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Formações', href: '/formations' }];

export default function FormationsIndex({ formations, availableChallengeTypes, availableTags }: PageProps) {
    const [selected, setSelected] = useState<number[]>([]);
    const [filters, setFilters] = useState<FilterState>(INITIAL_FILTERS);
    const { confirm, dialog: confirmDialog } = useConfirm();

    const filtered = useMemo(() => {
        const term = filters.search.trim().toLowerCase();
        return formations.filter((f) => {
            if (term && !f.name.toLowerCase().includes(term)) return false;
            if (filters.challengeTypeIds.size > 0 && !filters.challengeTypeIds.has(f.challenge_type_id)) return false;
            if (filters.tagIds.size > 0) {
                const fTagIds = new Set(f.tags?.map((t) => t.id) ?? []);
                const hasAny = [...filters.tagIds].some((id) => fTagIds.has(id));
                if (!hasAny) return false;
            }
            if (filters.mode !== 'all' && f.mode !== filters.mode) return false;
            if (filters.hasBeast === 'yes' && !f.has_beast) return false;
            if (filters.hasBeast === 'no' && f.has_beast) return false;
            if (filters.hasShare === 'yes' && !f.share_token) return false;
            if (filters.hasShare === 'no' && f.share_token) return false;
            if (filters.hasNotes === 'yes' && !f.has_notes) return false;
            if (filters.hasNotes === 'no' && f.has_notes) return false;
            return true;
        });
    }, [formations, filters]);

    const hasActiveFilters =
        filters.search !== '' ||
        filters.challengeTypeIds.size > 0 ||
        filters.tagIds.size > 0 ||
        filters.mode !== 'all' ||
        filters.hasBeast !== 'all' ||
        filters.hasShare !== 'all' ||
        filters.hasNotes !== 'all';

    const remove = async (formation: FormationListItem) => {
        const ok = await confirm({
            title: `Remover "${formation.name}"?`,
            description: 'Esta ação não pode ser desfeita. Todas as equipes e versões da formação serão apagadas.',
            confirmLabel: 'Remover',
            variant: 'destructive',
        });
        if (!ok) return;
        router.delete(`/formations/${formation.id}`, { preserveScroll: true });
    };

    const toggleSelected = (id: number) => {
        setSelected((prev) => {
            if (prev.includes(id)) return prev.filter((x) => x !== id);
            if (prev.length >= 2) return [prev[1], id];
            return [...prev, id];
        });
    };

    const compareNow = () => {
        if (selected.length !== 2) return;
        router.visit(`/formations/compare/${selected[0]}/${selected[1]}`);
    };

    const toggleChallengeType = (id: number) => {
        setFilters((prev) => {
            const next = new Set(prev.challengeTypeIds);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return { ...prev, challengeTypeIds: next };
        });
    };

    const toggleTag = (id: number) => {
        setFilters((prev) => {
            const next = new Set(prev.tagIds);
            if (next.has(id)) next.delete(id);
            else next.add(id);
            return { ...prev, tagIds: next };
        });
    };

    const cycleMode = () => {
        setFilters((prev) => ({
            ...prev,
            mode: prev.mode === 'all' ? 'target' : prev.mode === 'target' ? 'real' : 'all',
        }));
    };

    const cycleBool = (key: 'hasBeast' | 'hasShare' | 'hasNotes') => {
        setFilters((prev) => ({
            ...prev,
            [key]: prev[key] === 'all' ? 'yes' : prev[key] === 'yes' ? 'no' : 'all',
        }));
    };

    const resetFilters = () => setFilters(INITIAL_FILTERS);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Formações" />

            <PageShell
                title="Formações"
                icon={Swords}
                description={
                    formations.length === 0
                        ? 'Você ainda não criou nenhuma formação.'
                        : hasActiveFilters
                          ? `${filtered.length} de ${formations.length} formações`
                          : `${formations.length} ${formations.length === 1 ? 'formação' : 'formações'}.`
                }
                actions={
                    <>
                        {selected.length === 2 && (
                            <Button variant="outline" onClick={compareNow}>
                                <GitCompareArrows className="size-4" />
                                Comparar selecionadas
                            </Button>
                        )}
                        <Button asChild>
                            <Link href="/formations/create">
                                <Plus className="size-4" />
                                Nova formação
                            </Link>
                        </Button>
                    </>
                }
            >
                {formations.length > 0 && (
                    <FilterBar
                        filters={filters}
                        availableChallengeTypes={availableChallengeTypes}
                        availableTags={availableTags}
                        hasActiveFilters={hasActiveFilters}
                        onSearchChange={(v) => setFilters((p) => ({ ...p, search: v }))}
                        onToggleChallengeType={toggleChallengeType}
                        onToggleTag={toggleTag}
                        onCycleMode={cycleMode}
                        onCycleBool={cycleBool}
                        onReset={resetFilters}
                    />
                )}

                {selected.length > 0 && (
                    <p className="text-muted-foreground text-xs">
                        {selected.length === 1
                            ? '1 formação selecionada — selecione outra para comparar.'
                            : `${selected.length} formações selecionadas. Clique "Comparar selecionadas".`}
                    </p>
                )}

                {formations.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed py-16 text-center">
                        <Swords className="text-muted-foreground size-10" />
                        <p className="text-muted-foreground text-sm">Crie sua primeira formação para começar a montar equipes.</p>
                        <Button asChild>
                            <Link href="/formations/create">
                                <Plus className="size-4" />
                                Criar formação
                            </Link>
                        </Button>
                    </div>
                ) : filtered.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed py-16 text-center">
                        <Filter className="text-muted-foreground size-10" />
                        <p className="text-muted-foreground text-sm">Nenhuma formação casa com os filtros atuais.</p>
                        <Button variant="outline" onClick={resetFilters}>
                            <X className="size-4" />
                            Limpar filtros
                        </Button>
                    </div>
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {filtered.map((formation) => {
                            const isSelected = selected.includes(formation.id);
                            return (
                                <div
                                    key={formation.id}
                                    className={cn(
                                        'group bg-card flex flex-col gap-2 rounded-xl border p-4 transition-colors',
                                        isSelected ? 'border-primary ring-primary ring-1' : 'hover:border-foreground/20',
                                    )}
                                >
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="flex flex-1 items-start gap-2">
                                            <Checkbox
                                                checked={isSelected}
                                                onCheckedChange={() => toggleSelected(formation.id)}
                                                aria-label={`Selecionar ${formation.name} para comparar`}
                                                className="mt-0.5"
                                            />
                                            <Link
                                                href={`/formations/${formation.id}/edit`}
                                                className="flex-1 truncate text-base font-semibold hover:underline"
                                            >
                                                {formation.name}
                                            </Link>
                                        </div>
                                        <Button
                                            size="icon"
                                            variant="ghost"
                                            onClick={() => remove(formation)}
                                            className="size-7 opacity-0 transition-opacity group-hover:opacity-100"
                                            aria-label="Remover"
                                        >
                                            <Trash2 className="size-3.5" />
                                        </Button>
                                    </div>

                                    <div className="flex flex-wrap items-center gap-1.5">
                                        <Badge variant="secondary" className="gap-1">
                                            <Swords className="size-3" />
                                            {formation.challenge_type.name_pt}
                                        </Badge>
                                        {formation.mode === 'target' && (
                                            <Badge variant="outline" className="gap-1">
                                                <Target className="size-3" />
                                                Alvo
                                            </Badge>
                                        )}
                                        <Badge variant="outline">
                                            {formation.teams_count} {formation.teams_count === 1 ? 'equipe' : 'equipes'}
                                        </Badge>
                                        {formation.challenge_type.is_pvp && (
                                            <Badge variant="outline" className="border-rose-500/40 text-rose-600 dark:text-rose-400">
                                                PvP
                                            </Badge>
                                        )}
                                        {formation.has_beast && (
                                            <Badge variant="outline" className="gap-1 border-emerald-500/40 text-emerald-600 dark:text-emerald-400">
                                                <PawPrint className="size-3" />
                                                Beast
                                            </Badge>
                                        )}
                                        {formation.share_token && (
                                            <Badge variant="outline" className="gap-1 border-sky-500/40 text-sky-600 dark:text-sky-400">
                                                <Share2 className="size-3" />
                                                Pública
                                            </Badge>
                                        )}
                                        {formation.has_notes && (
                                            <Badge variant="outline" className="gap-1 border-amber-500/40 text-amber-600 dark:text-amber-400">
                                                <FileText className="size-3" />
                                                Notas
                                            </Badge>
                                        )}
                                    </div>

                                    {formation.tags && formation.tags.length > 0 && (
                                        <div className="flex flex-wrap gap-1">
                                            {formation.tags.map((tag) => {
                                                const c = tagColorClasses(tag.color);
                                                return (
                                                    <Badge key={tag.id} variant="outline" className={cn('gap-1 text-[10px]', c.bg, c.text, c.border)}>
                                                        <span className={cn('size-1.5 rounded-full', c.dot)} aria-hidden />
                                                        {tag.name}
                                                    </Badge>
                                                );
                                            })}
                                        </div>
                                    )}

                                    <p className="text-muted-foreground text-xs">
                                        Atualizada {new Date(formation.updated_at).toLocaleDateString('pt-BR')}
                                    </p>
                                </div>
                            );
                        })}
                    </div>
                )}
            </PageShell>
            {confirmDialog}
        </AppLayout>
    );
}

interface FilterBarProps {
    filters: FilterState;
    availableChallengeTypes: AvailableChallengeType[];
    availableTags: FormationTagPayload[];
    hasActiveFilters: boolean;
    onSearchChange: (v: string) => void;
    onToggleChallengeType: (id: number) => void;
    onToggleTag: (id: number) => void;
    onCycleMode: () => void;
    onCycleBool: (key: 'hasBeast' | 'hasShare' | 'hasNotes') => void;
    onReset: () => void;
}

function FilterBar({
    filters,
    availableChallengeTypes,
    availableTags,
    hasActiveFilters,
    onSearchChange,
    onToggleChallengeType,
    onToggleTag,
    onCycleMode,
    onCycleBool,
    onReset,
}: FilterBarProps) {
    return (
        <div className="bg-card flex flex-col gap-3 rounded-xl border p-3">
            <div className="flex items-center gap-2">
                <div className="relative flex-1">
                    <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2" />
                    <Input
                        value={filters.search}
                        onChange={(e) => onSearchChange(e.target.value)}
                        placeholder="Buscar por nome..."
                        className="h-9 pl-8 text-sm"
                    />
                </div>
                {hasActiveFilters && (
                    <Button variant="ghost" size="sm" onClick={onReset} className="shrink-0">
                        <X className="size-3.5" />
                        Limpar
                    </Button>
                )}
            </div>

            {availableChallengeTypes.length > 1 && (
                <div className="flex flex-wrap gap-1">
                    <span className="text-muted-foreground self-center px-1 text-[11px] tracking-wide uppercase">Desafio:</span>
                    {availableChallengeTypes.map((ct) => {
                        const active = filters.challengeTypeIds.has(ct.id);
                        return (
                            <button
                                key={ct.id}
                                type="button"
                                onClick={() => onToggleChallengeType(ct.id)}
                                className={cn(
                                    'min-h-7 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors',
                                    active
                                        ? 'border-primary bg-primary text-primary-foreground'
                                        : 'border-border text-muted-foreground hover:border-foreground/30',
                                )}
                                aria-pressed={active}
                            >
                                {ct.name_pt}
                            </button>
                        );
                    })}
                </div>
            )}

            {availableTags.length > 0 && (
                <div className="flex flex-wrap gap-1">
                    <span className="text-muted-foreground self-center px-1 text-[11px] tracking-wide uppercase">
                        <Tag className="-mt-0.5 mr-1 inline size-2.5" />
                        Tags:
                    </span>
                    {availableTags.map((tag) => {
                        const c = tagColorClasses(tag.color);
                        const active = filters.tagIds.has(tag.id);
                        return (
                            <button
                                key={tag.id}
                                type="button"
                                onClick={() => onToggleTag(tag.id)}
                                className={cn(
                                    'flex min-h-7 items-center gap-1 rounded-full border px-2.5 py-1 text-xs font-medium transition-colors',
                                    active
                                        ? cn(c.bg, c.text, c.border, 'ring-1 ring-current')
                                        : 'border-border text-muted-foreground hover:border-foreground/30',
                                )}
                                aria-pressed={active}
                            >
                                <span className={cn('size-1.5 rounded-full', c.dot)} aria-hidden />
                                {tag.name}
                            </button>
                        );
                    })}
                </div>
            )}

            <div className="flex flex-wrap gap-1.5">
                <CycleButton
                    icon={Target}
                    label="Modo"
                    state={filters.mode === 'all' ? 'Todos' : filters.mode === 'target' ? 'Alvo' : 'Real'}
                    active={filters.mode !== 'all'}
                    onClick={onCycleMode}
                />
                <CycleButton
                    icon={PawPrint}
                    label="Beast"
                    state={filters.hasBeast === 'all' ? 'Todos' : filters.hasBeast === 'yes' ? 'Com' : 'Sem'}
                    active={filters.hasBeast !== 'all'}
                    onClick={() => onCycleBool('hasBeast')}
                />
                <CycleButton
                    icon={Share2}
                    label="Pública"
                    state={filters.hasShare === 'all' ? 'Todos' : filters.hasShare === 'yes' ? 'Sim' : 'Não'}
                    active={filters.hasShare !== 'all'}
                    onClick={() => onCycleBool('hasShare')}
                />
                <CycleButton
                    icon={FileText}
                    label="Notas"
                    state={filters.hasNotes === 'all' ? 'Todos' : filters.hasNotes === 'yes' ? 'Com' : 'Sem'}
                    active={filters.hasNotes !== 'all'}
                    onClick={() => onCycleBool('hasNotes')}
                />
            </div>
        </div>
    );
}

interface CycleButtonProps {
    icon: React.ComponentType<{ className?: string }>;
    label: string;
    state: string;
    active: boolean;
    onClick: () => void;
}

function CycleButton({ icon: Icon, label, state, active, onClick }: CycleButtonProps) {
    return (
        <button
            type="button"
            onClick={onClick}
            className={cn(
                'flex min-h-7 items-center gap-1.5 rounded-md border px-2.5 py-1 text-xs font-medium transition-colors',
                active ? 'border-primary bg-primary/15 text-primary' : 'border-border text-muted-foreground hover:border-foreground/30',
            )}
            aria-pressed={active}
            aria-label={`Filtro ${label}: ${state}`}
        >
            <Icon className="size-3" />
            <span>{label}:</span>
            <span className="font-semibold">{state}</span>
        </button>
    );
}
