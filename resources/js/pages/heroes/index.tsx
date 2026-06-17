import { HeroCard } from '@/components/hero-card';
import { HeroDetailModal } from '@/components/hero-detail-modal';
import {
    HeroesAdvancedFilters,
    defaultAdvancedFilters,
    isAdvancedFiltersActive,
    type AdvancedFiltersState,
} from '@/components/heroes-advanced-filters';
import { PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { CLASS_ICON, FACTION_THEME, HERO_FEATURE_BADGE, HERO_FEATURE_LABEL, HERO_FEATURE_TAGS } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { ClassCode, Faction, FactionCode, Hero, HeroClass, HeroFeatureTag, RosterByHeroId, Tier, TierCode } from '@/types/afk';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { CheckSquare, Heart, HeartOff, Loader2, MinusCircle, PlusCircle, Search, Users, X, Upload } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';

interface PageProps {
    heroes: Hero[];
    factions: Faction[];
    heroClasses: HeroClass[];
    tiers: Tier[];
    roster: RosterByHeroId;
    favoriteHeroIds: number[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Heróis', href: '/heroes' }];

export default function HeroesIndex({ heroes, factions, heroClasses, tiers, roster, favoriteHeroIds }: PageProps) {
    const [search, setSearch] = useState('');
    const [factionFilter, setFactionFilter] = useState<Set<number>>(new Set());
    const [classFilter, setClassFilter] = useState<Set<number>>(new Set());
    const [featureFilter, setFeatureFilter] = useState<Set<HeroFeatureTag>>(new Set());
    const [onlyOwned, setOnlyOwned] = useState(false);
    const [onlyFavorites, setOnlyFavorites] = useState(false);
    const [advanced, setAdvanced] = useState<AdvancedFiltersState>(defaultAdvancedFilters);
    const [openSlug, setOpenSlug] = useState<string | null>(null);

    const advancedActive = isAdvancedFiltersActive(advanced);
    const advancedScopesRoster =
        advanced.siMin > 0 ||
        advanced.fMin > 0 ||
        advanced.engMin > 0 ||
        advanced.levelMin > 0 ||
        advanced.hasArtifact !== 'all' ||
        advanced.hasCollection !== 'all';

    const favoriteSet = useMemo(() => new Set(favoriteHeroIds), [favoriteHeroIds]);

    // Fase 45: modo seleção em lote
    const [selectionMode, setSelectionMode] = useState(false);
    const [selectedIds, setSelectedIds] = useState<Set<number>>(new Set());
    const [bulkProcessing, setBulkProcessing] = useState(false);

    const exitSelectionMode = () => {
        setSelectionMode(false);
        setSelectedIds(new Set());
    };

    const toggleSelected = (heroId: number) => {
        setSelectedIds((prev) => {
            const next = new Set(prev);
            if (next.has(heroId)) next.delete(heroId);
            else next.add(heroId);
            return next;
        });
    };

    // Esc cancela seleção em qualquer página (Fase 44 ignora hook em inputs;
    // aqui escutamos diretamente porque é específico desta página)
    useEffect(() => {
        if (!selectionMode) return;
        const handler = (e: KeyboardEvent) => {
            if (e.key === 'Escape') {
                e.preventDefault();
                exitSelectionMode();
            }
        };
        window.addEventListener('keydown', handler);
        return () => window.removeEventListener('keydown', handler);
    }, [selectionMode]);

    const runBulkAction = async (
        endpoint: 'favorites' | 'roster',
        action: 'add' | 'remove',
    ) => {
        if (selectedIds.size === 0 || bulkProcessing) return;
        setBulkProcessing(true);
        try {
            await axios.post(`/heroes/bulk/${endpoint}`, {
                hero_ids: [...selectedIds],
                action,
            });
            // Refrescar payload pra atualizar favoriteHeroIds + roster
            router.reload({ only: ['favoriteHeroIds', 'roster'] });
            exitSelectionMode();
        } catch (err) {
            console.error('Bulk action failed:', err);
        } finally {
            setBulkProcessing(false);
        }
    };

    // Abre modal automaticamente quando o índice é carregado com ?open=slug
    // (deep link a partir de /heroes/{slug} legacy ou compartilhamento).
    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const slug = params.get('open');
        if (slug) {
            setOpenSlug(slug);
            // Limpa o query param da URL sem reload
            const url = new URL(window.location.href);
            url.searchParams.delete('open');
            window.history.replaceState({}, '', url.toString());
        }
    }, []);

    const classById = useMemo(() => Object.fromEntries(heroClasses.map((c) => [c.id, c])) as Record<number, HeroClass>, [heroClasses]);
    const tierById = useMemo(() => Object.fromEntries(tiers.map((t) => [t.id, t])) as Record<number, Tier>, [tiers]);

    const filteredByFaction = useMemo(() => {
        const term = search.trim().toLowerCase();
        const map = new Map<number, Hero[]>();

        for (const hero of heroes) {
            if (factionFilter.size > 0 && !factionFilter.has(hero.faction_id)) continue;
            if (classFilter.size > 0 && !classFilter.has(hero.hero_class_id)) continue;
            if (onlyOwned && !roster[hero.id]) continue;
            if (onlyFavorites && !favoriteSet.has(hero.id)) continue;
            if (advanced.tierIds.size > 0 && !advanced.tierIds.has(hero.tier_id)) continue;
            if (featureFilter.size > 0) {
                const tags = hero.features?.map((f) => f.tag) ?? [];
                let matches = false;
                for (const tag of featureFilter) {
                    if (tags.includes(tag)) {
                        matches = true;
                        break;
                    }
                }
                if (!matches) continue;
            }
            if (term) {
                const haystack = `${hero.name} ${hero.title ?? ''}`.toLowerCase();
                if (!haystack.includes(term)) continue;
            }

            // Filtros avançados que dependem do roster (investimento + equipamento).
            // Heróis fora do roster são excluídos quando qualquer destes está ativo.
            if (advancedScopesRoster) {
                const uh = roster[hero.id];
                if (!uh) continue;
                if (advanced.siMin > 0 && uh.signature_item < advanced.siMin) continue;
                if (advanced.fMin > 0 && uh.furniture < advanced.fMin) continue;
                if (advanced.engMin > 0 && uh.engraving < advanced.engMin) continue;
                if (advanced.levelMin > 0 && uh.level < advanced.levelMin) continue;
                if (advanced.hasArtifact === 'yes' && uh.artifact_id === null) continue;
                if (advanced.hasArtifact === 'no' && uh.artifact_id !== null) continue;
                if (advanced.hasCollection === 'yes' && uh.collection_id === null) continue;
                if (advanced.hasCollection === 'no' && uh.collection_id !== null) continue;
            }

            const list = map.get(hero.faction_id) ?? [];
            list.push(hero);
            map.set(hero.faction_id, list);
        }

        return map;
    }, [heroes, search, factionFilter, classFilter, featureFilter, onlyOwned, onlyFavorites, favoriteSet, roster, advanced, advancedScopesRoster]);

    const totalShown = useMemo(() => {
        let n = 0;
        filteredByFaction.forEach((list) => {
            n += list.length;
        });
        return n;
    }, [filteredByFaction]);

    const ownedCount = Object.keys(roster).length;

    const toggleFromSet = <T,>(set: Set<T>, id: T, setter: (s: Set<T>) => void) => {
        const next = new Set(set);
        if (next.has(id)) next.delete(id);
        else next.add(id);
        setter(next);
    };

    const clearFilters = () => {
        setSearch('');
        setFactionFilter(new Set());
        setClassFilter(new Set());
        setFeatureFilter(new Set());
        setOnlyOwned(false);
        setOnlyFavorites(false);
        setAdvanced(defaultAdvancedFilters());
    };

    const openHero = (hero: Hero) => {
        setOpenSlug(hero.slug);
    };

    const filtersActive =
        search.length > 0 ||
        factionFilter.size > 0 ||
        classFilter.size > 0 ||
        featureFilter.size > 0 ||
        onlyOwned ||
        onlyFavorites ||
        advancedActive;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Heróis" />

            <PageShell
                title="Catálogo de Heróis"
                icon={Users}
                description={`${heroes.length} heróis no catálogo · ${ownedCount} no seu roster · ${totalShown} exibidos`}
                actions={
                    <Button variant="outline" size="sm" onClick={() => router.get(route('roster.import'))}>
                        <Upload className="mr-2 size-4" />
                        Importar OCR
                    </Button>
                }
            >
                <div className="bg-background/95 supports-[backdrop-filter]:bg-background/80 sticky top-0 z-10 flex flex-col gap-3 rounded-xl border p-3 backdrop-blur">
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="relative min-w-[220px] flex-1">
                            <Search className="text-muted-foreground pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Buscar herói por nome ou título..."
                                className="pl-9"
                            />
                        </div>

                        <Button variant={onlyOwned ? 'default' : 'outline'} size="sm" onClick={() => setOnlyOwned((v) => !v)}>
                            Só meus ({ownedCount})
                        </Button>
                        <Button
                            variant={onlyFavorites ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setOnlyFavorites((v) => !v)}
                            className={cn('gap-1.5', onlyFavorites && 'bg-rose-500/15 text-rose-600 hover:bg-rose-500/20 dark:text-rose-300')}
                            aria-pressed={onlyFavorites}
                        >
                            <Heart className={cn('size-3.5', onlyFavorites && 'fill-current')} />
                            Favoritos ({favoriteSet.size})
                        </Button>

                        {filtersActive && (
                            <Button variant="ghost" size="sm" onClick={clearFilters}>
                                <X className="size-3.5" />
                                Limpar
                            </Button>
                        )}

                        <Button
                            variant={selectionMode ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => (selectionMode ? exitSelectionMode() : setSelectionMode(true))}
                            className="gap-1.5"
                            aria-pressed={selectionMode}
                        >
                            <CheckSquare className="size-3.5" />
                            {selectionMode ? `Cancelar (${selectedIds.size})` : 'Selecionar'}
                        </Button>
                    </div>

                    <div className="flex flex-wrap items-center gap-1.5">
                        <span className="text-muted-foreground text-xs font-medium">Facção:</span>
                        {factions.map((faction) => {
                            const code = faction.code as FactionCode;
                            const theme = FACTION_THEME[code];
                            const active = factionFilter.has(faction.id);
                            return (
                                <button
                                    key={faction.id}
                                    type="button"
                                    onClick={() => toggleFromSet(factionFilter, faction.id, setFactionFilter)}
                                    className={cn(
                                        'rounded-full border px-2.5 py-0.5 text-xs font-medium transition-all',
                                        active
                                            ? cn(theme.cardGradient, theme.cardBorder, 'text-foreground shadow-sm')
                                            : 'border-border text-muted-foreground hover:border-foreground/30 hover:text-foreground',
                                    )}
                                >
                                    {faction.name_pt}
                                </button>
                            );
                        })}
                    </div>

                    <div className="flex flex-wrap items-center gap-1.5">
                        <span className="text-muted-foreground text-xs font-medium">Classe:</span>
                        {heroClasses.map((heroClass) => {
                            const Icon = CLASS_ICON[heroClass.code as ClassCode];
                            const active = classFilter.has(heroClass.id);
                            return (
                                <button
                                    key={heroClass.id}
                                    type="button"
                                    onClick={() => toggleFromSet(classFilter, heroClass.id, setClassFilter)}
                                    className={cn(
                                        'flex items-center gap-1 rounded-full border px-2.5 py-0.5 text-xs font-medium transition-all',
                                        active
                                            ? 'border-primary bg-primary text-primary-foreground'
                                            : 'border-border text-muted-foreground hover:border-foreground/30 hover:text-foreground',
                                    )}
                                >
                                    <Icon className="size-3" />
                                    {heroClass.name_pt}
                                </button>
                            );
                        })}
                    </div>

                    <div className="flex flex-wrap items-center gap-1.5">
                        <span className="text-muted-foreground text-xs font-medium">Destaque:</span>
                        {HERO_FEATURE_TAGS.map((tag) => {
                            const active = featureFilter.has(tag);
                            return (
                                <button
                                    key={tag}
                                    type="button"
                                    onClick={() => toggleFromSet(featureFilter, tag, setFeatureFilter)}
                                    className={cn(
                                        'rounded-full border px-2.5 py-0.5 text-xs font-medium transition-all',
                                        active
                                            ? cn(HERO_FEATURE_BADGE[tag], 'shadow-sm')
                                            : 'border-border text-muted-foreground hover:border-foreground/30 hover:text-foreground',
                                    )}
                                >
                                    {HERO_FEATURE_LABEL[tag]}
                                </button>
                            );
                        })}
                    </div>

                    <HeroesAdvancedFilters
                        state={advanced}
                        tiers={tiers}
                        onChange={setAdvanced}
                        onReset={() => setAdvanced(defaultAdvancedFilters())}
                        isActive={advancedActive}
                    />
                </div>

                <div className="flex flex-col gap-6">
                    {factions.map((faction) => {
                        const list = filteredByFaction.get(faction.id) ?? [];
                        if (list.length === 0) return null;

                        const code = faction.code as FactionCode;
                        const theme = FACTION_THEME[code];

                        return (
                            <section key={faction.id} className="flex flex-col gap-3">
                                <div
                                    className={cn(
                                        'flex items-center justify-between rounded-lg border-l-4 px-3 py-2',
                                        theme.headerBg,
                                        theme.cardBorder,
                                    )}
                                >
                                    <h2 className={cn('text-lg font-semibold', theme.headerText)}>{faction.name_pt}</h2>
                                    <Badge variant="secondary" className="text-xs">
                                        {list.length} {list.length === 1 ? 'herói' : 'heróis'}
                                    </Badge>
                                </div>
                                <div className="grid grid-cols-2 gap-3 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6">
                                    {list.map((hero) => {
                                        const heroClass = classById[hero.hero_class_id];
                                        const tier = tierById[hero.tier_id];
                                        return (
                                            <HeroCard
                                                key={hero.id}
                                                hero={hero}
                                                factionCode={code}
                                                classCode={heroClass.code as ClassCode}
                                                tierCode={tier.code as TierCode}
                                                userHero={roster[hero.id]}
                                                onClick={() => openHero(hero)}
                                                selectionMode={selectionMode}
                                                isSelected={selectedIds.has(hero.id)}
                                                onToggleSelect={() => toggleSelected(hero.id)}
                                            />
                                        );
                                    })}
                                </div>
                            </section>
                        );
                    })}

                    {totalShown === 0 && (
                        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-center">
                            <p className="text-muted-foreground text-sm">Nenhum herói corresponde aos filtros atuais.</p>
                            {filtersActive && (
                                <Button variant="link" onClick={clearFilters}>
                                    Limpar filtros
                                </Button>
                            )}
                        </div>
                    )}
                </div>
            </PageShell>

            <HeroDetailModal
                slug={openSlug}
                open={openSlug !== null}
                onOpenChange={(o) => !o && setOpenSlug(null)}
                onNavigate={(slug) => setOpenSlug(slug)}
            />

            {/* Fase 45: barra flutuante de ações em lote */}
            {selectionMode && selectedIds.size > 0 && (
                <div
                    className="fixed bottom-4 left-1/2 z-40 w-[calc(100vw-2rem)] max-w-3xl -translate-x-1/2 rounded-2xl border bg-card shadow-2xl backdrop-blur"
                    role="toolbar"
                    aria-label="Ações em lote"
                >
                    <div className="flex flex-wrap items-center gap-2 p-3">
                        <span className="text-sm font-semibold">
                            {selectedIds.size} {selectedIds.size === 1 ? 'selecionado' : 'selecionados'}
                        </span>
                        <div className="ml-auto flex flex-wrap items-center gap-1.5">
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => runBulkAction('favorites', 'add')}
                                disabled={bulkProcessing}
                                className="gap-1.5"
                            >
                                {bulkProcessing ? <Loader2 className="size-3.5 animate-spin" /> : <Heart className="size-3.5" />}
                                Favoritar
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => runBulkAction('favorites', 'remove')}
                                disabled={bulkProcessing}
                                className="gap-1.5"
                            >
                                <HeartOff className="size-3.5" />
                                Desfavoritar
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => runBulkAction('roster', 'add')}
                                disabled={bulkProcessing}
                                className="gap-1.5"
                            >
                                <PlusCircle className="size-3.5" />
                                Add no roster
                            </Button>
                            <Button
                                size="sm"
                                variant="outline"
                                onClick={() => runBulkAction('roster', 'remove')}
                                disabled={bulkProcessing}
                                className="gap-1.5 text-rose-600 hover:bg-rose-500/10 dark:text-rose-300"
                            >
                                <MinusCircle className="size-3.5" />
                                Remover roster
                            </Button>
                            <Button size="sm" variant="ghost" onClick={exitSelectionMode} className="gap-1.5">
                                <X className="size-3.5" />
                                <span className="sr-only sm:not-sr-only">Cancelar</span>
                            </Button>
                        </div>
                    </div>
                    <p className="border-t px-3 py-1.5 text-[10px] text-muted-foreground">
                        Pressione <kbd className="rounded border bg-muted px-1 font-mono">Esc</kbd> para cancelar.
                    </p>
                </div>
            )}
        </AppLayout>
    );
}
