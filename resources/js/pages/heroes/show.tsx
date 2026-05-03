import { Head, Link, router, usePage } from '@inertiajs/react';
import { ArrowLeft, BookText, ExternalLink, Flame, Hotel, ListChecks, Lock, Pencil, Plus, Sparkles, Unlock, Users } from 'lucide-react';
import { useState } from 'react';
import { MarkdownContent } from '@/components/markdown-content';
import { RosterModal } from '@/components/roster-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/components/ui/confirm-dialog';
import AppLayout from '@/layouts/app-layout';
import { CLASS_ICON, CLASS_LABEL, FACTION_THEME, TIER_THEME, localized, tierFromAscension } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, Locale, SharedData } from '@/types';
import type {
    ClassCode,
    Faction,
    FactionCode,
    Hero,
    HeroClass,
    Tier,
    TierCode,
    UserHero,
} from '@/types/afk';

interface SynergyEntry {
    partner: Hero;
    weight: number;
    note: string | null;
}

interface PageProps {
    hero: Hero & {
        faction: Faction;
        hero_class: HeroClass;
        tier: Tier;
    };
    userHero: UserHero | null;
    synergies: SynergyEntry[];
    factions: Faction[];
    heroClasses: HeroClass[];
    tiers: Tier[];
}

const ABILITY_TYPE_LABEL: Record<string, string> = {
    basic: 'Básica',
    active: 'Ativa',
    passive: 'Passiva',
    ultimate: 'Definitiva',
    signature: 'Assinatura',
    awakened: 'Despertar',
};

