import { PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/components/ui/confirm-dialog';
import AppLayout from '@/layouts/app-layout';
import { FACTION_THEME } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { Faction, FactionCode, Hero, RecommendedFormation, RecommendedFormationMode } from '@/types/afk';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { ExternalLink, Gem, Loader2, Sparkles, Swords } from 'lucide-react';
import { useState } from 'react';

interface PageProps {
    recommendedFormations: RecommendedFormation[];
    heroesBySlug: Record<string, Hero & { faction: Faction }>;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Formações', href: '/formations' },
    { title: 'Comps prontos', href: '/formations/recommended' },
];

const MODE_LABEL: Record<RecommendedFormationMode, string> = {
    pvp: 'PvP',
    pve: 'PvE',
    twisted_realm: 'Reino dos Sonhos',
    labyrinth: 'Labirinto',
    king_tower: 'Torre do Rei',
    arcane_labyrinth: 'Labirinto Arcano',
    dragonforge: 'Forja Dracônica',
};

const MODE_BADGE: Record<RecommendedFormationMode, string> = {
    pvp: 'border-rose-500/40 bg-rose-500/10 text-rose-700 dark:text-rose-300',
    pve: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    twisted_realm: 'border-purple-500/40 bg-purple-500/10 text-purple-700 dark:text-purple-300',
    labyrinth: 'border-sky-500/40 bg-sky-500/10 text-sky-700 dark:text-sky-300',
    king_tower: 'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    arcane_labyrinth: 'border-sky-500/40 bg-sky-500/10 text-sky-700 dark:text-sky-300',
    dragonforge: 'border-orange-500/40 bg-orange-500/10 text-orange-700 dark:text-orange-300',
};

const TIER_BADGE: Record<string, string> = {
    'S+': 'bg-gradient-to-br from-rose-500 to-amber-400 text-white',
    S: 'bg-rose-500 text-white',
    A: 'bg-orange-500 text-white',
    B: 'bg-amber-500 text-stone-900',
    C: 'bg-zinc-500 text-white',
    D: 'bg-zinc-700 text-white',
    F: 'bg-zinc-900 text-zinc-300',
    // Resultado em estrelas (Forja Dracônica)
    '3★': 'bg-amber-400 text-stone-900',
    '2★': 'bg-zinc-300 text-stone-900',
    '1★': 'bg-orange-800 text-white',
};

interface HeroChipProps {
    slug: string;
    hero: (Hero & { faction: Faction }) | undefined;
    isCore: boolean;
}

function HeroChip({ slug, hero, isCore }: HeroChipProps) {
    if (!hero) {
        return (
            <div
                className="flex flex-col items-center gap-1 rounded-lg border-2 border-dashed border-amber-500/40 bg-amber-500/5 p-1.5 opacity-60"
                title={`${slug} (não cadastrado)`}
            >
                <div className="bg-muted text-muted-foreground grid size-12 place-items-center rounded-md text-[10px]">?</div>
                <span className="line-clamp-1 max-w-[60px] text-center text-[9px] text-amber-600 dark:text-amber-400">{slug}</span>
            </div>
        );
    }
    const factionTheme = FACTION_THEME[hero.faction.code as FactionCode];
    return (
        <div
            className={cn(
                'flex flex-col items-center gap-1 rounded-lg border-2 p-1.5',
                factionTheme?.cardBorder ?? 'border-border',
                factionTheme?.cardGradient ?? 'bg-muted/30',
                !isCore && 'opacity-70',
            )}
            title={`${hero.name}${isCore ? ' (core)' : ' (flex)'}`}
        >
            {hero.portrait_url ? (
                <img src={hero.portrait_url} alt={hero.name} className="size-12 rounded-md object-cover object-top" loading="lazy" />
            ) : (
                <div className="bg-muted text-muted-foreground grid size-12 place-items-center rounded-md text-[10px]">?</div>
            )}
            <span className="line-clamp-1 max-w-[60px] text-center text-[10px] leading-tight font-medium">{hero.name}</span>
        </div>
    );
}

export default function RecommendedFormationsIndex({ recommendedFormations, heroesBySlug }: PageProps) {
    const [cloningSlug, setCloningSlug] = useState<string | null>(null);
    const [modeFilter, setModeFilter] = useState<'all' | RecommendedFormationMode>('all');
    const modes = [...new Set(recommendedFormations.map((rf) => rf.mode))];
    const visibleFormations = modeFilter === 'all' ? recommendedFormations : recommendedFormations.filter((rf) => rf.mode === modeFilter);
    const { confirm, dialog: confirmDialog } = useConfirm();

    const handleClone = async (rf: RecommendedFormation) => {
        const ok = await confirm({
            title: `Duplicar '${rf.name}'?`,
            description: rf.teams?.length
                ? `Uma cópia será criada nas suas formações com as ${rf.teams.length} equipes pré-posicionadas. Você poderá editar livremente.`
                : 'Uma cópia será criada nas suas formações com os 5 primeiros heróis (core + flex se necessário) pré-posicionados. Você poderá editar livremente.',
            confirmLabel: 'Duplicar',
        });
        if (!ok) return;

        setCloningSlug(rf.slug);
        try {
            await axios.post(`/formations/recommended/${rf.slug}/clone`);
            router.visit('/formations');
        } catch {
            setCloningSlug(null);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            {confirmDialog}
            <Head title="Comps prontos" />

            <PageShell
                title="Comps prontos"
                icon={Sparkles}
                description="Equipes recomendadas curadas do afk.guide. Heróis com borda forte são CORE; com borda esmaecida são FLEX (substituições viáveis). Clique em 'Duplicar' para criar uma cópia editável."
            >
                {recommendedFormations.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-center">
                        <Sparkles className="text-muted-foreground size-10" />
                        <p className="text-muted-foreground mt-2 text-sm">Nenhum comp recomendado cadastrado.</p>
                    </div>
                ) : (
                    <>
                        <div className="mb-4 flex flex-wrap gap-1.5">
                            {(['all', ...modes] as const).map((mode) => (
                                <Button
                                    key={mode}
                                    size="sm"
                                    variant={modeFilter === mode ? 'default' : 'outline'}
                                    onClick={() => setModeFilter(mode)}
                                >
                                    {mode === 'all' ? 'Todos' : MODE_LABEL[mode]}
                                    <span className="ml-1 opacity-60">
                                        {mode === 'all'
                                            ? recommendedFormations.length
                                            : recommendedFormations.filter((rf) => rf.mode === mode).length}
                                    </span>
                                </Button>
                            ))}
                        </div>
                        <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                            {visibleFormations.map((rf) => {
                                const cloning = cloningSlug === rf.slug;
                                const coreSlugs = rf.hero_slugs.slice(0, rf.core_count);
                                const flexSlugs = rf.hero_slugs.slice(rf.core_count);

                                return (
                                    <div key={rf.id} className="bg-card flex flex-col gap-3 rounded-xl border p-4">
                                        <div className="flex flex-wrap items-start justify-between gap-2">
                                            <div className="min-w-0 flex-1">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <h3 className="text-base font-semibold">{rf.name}</h3>
                                                    {rf.tier && (
                                                        <span
                                                            className={cn(
                                                                'rounded px-1.5 py-0.5 text-[10px] font-bold',
                                                                TIER_BADGE[rf.tier] ?? 'bg-muted',
                                                            )}
                                                        >
                                                            {rf.tier}
                                                        </span>
                                                    )}
                                                </div>
                                                <div className="mt-1 flex flex-wrap items-center gap-2">
                                                    <Badge variant="outline" className={cn(MODE_BADGE[rf.mode])}>
                                                        {MODE_LABEL[rf.mode]}
                                                    </Badge>
                                                    {rf.level_deficit && (
                                                        <Badge variant="outline" className="text-[10px]">
                                                            Déficit: {rf.level_deficit}
                                                        </Badge>
                                                    )}
                                                </div>
                                            </div>
                                            {rf.source_url && (
                                                <a
                                                    href={rf.source_url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="text-muted-foreground hover:text-foreground flex shrink-0 items-center gap-1 text-[10px]"
                                                    title={rf.source_label ?? 'Fonte'}
                                                >
                                                    <ExternalLink className="size-3" />
                                                    {rf.source_label ?? 'Fonte'}
                                                </a>
                                            )}
                                        </div>

                                        {rf.teams?.map((team, index) => (
                                            <div key={index}>
                                                <div className="mb-1.5 text-[10px] font-semibold tracking-wide text-orange-700 uppercase dark:text-orange-300">
                                                    Equipe {index + 1}
                                                </div>
                                                <div className="flex flex-wrap gap-2">
                                                    {team.map((slug) => (
                                                        <HeroChip key={slug} slug={slug} hero={heroesBySlug[slug]} isCore={true} />
                                                    ))}
                                                </div>
                                            </div>
                                        ))}

                                        {!rf.teams?.length && coreSlugs.length > 0 && (
                                            <div>
                                                <div className="mb-1.5 text-[10px] font-semibold tracking-wide text-emerald-700 uppercase dark:text-emerald-400">
                                                    Core ({coreSlugs.length})
                                                </div>
                                                <div className="flex flex-wrap gap-2">
                                                    {coreSlugs.map((slug) => (
                                                        <HeroChip key={slug} slug={slug} hero={heroesBySlug[slug]} isCore={true} />
                                                    ))}
                                                </div>
                                            </div>
                                        )}

                                        {!rf.teams?.length && flexSlugs.length > 0 && (
                                            <div>
                                                <div className="text-muted-foreground mb-1.5 text-[10px] font-semibold tracking-wide uppercase">
                                                    Flex (substituições) — {flexSlugs.length}
                                                </div>
                                                <div className="flex flex-wrap gap-2">
                                                    {flexSlugs.map((slug) => (
                                                        <HeroChip key={slug} slug={slug} hero={heroesBySlug[slug]} isCore={false} />
                                                    ))}
                                                </div>
                                            </div>
                                        )}

                                        {rf.description && <p className="text-muted-foreground text-xs">{rf.description}</p>}

                                        {rf.notes && (
                                            <div className="text-muted-foreground rounded-md border-l-2 border-amber-400/60 bg-amber-500/5 px-2 py-1.5 text-[11px] italic">
                                                {rf.notes}
                                            </div>
                                        )}

                                        {rf.artifacts && rf.artifacts.length > 0 && (
                                            <div className="flex flex-wrap items-center gap-1">
                                                <Gem className="text-muted-foreground size-3" />
                                                <span className="text-muted-foreground text-[10px]">Artefatos:</span>
                                                {rf.artifacts.map((a) => (
                                                    <Badge key={a} variant="secondary" className="text-[10px]">
                                                        {a}
                                                    </Badge>
                                                ))}
                                            </div>
                                        )}

                                        <div className="flex justify-end border-t pt-2">
                                            <Button size="sm" onClick={() => handleClone(rf)} disabled={cloning}>
                                                {cloning ? <Loader2 className="size-4 animate-spin" /> : <Swords className="size-4" />}
                                                {cloning ? 'Duplicando...' : 'Duplicar para minhas formações'}
                                            </Button>
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    </>
                )}
            </PageShell>
        </AppLayout>
    );
}
