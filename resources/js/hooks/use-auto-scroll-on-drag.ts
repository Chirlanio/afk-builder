import { useCallback, useEffect, useRef } from 'react';

interface UseAutoScrollOnDragOptions {
    /** Distância (px) da borda onde o auto-scroll começa. */
    edgeThreshold?: number;
    /** Velocidade máxima de scroll (px/frame). Aplicada na borda exata. */
    maxSpeed?: number;
    /** Container scrollável. Quando omitido, usa window. */
    container?: HTMLElement | null;
}

/**
 * Auto-scroll vertical durante operações de DnD. Detecta posição do ponteiro
 * via mousemove/touchmove e aplica scroll proporcional à distância da borda.
 *
 * Uso:
 *   const auto = useAutoScrollOnDrag();
 *   ...
 *   <DndContext onDragStart={() => auto.setActive(true)} onDragEnd={() => auto.setActive(false)} ...>
 *
 * O listener é global (window.mousemove/touchmove) — não requer wiring nos
 * elementos. Só fica ativo durante drag (entre setActive(true) e setActive(false)).
 */
export function useAutoScrollOnDrag({ edgeThreshold = 80, maxSpeed = 18, container }: UseAutoScrollOnDragOptions = {}) {
    const activeRef = useRef(false);
    const pointerYRef = useRef<number | null>(null);
    const rafRef = useRef<number | null>(null);

    const tick = useCallback(() => {
        if (!activeRef.current) {
            rafRef.current = null;
            return;
        }

        const y = pointerYRef.current;
        if (y !== null) {
            if (container) {
                const rect = container.getBoundingClientRect();
                const fromTop = y - rect.top;
                const fromBottom = rect.bottom - y;
                if (fromTop < edgeThreshold && fromTop >= 0) {
                    const speed = Math.ceil(((edgeThreshold - fromTop) / edgeThreshold) * maxSpeed);
                    container.scrollTop -= speed;
                } else if (fromBottom < edgeThreshold && fromBottom >= 0) {
                    const speed = Math.ceil(((edgeThreshold - fromBottom) / edgeThreshold) * maxSpeed);
                    container.scrollTop += speed;
                }
            } else {
                const fromTop = y;
                const fromBottom = window.innerHeight - y;
                if (fromTop < edgeThreshold && fromTop >= 0) {
                    const speed = Math.ceil(((edgeThreshold - fromTop) / edgeThreshold) * maxSpeed);
                    window.scrollBy(0, -speed);
                } else if (fromBottom < edgeThreshold && fromBottom >= 0) {
                    const speed = Math.ceil(((edgeThreshold - fromBottom) / edgeThreshold) * maxSpeed);
                    window.scrollBy(0, speed);
                }
            }
        }

        rafRef.current = requestAnimationFrame(tick);
    }, [container, edgeThreshold, maxSpeed]);

    useEffect(() => {
        const onPointerMove = (e: PointerEvent) => {
            if (!activeRef.current) return;
            pointerYRef.current = e.clientY;
        };
        const onTouchMove = (e: TouchEvent) => {
            if (!activeRef.current || e.touches.length === 0) return;
            pointerYRef.current = e.touches[0].clientY;
        };
        window.addEventListener('pointermove', onPointerMove);
        window.addEventListener('touchmove', onTouchMove, { passive: true });
        return () => {
            window.removeEventListener('pointermove', onPointerMove);
            window.removeEventListener('touchmove', onTouchMove);
        };
    }, []);

    const setActive = useCallback(
        (next: boolean) => {
            activeRef.current = next;
            if (next) {
                if (rafRef.current === null) {
                    rafRef.current = requestAnimationFrame(tick);
                }
            } else {
                pointerYRef.current = null;
                if (rafRef.current !== null) {
                    cancelAnimationFrame(rafRef.current);
                    rafRef.current = null;
                }
            }
        },
        [tick],
    );

    useEffect(() => {
        return () => {
            if (rafRef.current !== null) cancelAnimationFrame(rafRef.current);
        };
    }, []);

    return { setActive };
}
