import { useDraggable, useDroppable } from '@dnd-kit/core';
import { CSS } from '@dnd-kit/utilities';
import { Plus, X } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { ClassCode, FactionCode, Hero, TierCode } from '@/types/afk';
import { HeroChip } from './hero-chip';

interface TeamSlotProps {
    teamPosition: number;
    slotPosition: number;
    hero: Hero | null;
    factionCode: FactionCode | null;
    classCode: ClassCode | null;
    tierCode: TierCode | null;
    onRemove: () => void;
}

/**
 * One slot in a team row. Acts as both a droppable target and (when occupied)
 * a draggable source so heroes can be dragged between slots.
 */
export function TeamSlot({ teamPosition, slotPosition, hero, factionCode, classCode, tierCode, onRemove }: TeamSlotProps) {
    const dropId = `slot:${teamPosition}:${slotPosition}`;

    const { setNodeRef: setDropRef, isOver, active } = useDroppable({
        id: dropId,
        data: { kind: 'slot', teamPosition, slotPosition },
    });

    const {
        attributes,
        listeners,
        setNodeRef: setDragRef,
        transform,
        isDragging,
    } = useDraggable({
        id: `drag-slot:${teamPosition}:${slotPosition}`,
        data: { kind: 'slot', teamPosition, slotPosition, heroId: hero?.id ?? null },
        disabled: !hero,
    });

    const setRefs = (node: HTMLDivElement | null) => {
        setDropRef(node);
        setDragRef(node);
    };

    const beingDraggedOver = isOver && active && active.id !== `drag-slot:${teamPosition}:${slotPosition}`;

    return (
        <div className="relative">
            <div
                ref={setRefs}
                {...listeners}
                {...attributes}
                style={transform ? { transform: CSS.Translate.toString(transform) } : undefined}
                className={cn(
                    'relative flex h-24 w-24 cursor-grab items-center justify-center rounded-lg border-2 border-dashed bg-muted/20 transition-all',
                    beingDraggedOver && 'border-primary bg-primary/15 ring-2 ring-primary',
                    !hero && !beingDraggedOver && 'border-border/60 hover:border-foreground/40',
                    hero && 'border-transparent',
                    isDragging && 'z-50 cursor-grabbing opacity-50',
                )}
                role="button"
                aria-label={hero ? `Slot ${slotPosition} (${hero.name})` : `Slot ${slotPosition} vazio`}
            >
                {hero && factionCode && classCode && tierCode ? (
                    <HeroChip
                        hero={hero}
                        factionCode={factionCode}
                        classCode={classCode}
                        tierCode={tierCode}
                        size="lg"
                    />
                ) : (
                    <Plus className="size-5 text-muted-foreground/50" />
                )}
            </div>

            {hero && (
                <button
                    type="button"
                    onMouseDown={(e) => e.stopPropagation()}
                    onPointerDown={(e) => e.stopPropagation()}
                    onClick={(e) => {
                        e.stopPropagation();
                        onRemove();
                    }}
                    className="absolute -top-1.5 -right-1.5 z-20 grid size-5 place-items-center rounded-full bg-destructive text-destructive-foreground shadow-md hover:scale-110"
                    aria-label="Remover do slot"
                >
                    <X className="size-3" />
                </button>
            )}
        </div>
    );
}
