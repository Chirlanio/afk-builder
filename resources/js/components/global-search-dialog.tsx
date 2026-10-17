import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { router } from '@inertiajs/react';
import axios from 'axios';
import { BookOpen, ListChecks, Loader2, Search, Swords, Users, X } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

export interface SearchResultItem {
    id: number;
    label: string;
    sublabel: string | null;
    url: string;
    image_url: string | null;
}

interface SearchResponse {
    query: string;
    results: {
        heroes: SearchResultItem[];
        formations: SearchResultItem[];
        tier_lists: SearchResultItem[];
        guides: SearchResultItem[];
    };
}

type CategoryKey = 'heroes' | 'formations' | 'tier_lists' | 'guides';

const CATEGORY_META: Record<CategoryKey, { label: string; icon: typeof Users }> = {
    heroes: { label: 'Heróis', icon: Users },
    formations: { label: 'Formações', icon: Swords },
    tier_lists: { label: 'Tier Lists', icon: ListChecks },
    guides: { label: 'Guias', icon: BookOpen },
};

const DEBOUNCE_MS = 200;
const MIN_QUERY = 2;

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}

export function GlobalSearchDialog({ open, onOpenChange }: Props) {
    const [query, setQuery] = useState('');
    const [results, setResults] = useState<SearchResponse['results']>({
        heroes: [],
        formations: [],
        tier_lists: [],
        guides: [],
    });
    const [loading, setLoading] = useState(false);
    const [activeIndex, setActiveIndex] = useState(0);
    const inputRef = useRef<HTMLInputElement>(null);
    const abortRef = useRef<AbortController | null>(null);

    // Flatten resultados em ordem visual (heroes → formations → tier_lists → guides)
    const flatResults = useMemo(() => {
        const order: CategoryKey[] = ['heroes', 'formations', 'tier_lists', 'guides'];
        const list: Array<SearchResultItem & { category: CategoryKey }> = [];
        for (const key of order) {
            results[key].forEach((item) => list.push({ ...item, category: key }));
        }
        return list;
    }, [results]);

    // Reseta estado quando o dialog fecha
    useEffect(() => {
        if (!open) {
            setQuery('');
            setResults({ heroes: [], formations: [], tier_lists: [], guides: [] });
            setActiveIndex(0);
            return;
        }
        // Foca input ao abrir (Radix delega, mas autofocus pode falhar em <Dialog>)
        const t = setTimeout(() => inputRef.current?.focus(), 30);
        return () => clearTimeout(t);
    }, [open]);

    // Reseta active index quando results mudam
    useEffect(() => {
        setActiveIndex(0);
    }, [results]);

    // Busca debounced
    useEffect(() => {
        if (!open) return;

        const trimmed = query.trim();
        if (trimmed.length < MIN_QUERY) {
            setResults({ heroes: [], formations: [], tier_lists: [], guides: [] });
            setLoading(false);
            return;
        }

        setLoading(true);
        const handle = setTimeout(async () => {
            abortRef.current?.abort();
            const controller = new AbortController();
            abortRef.current = controller;
            try {
                const response = await axios.get<SearchResponse>('/search', {
                    params: { q: trimmed },
                    signal: controller.signal,
                });
                setResults(response.data.results);
            } catch (err) {
                if (!axios.isCancel(err)) {
                    console.error('Falha na busca global:', err);
                }
            } finally {
                setLoading(false);
            }
        }, DEBOUNCE_MS);

        return () => clearTimeout(handle);
    }, [query, open]);

    const handleSelect = (item: { url: string }) => {
        onOpenChange(false);
        router.visit(item.url);
    };

    const handleKeyDown = (e: React.KeyboardEvent<HTMLInputElement>) => {
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            if (flatResults.length === 0) return;
            setActiveIndex((i) => (i + 1) % flatResults.length);
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            if (flatResults.length === 0) return;
            setActiveIndex((i) => (i - 1 + flatResults.length) % flatResults.length);
        } else if (e.key === 'Enter') {
            e.preventDefault();
            const item = flatResults[activeIndex];
            if (item) handleSelect(item);
        }
    };

    const hasAnyResult = flatResults.length > 0;
    const showEmpty = !loading && query.trim().length >= MIN_QUERY && !hasAnyResult;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[80vh] gap-0 overflow-hidden p-0 sm:max-w-2xl">
                <DialogTitle className="sr-only">Busca global</DialogTitle>
                <DialogDescription className="sr-only">
                    Pesquise heróis, formações, tier lists e guias. Use as setas para navegar e Enter para abrir.
                </DialogDescription>

                {/* Input bar */}
                <div className="flex items-center gap-3 border-b px-4 py-3">
                    <Search className="text-muted-foreground size-4 shrink-0" />
                    <input
                        ref={inputRef}
                        type="text"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        onKeyDown={handleKeyDown}
                        placeholder="Buscar heróis, formações, tier lists, guias…"
                        className="placeholder:text-muted-foreground flex-1 bg-transparent text-sm outline-none"
                        aria-label="Termo de busca"
                    />
                    {loading && <Loader2 className="text-muted-foreground size-4 animate-spin" />}
                    {!loading && query.length > 0 && (
                        <button
                            type="button"
                            onClick={() => setQuery('')}
                            className="text-muted-foreground hover:bg-accent rounded p-0.5"
                            aria-label="Limpar"
                        >
                            <X className="size-3.5" />
                        </button>
                    )}
                </div>

                {/* Resultados */}
                <div className="max-h-[60vh] overflow-y-auto">
                    {query.trim().length < MIN_QUERY ? (
                        <p className="text-muted-foreground px-4 py-8 text-center text-xs">Digite pelo menos {MIN_QUERY} caracteres para buscar.</p>
                    ) : showEmpty ? (
                        <p className="text-muted-foreground px-4 py-8 text-center text-sm">
                            Nada encontrado para <span className="font-semibold">"{query}"</span>.
                        </p>
                    ) : (
                        (['heroes', 'formations', 'tier_lists', 'guides'] as CategoryKey[]).map((category) => {
                            const items = results[category];
                            if (items.length === 0) return null;
                            const meta = CATEGORY_META[category];
                            const Icon = meta.icon;
                            return (
                                <div key={category} className="border-b last:border-b-0">
                                    <div className="bg-muted/30 text-muted-foreground flex items-center gap-2 px-4 py-1.5 text-[11px] font-semibold tracking-wide uppercase">
                                        <Icon className="size-3" />
                                        {meta.label}
                                    </div>
                                    {items.map((item) => {
                                        const flatIdx = flatResults.findIndex((r) => r.category === category && r.id === item.id);
                                        const active = flatIdx === activeIndex;
                                        return (
                                            <button
                                                key={`${category}-${item.id}`}
                                                type="button"
                                                onClick={() => handleSelect(item)}
                                                onMouseEnter={() => setActiveIndex(flatIdx)}
                                                className={cn(
                                                    'flex w-full items-center gap-3 px-4 py-2.5 text-left text-sm transition-colors',
                                                    active ? 'bg-accent' : 'hover:bg-accent/50',
                                                )}
                                            >
                                                {item.image_url ? (
                                                    <img
                                                        src={item.image_url}
                                                        alt=""
                                                        className="size-8 shrink-0 rounded-full border object-cover"
                                                        loading="lazy"
                                                    />
                                                ) : (
                                                    <span className="bg-muted text-muted-foreground grid size-8 shrink-0 place-items-center rounded-full">
                                                        <Icon className="size-3.5" />
                                                    </span>
                                                )}
                                                <div className="min-w-0 flex-1">
                                                    <div className="truncate font-medium">{item.label}</div>
                                                    {item.sublabel && (
                                                        <div className="text-muted-foreground truncate text-[11px]">{item.sublabel}</div>
                                                    )}
                                                </div>
                                            </button>
                                        );
                                    })}
                                </div>
                            );
                        })
                    )}
                </div>

                {/* Footer hint */}
                <div className="bg-muted/30 text-muted-foreground flex items-center justify-between border-t px-4 py-1.5 text-[10px]">
                    <span className="flex items-center gap-2">
                        <kbd className="bg-background rounded border px-1.5 py-0.5 font-mono">↑↓</kbd> Navegar
                        <kbd className="bg-background rounded border px-1.5 py-0.5 font-mono">Enter</kbd> Abrir
                        <kbd className="bg-background rounded border px-1.5 py-0.5 font-mono">Esc</kbd> Fechar
                    </span>
                </div>
            </DialogContent>
        </Dialog>
    );
}
