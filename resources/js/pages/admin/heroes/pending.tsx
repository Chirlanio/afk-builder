import { CreateHeroModal } from '@/components/create-hero-modal';
import { PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { CLASS_ICON, CLASS_LABEL, FACTION_THEME, TIER_THEME } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { ClassCode, Faction, FactionCode, HeroClass, Tier, TierCode } from '@/types/afk';
import { Head, router } from '@inertiajs/react';
import { Check, ExternalLink, Plus, ShieldCheck, X } from 'lucide-react';
import { useState } from 'react';

interface PendingHero {
    id: number;
    slug: string;
    name: string;
    title: string | null;
    portrait_url: string | null;
    thumbnail_url: string | null;
    source_url: string | null;
    is_active: boolean;
    pending_review: boolean;
    created_at: string;
    faction: { id: number; code: FactionCode; name_pt: string; color_hex: string | null };
    hero_class: { id: number; code: ClassCode; name_pt: string };
    tier: { id: number; code: TierCode; name_pt: string };
}

interface PageProps {
    heroes: PendingHero[];
    factions: Faction[];
    heroClasses: HeroClass[];
    tiers: Tier[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Aprovar heróis', href: '/admin/heroes/pending' }];

export default function HeroesPendingReview({ heroes, factions, heroClasses, tiers }: PageProps) {
    const [busyId, setBusyId] = useState<number | null>(null);
    const [createOpen, setCreateOpen] = useState(false);

    const handleAction = (heroId: number, action: 'approve' | 'reject') => {
        setBusyId(heroId);
        router.patch(
            `/admin/heroes/${heroId}/${action}`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setBusyId(null),
            },
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Aprovar heróis" />

            <PageShell
                title="Heróis aguardando aprovação"
                icon={ShieldCheck}
                description="Heróis novos sincronizados da wiki entram aqui antes de aparecer no catálogo. Aprove pra ativar, rejeite pra ocultar."
                actions={
                    <Button variant="outline" size="sm" onClick={() => setCreateOpen(true)}>
                        <Plus className="size-4" />
                        Adicionar manualmente
                    </Button>
                }
            >
                {heroes.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-center">
                        <ShieldCheck className="text-muted-foreground size-10" />
                        <p className="text-muted-foreground mt-2 text-sm">Nenhum herói pendente. ✓</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {heroes.map((hero) => {
                            const factionTheme = FACTION_THEME[hero.faction.code];
                            const tierTheme = TIER_THEME[hero.tier.code];
                            const ClassIcon = CLASS_ICON[hero.hero_class.code];
                            const isBusy = busyId === hero.id;

                            return (
                                <div key={hero.id} className={cn('bg-card flex gap-3 rounded-xl border p-3', factionTheme.cardBorder)}>
                                    <div className={cn('relative size-24 shrink-0 overflow-hidden rounded-lg', factionTheme.cardGradient)}>
                                        {hero.portrait_url ? (
                                            <img src={hero.portrait_url} alt={hero.name} className="size-full object-contain object-bottom" />
                                        ) : (
                                            <div className="text-muted-foreground grid size-full place-items-center text-xs">sem imagem</div>
                                        )}
                                    </div>

                                    <div className="flex flex-1 flex-col gap-1.5">
                                        <div>
                                            <h3 className="text-sm font-semibold">{hero.name}</h3>
                                            {hero.title && <p className="text-muted-foreground text-xs">{hero.title}</p>}
                                        </div>

                                        <div className="flex flex-wrap items-center gap-1">
                                            <Badge variant="outline" className={cn('gap-1', factionTheme.headerText)}>
                                                {hero.faction.name_pt}
                                            </Badge>
                                            <Badge variant="outline" className="gap-1">
                                                <ClassIcon className="size-3" />
                                                {CLASS_LABEL[hero.hero_class.code]}
                                            </Badge>
                                            <Badge className={cn('text-[10px]', tierTheme.badge)}>{tierTheme.label}</Badge>
                                        </div>

                                        {hero.source_url && (
                                            <a
                                                href={hero.source_url}
                                                target="_blank"
                                                rel="noopener noreferrer"
                                                className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-xs"
                                            >
                                                <ExternalLink className="size-3" />
                                                Fandom Wiki
                                            </a>
                                        )}

                                        <div className="mt-auto flex items-center gap-2 pt-2">
                                            <Button
                                                size="sm"
                                                variant="default"
                                                disabled={isBusy}
                                                onClick={() => handleAction(hero.id, 'approve')}
                                                className="h-8 flex-1"
                                            >
                                                <Check className="size-4" />
                                                Aprovar
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                disabled={isBusy}
                                                onClick={() => handleAction(hero.id, 'reject')}
                                                className="h-8 flex-1"
                                            >
                                                <X className="size-4" />
                                                Rejeitar
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </PageShell>

            <CreateHeroModal open={createOpen} onOpenChange={setCreateOpen} factions={factions} heroClasses={heroClasses} tiers={tiers} />
        </AppLayout>
    );
}