export default function HeroShow({ hero, userHero, synergies, factions, heroClasses, tiers }: PageProps) {
    const [modalOpen, setModalOpen] = useState(false);
    const { confirm, dialog: confirmDialog } = useConfirm();
    const { auth } = usePage<SharedData>().props;
    const locale = (auth.user?.locale ?? 'en') as Locale;

    const factionTheme = FACTION_THEME[hero.faction.code as FactionCode];
    const ClassIcon = CLASS_ICON[hero.hero_class.code as ClassCode];
    const displayTierCode: TierCode = userHero ? tierFromAscension(userHero.ascension_value) : (hero.tier.code as TierCode);
    const tierTheme = TIER_THEME[displayTierCode];
    const tierByCode = tiers.find((t) => t.code === hero.tier.code);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Heróis', href: '/heroes' },
        { title: hero.name, href: `/heroes/${hero.slug}` },
    ];

    const owned = userHero !== null;
    const awakened = hero.is_awakened;

    const handleRemoveFromRoster = async () => {
        if (!userHero) return;
        const ok = await confirm({
            title: `Remover ${hero.name} do roster?`,
            description: 'Os atributos cadastrados (nível, ascensão, Assinatura/Móveis/Inscrições) serão perdidos.',
            confirmLabel: 'Remover',
            variant: 'destructive',
        });
        if (!ok) return;
        router.delete(`/user/heroes/${userHero.id}`, { preserveScroll: true });
    };

    const abilities = hero.abilities ?? [];
    const localizedLore = localized(hero, 'lore', locale);
    const hasLore = Boolean(localizedLore && localizedLore.trim().length > 0);
    const furnitureAbility = hero.furniture_ability;
    const userFurniture = userHero?.furniture ?? 0;
    const f3Unlocked = userFurniture >= 3;
    const f9Unlocked = userFurniture >= 9;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={hero.name} />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center gap-2">
                    <Link href="/heroes" className="text-muted-foreground hover:text-foreground">
                        <ArrowLeft className="size-4" />
                    </Link>
                    <span className="text-xs text-muted-foreground">Catálogo</span>
                </div>

                {/* Header card */}
                <div
                    className={cn(
                        'flex flex-col gap-4 overflow-hidden rounded-xl border-2 bg-card p-4 sm:flex-row',
                        factionTheme.cardGradient,
                        awakened ? 'border-amber-400/90 shadow-[0_0_18px_-2px_rgba(251,191,36,0.55)]' : factionTheme.cardBorder,
                    )}
                >
                    <div className={cn('relative flex aspect-[3/4] w-full max-w-[200px] shrink-0 items-end justify-center overflow-hidden rounded-lg', factionTheme.cardGradient)}>
                        {hero.portrait_url ? (
                            <img
                                src={hero.portrait_url}
                                alt={hero.name}
                                className="size-full object-contain object-bottom"
                            />
                        ) : (
                            <div className="flex size-full items-center justify-center text-xs text-muted-foreground">sem imagem</div>
                        )}
                    </div>

                    <div className="flex flex-1 flex-col gap-3">
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <div>
                                <h1
                                    className={cn(
                                        'text-3xl font-semibold leading-tight',
                                        awakened && 'bg-gradient-to-r from-amber-400 via-orange-400 to-rose-500 bg-clip-text text-transparent',
                                    )}
                                >
                                    {hero.name}
                                </h1>
                                {hero.title && <p className="text-sm text-muted-foreground">{hero.title}</p>}
                            </div>

                            <div className="flex items-center gap-2">
                                {owned ? (
                                    <>
                                        <Button variant="outline" onClick={() => setModalOpen(true)}>
                                            <Pencil className="size-4" />
                                            Editar roster
                                        </Button>
                                        <Button variant="outline" onClick={handleRemoveFromRoster} className="border-destructive/40 text-destructive hover:bg-destructive/10">
                                            Remover
                                        </Button>
                                    </>
                                ) : (
                                    <Button onClick={() => setModalOpen(true)}>
                                        <Plus className="size-4" />
                                        Adicionar ao roster
                                    </Button>
                                )}
                            </div>
                        </div>

                        <div className="flex flex-wrap items-center gap-1.5">
                            <Badge className={cn('gap-1', factionTheme.headerBg, factionTheme.headerText)}>
                                {hero.faction.name_pt}
                            </Badge>
                            <Badge variant="outline" className="gap-1">
                                <ClassIcon className="size-3" />
                                {CLASS_LABEL[hero.hero_class.code as ClassCode]}
                            </Badge>
                            <Badge className={cn('text-xs', tierTheme.badge)}>{tierTheme.label}</Badge>
                            {awakened && (
                                <Badge className="gap-1 bg-gradient-to-r from-rose-500 via-amber-400 to-rose-500 text-white">
                                    <Flame className="size-3" />
                                    Desperto
                                </Badge>
                            )}
                            {hero.role_label && <Badge variant="secondary">{hero.role_label}</Badge>}
                            {hero.gender && (
                                <Badge variant="outline" className="text-xs">
                                    {hero.gender === 'male' ? 'Masc.' : hero.gender === 'female' ? 'Fem.' : 'Outro'}
                                </Badge>
                            )}
                        </div>

                        {owned && userHero && (
                            <div className="flex flex-wrap items-center gap-2 rounded-lg border bg-background/60 p-2 text-xs">
                                <span className="font-medium">No roster:</span>
                                <Badge variant="secondary">Nv {userHero.level}{tierByCode ? `/${tierByCode.max_level}` : ''}</Badge>
                                {userHero.ascension_code && <Badge variant="secondary">{userHero.ascension_code.replace(/_/g, ' ')}</Badge>}
                                {userHero.signature_item > 0 && <Badge variant="outline">Assinatura {userHero.signature_item}</Badge>}
                                {userHero.furniture > 0 && <Badge variant="outline">Móveis {userHero.furniture}/9</Badge>}
                                {userHero.engraving > 0 && <Badge variant="outline">Inscrições {userHero.engraving}</Badge>}
                            </div>
                        )}

                        {hero.source_url && (
                            <a
                                href={hero.source_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                            >
                                <ExternalLink className="size-3" />
                                Ver na Fandom Wiki
                            </a>
                        )}
                    </div>
                </div>

                {/* Abilities */}
                <section className="flex flex-col gap-2">
                    <h2 className="flex items-center gap-2 text-lg font-semibold">
                        <Sparkles className="size-5 text-primary" />
                        Habilidades
                    </h2>
                    {abilities.length === 0 ? (
                        <div className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                            Habilidades ainda não importadas para este herói.
                        </div>
                    ) : (
                        <div className="flex flex-col gap-2">
                            {abilities.map((ability) => {
                                const abilityName = localized(ability, 'name', locale) ?? ability.name;
                                const abilityDesc = localized(ability, 'description', locale);
                                return (
                                <div key={ability.id} className="rounded-lg border bg-card p-3">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-semibold">{abilityName}</span>
                                        <Badge variant="secondary" className="text-[10px]">
                                            {ABILITY_TYPE_LABEL[ability.type] ?? ability.type}
                                        </Badge>
                                        {ability.ascension_unlock && (
                                            <Badge variant="outline" className="text-[10px]">
                                                Desbloqueio: {ability.ascension_unlock}
                                            </Badge>
                                        )}
                                    </div>
                                    {abilityDesc && (
                                        <div className="mt-2 text-muted-foreground">
                                            <MarkdownContent>{abilityDesc}</MarkdownContent>
                                        </div>
                                    )}
                                </div>
                                );
                            })}
                        </div>
                    )}
                </section>

                {/* Lore */}
                <section className="flex flex-col gap-2">
                    <h2 className="flex items-center gap-2 text-lg font-semibold">
                        <BookText className="size-5 text-primary" />
                        História
                    </h2>
                    {hasLore && localizedLore ? (
                        <div className="rounded-xl border bg-card p-4">
                            <MarkdownContent>{localizedLore}</MarkdownContent>
                        </div>
                    ) : (
                        <div className="rounded-xl border border-dashed p-6 text-center text-sm text-muted-foreground">
                            {locale === 'pt' && hero.lore
                                ? 'Tradução ainda não disponível para este herói.'
                                : 'História ainda não importada para este herói.'}
                        </div>
                    )}
                </section>

                {/* Furniture ability */}
                {furnitureAbility && (() => {
                    const faName = localized(furnitureAbility, 'name', locale) ?? furnitureAbility.name;
                    const faF3 = localized(furnitureAbility, 'f3_description', locale);
                    const faF9 = localized(furnitureAbility, 'f9_description', locale);
                    return (
                    <section className="flex flex-col gap-2">
                        <h2 className="flex items-center gap-2 text-lg font-semibold">
                            <Hotel className="size-5 text-primary" />
                            Habilidade de Móvel
                            <Badge variant="secondary" className="text-xs">
                                {faName}
                            </Badge>
                            {owned && (
                                <Badge variant="outline" className="text-[10px]">
                                    {userFurniture}/9 móveis
                                </Badge>
                            )}
                        </h2>

                        <div className="grid grid-cols-1 gap-2 md:grid-cols-2">
                            {/* F3 card */}
                            <div className={cn(
                                'rounded-lg border bg-card p-3',
                                owned && f3Unlocked && 'border-emerald-500/40 bg-emerald-500/5',
                            )}>
                                <div className="mb-2 flex items-center justify-between gap-2">
                                    <Badge variant={owned && f3Unlocked ? 'default' : 'secondary'} className="gap-1">
                                        {owned && f3Unlocked ? <Unlock className="size-3" /> : <Lock className="size-3" />}
                                        F3 — 3/9 Móveis
                                    </Badge>
                                    {owned && f3Unlocked && (
                                        <span className="text-[10px] font-medium text-emerald-600 dark:text-emerald-400">
                                            Desbloqueada
                                        </span>
                                    )}
                                </div>
                                {faF3 ? (
                                    <div className="text-muted-foreground">
                                        <MarkdownContent>{faF3}</MarkdownContent>
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">Sem descrição.</p>
                                )}
                            </div>

                            {/* F9 card */}
                            <div className={cn(
                                'rounded-lg border bg-card p-3',
                                owned && f9Unlocked && 'border-amber-400/60 bg-amber-400/5',
                            )}>
                                <div className="mb-2 flex items-center justify-between gap-2">
                                    <Badge
                                        variant={owned && f9Unlocked ? 'default' : 'secondary'}
                                        className={cn('gap-1', owned && f9Unlocked && 'bg-gradient-to-r from-amber-400 to-orange-500 text-stone-900')}
                                    >
                                        {owned && f9Unlocked ? <Unlock className="size-3" /> : <Lock className="size-3" />}
                                        F9 — 9/9 Móveis
                                    </Badge>
                                    {owned && f9Unlocked && (
                                        <span className="text-[10px] font-medium text-amber-600 dark:text-amber-400">
                                            Desbloqueada
                                        </span>
                                    )}
                                </div>
                                {faF9 ? (
                                    <div className="text-muted-foreground">
                                        <MarkdownContent>{faF9}</MarkdownContent>
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">Sem descrição.</p>
                                )}
                            </div>
                        </div>
                    </section>
                    );
                })()}

                {/* Synergies */}
                {synergies.length > 0 && (
                    <section className="flex flex-col gap-2">
                        <h2 className="flex items-center gap-2 text-lg font-semibold">
                            <Users className="size-5 text-primary" />
                            Sinergias ({synergies.length})
                        </h2>
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                            {synergies.map(({ partner, weight, note }) => {
                                const partnerFactionTheme = partner.faction ? FACTION_THEME[partner.faction.code as FactionCode] : null;
                                return (
                                    <Link
                                        key={partner.id}
                                        href={`/heroes/${partner.slug}`}
                                        className={cn(
                                            'flex flex-col gap-1 rounded-lg border bg-card p-2 transition-colors hover:border-foreground/30',
                                            partnerFactionTheme?.cardGradient,
                                        )}
                                        title={note ?? ''}
                                    >
                                        <div className="flex items-center gap-2">
                                            {partner.portrait_url && (
                                                <img src={partner.portrait_url} alt="" className="size-10 shrink-0 rounded object-cover" />
                                            )}
                                            <div className="min-w-0 flex-1">
                                                <div className="truncate text-sm font-medium">{partner.name}</div>
                                                <div className="text-[10px] text-muted-foreground">peso {weight}</div>
                                            </div>
                                        </div>
                                        {note && (
                                            <p className="line-clamp-2 text-[11px] text-muted-foreground">{note}</p>
                                        )}
                                    </Link>
                                );
                            })}
                        </div>
                    </section>
                )}

                {/* Bottom actions */}
                <section className="rounded-xl border bg-muted/30 p-4">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                            <ListChecks className="size-4" />
                            Use este herói nas suas formações
                        </div>
                        <Button asChild variant="outline">
                            <Link href="/formations">Ir para formações</Link>
                        </Button>
                    </div>
                </section>
            </div>

            <RosterModal
                open={modalOpen}
                onOpenChange={setModalOpen}
                hero={hero}
                factionCode={hero.faction.code as FactionCode}
                tierCode={hero.tier.code as TierCode}
                userHero={userHero ?? undefined}
            />
            {confirmDialog}
        </AppLayout>
    );
}
