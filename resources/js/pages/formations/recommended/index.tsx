import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { ExternalLink, Loader2, Sparkles, Swords } from 'lucide-react';
import { useState } from 'react';
import { PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/components/ui/confirm-dialog';
import AppLayout from '@/layouts/app-layout';
import { FACTION_THEME } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { Faction, FactionCode, Hero, RecommendedFormation, RecommendedFormationMode } from '@/types/afk';

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
    twisted_realm: 'Realidade Distorcida',
    labyrinth: 'Labirinto',
    king_tower: 'Torre do Rei',
    arcane_labyrinth: 'Labirinto Arcano',
};

const MODE_BADGE: Record<RecommendedFormationMode, string> = {
    pvp: 'border-rose-500/40 bg-rose-500/10 text-rose-700 dark:text-rose-300',
    pve: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    twisted_realm: 'border-purple-500/40 bg-purple-500/10 text-purple-700 dark:text-purple-300',
    labyrinth: 'border-sky-500/40 bg-sky-500/10 text-sky-700 dark:text-sky-300',
    king_tower: 'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    arcane_labyrinth: 'border-sky-500/40 bg-sky-500/10 text-sky-700 dark:text-sky-300',
};

export default function RecommendedFormationsIndex({ recommendedFormations, heroesBySlug }: PageProps) {
    const [cloningSlug, setCloningSlug] = useState<string | null>(null);
    const { confirm, dialog: confirmDialog } = useConfirm();

    const handleClone = async (rf: RecommendedFormation) => {
        const ok = await confirm({
            title: `Duplicar '${rf.name}'?`,
            description: 'Uma cópia será criada nas suas formações com os heróis pré-posicionados. Você poderá editar livremente.',
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
                description="Equipes recomendadas curadas de fontes externas (afk.guide). Clique em 'Duplicar' para criar uma cópia editável nas suas formações."
            >
                {recommendedFormations.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-center">
                        <Sparkles className="size-10 text-muted-foreground" />
                        <p className="mt-2 text-sm text-muted-foreground">Nenhum comp recomendado cadastrado.</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        {recommendedFormations.map((rf) => {
                            const cloning = cloningSlug === rf.slug;
                            const heroes = rf.hero_slugs
                                .map((slug) => heroesBySlug[slug])
                                .filter((h): h is Hero & { faction: Faction } => h !== undefined);

                            return (
                                <div key={rf.id} className="flex flex-col gap-3 rounded-xl border bg-card p-4">
                                    <div className="flex items-start justify-between gap-2">
                                        <div className="min-w-0 flex-1">
                                            <h3 className="truncate text-base font-semibold">{rf.name}</h3>
                                            <Badge variant="outline" className={cn('mt-1', MODE_BADGE[rf.mode])}>
                                                {MODE_LABEL[rf.mode]}
                                            </Badge>
                                        </div>
                                        {rf.source_url && (
                                            <a
                                                href={rf.source_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="flex shrink-0 items-center gap-1 text-[10px] text-muted-foreground hover:text-foreground"
                                                title={rf.source_label ?? 'Fonte'}
                                            >
                                                <ExternalLink className="size-3" />
                                                {rf.source_label ?? 'Fonte'}
                                            </a>
                                        )}
                                    </div>

                                    <div className="flex flex-wrap gap-2">
                                        {heroes.map((hero) => {
                                            const factionTheme = FACTION_THEME[hero.faction.code as FactionCode];
                                            return (
                                                <div
                                                    key={hero.id}
                                                    className={cn(
                                                        'flex flex-col items-center gap-1 rounded-lg border-2 p-1.5',
                                                        factionTheme?.cardBorder ?? 'border-border',
                                                        factionTheme?.cardGradient ?? 'bg-muted/30',
                                                    )}
                                                    title={hero.name}
                                                >
                                                    {hero.portrait_url ? (
                                                        <img
                                                            src={hero.portrait_url}
                                                            alt={hero.name}
                                                            className="size-14 rounded-md object-cover object-top"
                                                            loading="lazy"
                                                        />
                                                    ) : (
                                                        <div className="grid size-14 place-items-center rounded-md bg-muted text-[10px] text-muted-foreground">
                                                            ?
                                                        </div>
                                                    )}
                                                    <span className="line-clamp-1 max-w-[64px] text-center text-[10px] font-medium leading-tight">
                                                        {hero.name}
                                                    </span>
                                                </div>
                                            );
                                        })}
                                        {rf.hero_slugs.length > heroes.length && (
                                            <div className="grid size-14 place-items-center rounded-lg border-2 border-dashed border-amber-500/40 bg-amber-500/5 text-[10px] text-amber-600 dark:text-amber-400">
                                                {rf.hero_slugs.length - heroes.length} faltando
                                            </div>
                                        )}
                                    </div>

                                    {rf.description && (
                                        <p className="text-xs text-muted-foreground">{rf.description}</p>
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
                )}
            </PageShell>
        </AppLayout>
    );
}
