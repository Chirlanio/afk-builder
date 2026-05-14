import axios from 'axios';
import { ChevronDown, Loader2, Plus, Tag, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';

export interface FormationTagPayload {
    id: number;
    name: string;
    color: string;
}

const TAG_COLORS = ['amber', 'sky', 'emerald', 'rose', 'violet', 'orange', 'fuchsia', 'slate'] as const;
type TagColor = typeof TAG_COLORS[number];

const COLOR_CLASSES: Record<TagColor, { bg: string; text: string; border: string; dot: string }> = {
    amber: { bg: 'bg-amber-500/15', text: 'text-amber-700 dark:text-amber-300', border: 'border-amber-500/40', dot: 'bg-amber-400' },
    sky: { bg: 'bg-sky-500/15', text: 'text-sky-700 dark:text-sky-300', border: 'border-sky-500/40', dot: 'bg-sky-400' },
    emerald: { bg: 'bg-emerald-500/15', text: 'text-emerald-700 dark:text-emerald-300', border: 'border-emerald-500/40', dot: 'bg-emerald-400' },
    rose: { bg: 'bg-rose-500/15', text: 'text-rose-700 dark:text-rose-300', border: 'border-rose-500/40', dot: 'bg-rose-400' },
    violet: { bg: 'bg-violet-500/15', text: 'text-violet-700 dark:text-violet-300', border: 'border-violet-500/40', dot: 'bg-violet-400' },
    orange: { bg: 'bg-orange-500/15', text: 'text-orange-700 dark:text-orange-300', border: 'border-orange-500/40', dot: 'bg-orange-400' },
    fuchsia: { bg: 'bg-fuchsia-500/15', text: 'text-fuchsia-700 dark:text-fuchsia-300', border: 'border-fuchsia-500/40', dot: 'bg-fuchsia-400' },
    slate: { bg: 'bg-slate-500/15', text: 'text-slate-700 dark:text-slate-300', border: 'border-slate-500/40', dot: 'bg-slate-400' },
};

export function tagColorClasses(color: string) {
    const safe = (TAG_COLORS as readonly string[]).includes(color) ? (color as TagColor) : 'amber';
    return COLOR_CLASSES[safe];
}

interface Props {
    availableTags: FormationTagPayload[];
    selectedTagIds: number[];
    onSelectionChange: (next: number[]) => void;
    onTagsChange: (tags: FormationTagPayload[]) => void;
}

export function FormationTagsMenu({ availableTags, selectedTagIds, onSelectionChange, onTagsChange }: Props) {
    const [open, setOpen] = useState(false);
    const [creating, setCreating] = useState(false);
    const [newName, setNewName] = useState('');
    const [newColor, setNewColor] = useState<TagColor>('amber');
    const [isSaving, setIsSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const selectedSet = new Set(selectedTagIds);
    const selectedCount = selectedSet.size;

    const toggle = (id: number) => {
        const next = new Set(selectedSet);
        if (next.has(id)) next.delete(id);
        else next.add(id);
        onSelectionChange([...next]);
    };

    const handleCreate = async () => {
        const trimmed = newName.trim();
        if (!trimmed) {
            setError('Informe um nome.');
            return;
        }
        setIsSaving(true);
        setError(null);
        try {
            const response = await axios.post<{ tag: FormationTagPayload }>('/formation-tags', {
                name: trimmed,
                color: newColor,
            });
            const created = response.data.tag;
            onTagsChange([...availableTags, created].sort((a, b) => a.name.localeCompare(b.name)));
            onSelectionChange([...selectedTagIds, created.id]);
            setNewName('');
            setNewColor('amber');
            setCreating(false);
        } catch (err: unknown) {
            const e = err as { response?: { data?: { errors?: { name?: string[] }; message?: string } } };
            setError(e.response?.data?.errors?.name?.[0] ?? e.response?.data?.message ?? 'Falha ao criar.');
        } finally {
            setIsSaving(false);
        }
    };

    const handleDelete = async (tag: FormationTagPayload) => {
        if (!confirm(`Excluir tag "${tag.name}"? Ela será removida de todas as formações que a usam.`)) return;
        try {
            await axios.delete(`/formation-tags/${tag.id}`);
            onTagsChange(availableTags.filter((t) => t.id !== tag.id));
            onSelectionChange(selectedTagIds.filter((id) => id !== tag.id));
        } catch (err) {
            console.error('Falha ao excluir tag:', err);
        }
    };

    return (
        <DropdownMenu open={open} onOpenChange={setOpen}>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm" className="gap-1.5">
                    <Tag className="size-3.5" />
                    Tags
                    {selectedCount > 0 && (
                        <span className="ml-1 rounded-full bg-primary/15 px-1.5 text-[10px] font-semibold text-primary">
                            {selectedCount}
                        </span>
                    )}
                    <ChevronDown className="size-3" />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-72">
                <DropdownMenuLabel className="text-xs uppercase tracking-wide text-muted-foreground">
                    Tags da formação
                </DropdownMenuLabel>

                {availableTags.length === 0 && !creating ? (
                    <p className="px-2 py-3 text-xs text-muted-foreground">
                        Nenhuma tag criada ainda.
                    </p>
                ) : (
                    availableTags.map((tag) => {
                        const c = tagColorClasses(tag.color);
                        return (
                            <DropdownMenuCheckboxItem
                                key={tag.id}
                                checked={selectedSet.has(tag.id)}
                                onCheckedChange={() => toggle(tag.id)}
                                onSelect={(e) => e.preventDefault()}
                                className="flex items-center gap-2"
                            >
                                <span className={cn('size-2 shrink-0 rounded-full', c.dot)} aria-hidden />
                                <span className="flex-1 truncate text-sm">{tag.name}</span>
                                <button
                                    type="button"
                                    onClick={(e) => {
                                        e.stopPropagation();
                                        e.preventDefault();
                                        handleDelete(tag);
                                    }}
                                    className="rounded p-1 text-muted-foreground hover:bg-destructive hover:text-destructive-foreground"
                                    aria-label={`Excluir ${tag.name}`}
                                >
                                    <Trash2 className="size-3" />
                                </button>
                            </DropdownMenuCheckboxItem>
                        );
                    })
                )}

                <DropdownMenuSeparator />

                {!creating ? (
                    <button
                        type="button"
                        onClick={() => {
                            setCreating(true);
                            setError(null);
                        }}
                        className="flex w-full items-center gap-2 px-2 py-1.5 text-sm hover:bg-accent"
                    >
                        <Plus className="size-3.5" />
                        Criar nova tag
                    </button>
                ) : (
                    <div className="flex flex-col gap-2 p-2">
                        <input
                            type="text"
                            value={newName}
                            onChange={(e) => setNewName(e.target.value)}
                            placeholder='ex: "speedrun"'
                            maxLength={30}
                            autoFocus
                            onKeyDown={(e) => {
                                if (e.key === 'Enter') {
                                    e.preventDefault();
                                    handleCreate();
                                }
                                if (e.key === 'Escape') {
                                    setCreating(false);
                                    setError(null);
                                }
                            }}
                            className="h-8 rounded-md border border-input bg-background px-2 text-xs focus:outline-none focus:ring-2 focus:ring-ring"
                        />
                        <div className="flex flex-wrap gap-1">
                            {TAG_COLORS.map((color) => {
                                const c = COLOR_CLASSES[color];
                                const active = newColor === color;
                                return (
                                    <button
                                        key={color}
                                        type="button"
                                        onClick={() => setNewColor(color)}
                                        className={cn(
                                            'size-5 rounded-full transition-all',
                                            c.dot,
                                            active ? 'scale-110 ring-2 ring-foreground ring-offset-1 ring-offset-background' : 'opacity-60 hover:opacity-100',
                                        )}
                                        aria-label={`Cor ${color}`}
                                        aria-pressed={active}
                                    />
                                );
                            })}
                        </div>
                        {error && <p className="text-[11px] text-destructive">{error}</p>}
                        <div className="flex items-center justify-end gap-1.5">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setCreating(false);
                                    setError(null);
                                }}
                                disabled={isSaving}
                                className="h-7 text-xs"
                            >
                                Cancelar
                            </Button>
                            <Button size="sm" onClick={handleCreate} disabled={isSaving} className="h-7 gap-1 text-xs">
                                {isSaving ? <Loader2 className="size-3 animate-spin" /> : <Plus className="size-3" />}
                                Criar
                            </Button>
                        </div>
                    </div>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    );
}
