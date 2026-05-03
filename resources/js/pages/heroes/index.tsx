import { Head } from '@inertiajs/react';
import { Search, X } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { HeroCard } from '@/components/hero-card';
import { HeroDetailModal } from '@/components/hero-detail-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { CLASS_ICON, FACTION_THEME } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type {
    ClassCode,
    Faction,
    FactionCode,
    Hero,
    HeroClass,
    RosterByHeroId,
    Tier,
    TierCode,
} from '@/types/afk';

interface PageProps {
    heroes: Hero[];
    factions: Faction[];
    heroClasses: HeroClass[];
    tiers: Tier[];
    roster: RosterByHeroId;
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Heróis', href: '/heroes' }];

export default function HeroesIndex({ heroes, factions, heroClasses, tiers, roster }: PageProps) {
    const [search, setSearch] = useState('');
    const [factionFilter, setFactionFilter] = useState<Set<number>>(new Set());
    const [classFilter, setClassFilter] = useState<Set<number>>(new Set());
    const [onlyOwned, setOnlyOwned] = useState(false);
    const [openSlug, setOpenSlug] = useState<string | null>(null);

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

    const factionById = useMemo(() => Object.fromEntries(factions.map((f) => [f.id, f])) as Record<number, Faction>, [factions]);
    const classById = useMemo(() => Object.fromEntries(heroClasses.map((c) => [c.id, c])) as Record<number, HeroClass>, [heroClasses]);
    const tierById = useMemo(() => Object.fromEntries(tiers.map((t) => [t.id, t])) as Record<number, Tier>, [tiers]);

    const filteredByFaction = useMemo(() => {
        const term = search.trim().toLowerCase();
        const map = new Map<number, Hero[]>();

        for (const hero of heroes) {
            if (factionFilter.size > 0 && !factionFilter.has(hero.faction_id)) continue;
            if (classFilter.size > 0 && !classFilter.has(hero.hero_class_id)) continue;
            if (onlyOwned && !roster[hero.id]) continue;
            if (term) {
                const haystack = `${hero.name} ${hero.title ?? ''}`.toLowerCase();
                if (!haystack.includes(term)) continue;
            }

            const list = map.get(hero.faction_id) ?? [];
            list.push(hero);
            map.set(hero.faction_id, list);
        }

        return map;
    }, [heroes, search, factionFilter, classFilter, onlyOwned, roster]);

    const totalShown = useMemo(() => {
        let n = 0;
        filteredByFaction.forEach((list) => {
            n += list.length;
        });
        return n;
    }, [filteredByFaction]);

    const ownedCount = Object.keys(roster).length;

    const toggleFromSet = (set: Set<number>, id: number, setter: (s: Set<number>) => void) => {
        const next = new Set(set);
        if (next.has(id)) next.delete(id);
        else next.add(id);
        setter(next);
    };

    const clearFilters = () => {
        setSearch('');
        setFactionFilter(new Set());
        setClassFilter(new Set());
        setOnlyOwned(false);
    };

    const openHero = (hero: Hero) => {
        setOpenSlug(hero.slug);
    };

    const filtersActive = search.length > 0 || factionFilter.size > 0 || classFilter.size > 0 || onlyOwned;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Heróis" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <div>
                        <h1 className="text-2xl font-semibold">Catálogo de Heróis</h1>
                        <p className="text-sm text-muted-foreground">
                            {heroes.length} heróis no catálogo · {ownedCount} no seu roster · {totalShown} exibidos
                        </p>
                    </div>
                </div>

                <div className="sticky top-0 z-10 flex flex-col gap-3 rounded-xl border bg-background/95 p-3 backdrop-blur supports-[backdrop-filter]:bg-background/80">
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="relative min-w-[220px] flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                value={search}
                                onChange={(e) => setSearch(e.target.value)}
                                placeholder="Buscar herói por nome ou título..."
                                className="pl-9"
                            />
                        </div>

                        <Button
                            variant={onlyOwned ? 'default' : 'outline'}
                            size="sm"
                            onClick={() => setOnlyOwned((v) => !v)}
                        >
                            Só meus ({ownedCount})
                        </Button>

                        {filtersActive && (
                            <Button variant="ghost" size="sm" onClick={clearFilters}>
                                <X className="size-3.5" />
                                Limpar
                            </Button>
                        )}
                    </div>

                    <div className="flex flex-wrap items-center gap-1.5">
                        <span className="text-xs font-medium text-muted-foreground">Facção:</span>
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
                        <span className="text-xs font-medium text-muted-foreground">Classe:</span>
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
                </div>

                <div className="flex flex-col gap-6">
                    {factions.map((faction) => {
                        const list = filteredByFaction.get(faction.id) ?? [];
                        if (list.length === 0) return null;

                        const code = faction.code as FactionCode;
                        const theme = FACTION_THEME[code];

                        return (
                            <section key={faction.id} className="flex flex-col gap-3">
                                <div className={cn('flex items-center justify-between rounded-lg border-l-4 px-3 py-2', theme.headerBg, theme.cardBorder)}>
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
                                            />
                                        );
                                    })}
                                </div>
                            </section>
                        );
                    })}

                    {totalShown === 0 && (
                        <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-center">
                            <p className="text-sm text-muted-foreground">Nenhum herói corresponde aos filtros atuais.</p>
                            {filtersActive && (
                                <Button variant="link" onClick={clearFilters}>
                                    Limpar filtros
                                </Button>
                            )}
                        </div>
                    )}
                </div>
            </div>

            <HeroDetailModal
                slug={openSlug}
                open={openSlug !== null}
                onOpenChange={(o) => !o && setOpenSlug(null)}
                onNavigate={(slug) => setOpenSlug(slug)}
            />
        </AppLayout>
    );
}
