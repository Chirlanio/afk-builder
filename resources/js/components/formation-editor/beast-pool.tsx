import { useDraggable } from '@dnd-kit/core';
import { CSS } from '@dnd-kit/utilities';
import { PawPrint, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import { Input } from '@/components/ui/input';
import { beastStatCounts, summarizeScope } from '@/lib/beast';
import { cn } from '@/lib/utils';
import type { Beast } from '@/types/afk';

interface BeastPoolProps {
    beasts: Beast[];
    onTapBeast?: (beastId: number) => void;
}

interface DraggableBeastProps {
    beast: Beast;
    onTap?: () => void;
}

function DraggableBeast({ beast, onTap }: DraggableBeastProps) {
    const { attributes, listeners, setNodeRef, transform, isDragging } = useDraggable({
        id: `beast-pool:${beast.id}`,
        data: { kind: 'beast', beastId: beast.id },
    });

    const counts = beastStatCounts(beast);
    const summary = counts.team > 0
        ? summarizeScope(beast.buffs_team, 2)
        : counts.strength > 0
        ? summarizeScope(beast.buffs_strength, 2)
        : counts.intelligence > 0
        ? summarizeScope(beast.buffs_intelligence, 2)
        : counts.agility > 0
        ? summarizeScope(beast.buffs_agility, 2)
        : '';

    const handleClick = onTap && !isDragging ? onTap : undefined;

    return (
        <div
            ref={setNodeRef}
            {...listeners}
            {...attributes}
            onClick={handleClick}
            style={transform ? { transform: CSS.Translate.toString(transform) } : undefined}
            className={cn(
                'flex w-full items-center gap-2 rounded-md border bg-card p-1.5 touch-none',
                onTap ? 'cursor-pointer active:scale-[0.98] transition-transform' : 'cursor-grab active:cursor-grabbing',
                isDragging && 'z-50 opacity-50',
            )}
            role={onTap ? 'button' : undefined}
        >
            {beast.portrait_url ? (
                <img src={beast.portrait_url} alt={beast.name} className="size-9 shrink-0 rounded object-cover object-top" loading="lazy" />
            ) : (
                <div className="grid size-9 shrink-0 place-items-center rounded bg-muted text-muted-foreground">
                    <PawPrint className="size-4" />
                </div>
            )}
            <div className="flex min-w-0 flex-1 flex-col leading-tight">
                <span className="truncate text-xs font-medium">{beast.name}</span>
                {summary && (
                    <span className="truncate text-[10px] text-muted-foreground">{summary}</span>
                )}
            </div>
        </div>
    );
}

export function BeastPool({ beasts, onTapBeast }: BeastPoolProps) {
    const [search, setSearch] = useState('');

    const filtered = useMemo(() => {
        const term = search.trim().toLowerCase();
        if (!term) return beasts;
        return beasts.filter((b) => b.name.toLowerCase().includes(term));
    }, [beasts, search]);

    return (
        <div className="flex h-full flex-col gap-2 rounded-xl border bg-card p-3">
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-semibold">Bestas disponíveis</h3>
                <span className="text-xs text-muted-foreground">{filtered.length} / {beasts.length}</span>
            </div>

            <div className="relative">
                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-muted-foreground" />
                <Input
                    value={search}
                    onChange={(e) => setSearch(e.target.value)}
                    placeholder="Buscar besta..."
                    className="h-8 pl-8 text-sm"
                />
            </div>

            <div className="flex flex-1 flex-col gap-1.5 overflow-y-auto pt-1">
                {filtered.length === 0 ? (
                    <p className="py-8 text-center text-xs text-muted-foreground">
                        Nenhuma besta encontrada
                    </p>
                ) : (
                    filtered.map((beast) => (
                        <DraggableBeast
                            key={beast.id}
                            beast={beast}
                            onTap={onTapBeast ? () => onTapBeast(beast.id) : undefined}
                        />
                    ))
                )}
            </div>
        </div>
    );
}
