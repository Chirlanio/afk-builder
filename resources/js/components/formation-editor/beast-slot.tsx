import { useDroppable } from '@dnd-kit/core';
import { Loader2, PawPrint, Sparkles, X } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { BEAST_MAX_LEVEL, beastStatCounts, summarizeScope } from '@/lib/beast';
import { cn } from '@/lib/utils';
import type { Beast } from '@/types/afk';

interface BeastSlotProps {
    teamPosition: number;
    beast: Beast | null;
    level: number;
    onClear: () => void;
    onLevelChange: (level: number) => void;
    onAutoPick?: () => void;
    isPickingBest?: boolean;
    canAutoPick?: boolean;
}

export function BeastSlot({ teamPosition, beast, level, onClear, onLevelChange, onAutoPick, isPickingBest, canAutoPick }: BeastSlotProps) {
    const { setNodeRef, isOver } = useDroppable({
        id: `beast-slot:${teamPosition}`,
        data: { kind: 'beast-slot', teamPosition },
    });

    const counts = beastStatCounts(beast);
    // Resumo: prioriza scope team (sempre aplicado), depois mostra primeira scope com buff
    const summary = beast
        ? (counts.team > 0 ? summarizeScope(beast.buffs_team, 2)
            : counts.strength > 0 ? summarizeScope(beast.buffs_strength, 2)
            : counts.intelligence > 0 ? summarizeScope(beast.buffs_intelligence, 2)
            : counts.agility > 0 ? summarizeScope(beast.buffs_agility, 2)
            : '')
        : '';

    const clampLevel = (n: number) => Math.max(1, Math.min(BEAST_MAX_LEVEL, n || 1));

    return (
        <div className="flex items-center gap-1.5">
            <div
                ref={setNodeRef}
                className={cn(
                    'flex h-12 min-w-[160px] items-center gap-2 rounded-md border px-2 transition-colors',
                    beast ? 'border-border bg-muted/40' : 'border-dashed bg-muted/20',
                    isOver && 'border-primary bg-primary/10 ring-2 ring-primary/40',
                )}
                aria-label={`Besta da equipe ${teamPosition}`}
            >
                {beast ? (
                    <>
                        {beast.portrait_url ? (
                            <img
                                src={beast.portrait_url}
                                alt={beast.name}
                                className="size-9 rounded object-cover object-top"
                                loading="lazy"
                            />
                        ) : (
                            <div className="grid size-9 place-items-center rounded bg-muted text-muted-foreground">
                                <PawPrint className="size-4" />
                            </div>
                        )}
                        <div className="flex min-w-0 flex-1 flex-col leading-tight">
                            <span className="truncate text-xs font-medium">{beast.name}</span>
                            {summary && (
                                <span className="truncate text-[10px] text-muted-foreground">{summary}</span>
                            )}
                        </div>
                        <div className="flex flex-col items-end gap-0.5">
                            <span className="text-[9px] uppercase tracking-wide text-muted-foreground">Nv</span>
                            <input
                                type="number"
                                min={1}
                                max={BEAST_MAX_LEVEL}
                                value={level}
                                onChange={(e) => onLevelChange(clampLevel(Number(e.target.value)))}
                                onClick={(e) => (e.target as HTMLInputElement).select()}
                                className="h-6 w-12 rounded border bg-background px-1 text-center text-xs tabular-nums"
                                aria-label="Nível da besta"
                            />
                        </div>
                        <button
                            type="button"
                            onClick={onClear}
                            className="ml-1 rounded p-1 text-muted-foreground hover:bg-destructive/10 hover:text-destructive"
                            aria-label="Remover besta"
                        >
                            <X className="size-3.5" />
                        </button>
                    </>
                ) : (
                    <div className="flex flex-1 items-center gap-1.5 text-xs text-muted-foreground">
                        <PawPrint className="size-4" />
                        <span>Arraste uma besta</span>
                    </div>
                )}
            </div>
            {onAutoPick && (
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={onAutoPick}
                    disabled={isPickingBest || !canAutoPick}
                    className="h-9 gap-1 px-2 text-[11px]"
                    title={canAutoPick ? 'Escolher a melhor besta para este time' : 'Adicione heróis ao time primeiro'}
                >
                    {isPickingBest ? (
                        <Loader2 className="size-3 animate-spin" />
                    ) : (
                        <Sparkles className="size-3" />
                    )}
                    Auto
                </Button>
            )}
        </div>
    );
}
