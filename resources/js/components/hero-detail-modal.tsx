import { Link, router, usePage } from '@inertiajs/react';
import axios from 'axios';
import {
    BarChart3,
    BookText,
    Crosshair,
    Crown,
    ExternalLink,
    Flame,
    Footprints,
    Gem,
    Heart,
    Hotel,
    Loader2,
    Lock,
    Package,
    Pencil,
    PenLine,
    Plus,
    ScrollText,
    Shield,
    Shirt,
    Sparkles,
    Star,
    Sword,
    Trash2,
    Trophy,
    Unlock,
    Users,
} from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';

import { CapabilityBadge, CapabilityList } from '@/components/capability-badges';
import { CreateHeroModal } from '@/components/create-hero-modal';
import { HeroSlotCard } from '@/components/hero-slot-card';
import { MarkdownContent } from '@/components/markdown-content';
import { RosterEditModal } from '@/components/roster-edit-modal';
import { StatsEditModal } from '@/components/stats-edit-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/components/ui/confirm-dialog';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    ascendedStars,
    CLASS_ICON,
    CLASS_LABEL,
    FACTION_THEME,
    HERO_TYPE_BADGE,
    HERO_TYPE_ICON,
    HERO_TYPE_LABEL,
    localized,
    TIER_THEME,
    tierFromAscension,
} from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { Locale, SharedData } from '@/types';
import type {
    Artifact,
    AvailableEquipment,
    ClassCode,
    Collection,
    EquippedMythicItem,
    Faction,
    FactionCode,
    Hero,
    HeroClass,
    HeroEngraving,
    HeroFeature,
    HeroFeatureTag,
    HeroRating,
    HeroRatingMode,
    HeroRatingStage,
    HeroRatingTier,
    HeroStats,
    Tier,
    TierCode,
    UserHero,
} from '@/types/afk';

interface SynergyEntry {
    partner: Hero;
    weight: number;
    note: string | null;
}

interface SignatureItemSummary {
    current: number;
    cap: number;
    next_level: number | null;
    next_cost: number | null;
    next_emblem_type: 'primordial' | 'amplifying' | 'faction' | null;
    cumulative_to_cap: number | null;
    levels_remaining: number;
    is_capped: boolean;
}

const EMBLEM_LABEL: Record<NonNullable<SignatureItemSummary['next_emblem_type']>, string> = {
    primordial: 'Primordial',
    amplifying: 'Amplificador',
    faction: 'Facção',
};

interface DetailsPayload {
    hero: Hero & { faction: Faction; hero_class: HeroClass; tier: Tier };
    userHero: UserHero | null;
    synergies: SynergyEntry[];
    availableArtifacts: Artifact[];
    availableCollections: Collection[];
    availableEquipments: AvailableEquipment[];
    ratings: HeroRating[];
    engravings: HeroEngraving[];
    features: HeroFeature[];
    signatureItem: SignatureItemSummary;
    isFavorited: boolean;
    /** Página de Counters com este herói como Equipe inimiga: quem o countera. */
    countersUrl: string | null;
}

const FEATURE_TAG_LABEL: Record<HeroFeatureTag, string> = {
    early_carry: 'Carry Early Game',
    early_support: 'Suporte Early Game',
    early_tank: 'Tank Early Game',
    early_utility: 'Utilidade Early Game',
    pvp_top: 'Top PvP',
    twisted_realm_top: 'Top Realidade Distorcida',
};

const FEATURE_TAG_BADGE: Record<HeroFeatureTag, string> = {
    early_carry: 'border-rose-500/40 bg-rose-500/10 text-rose-700 dark:text-rose-300',
    early_support: 'border-sky-500/40 bg-sky-500/10 text-sky-700 dark:text-sky-300',
    early_tank: 'border-amber-500/40 bg-amber-500/10 text-amber-700 dark:text-amber-300',
    early_utility: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300',
    pvp_top: 'border-purple-500/40 bg-purple-500/10 text-purple-700 dark:text-purple-300',
    twisted_realm_top: 'border-fuchsia-500/40 bg-fuchsia-500/10 text-fuchsia-700 dark:text-fuchsia-300',
};

const RATING_MODE_LABEL: Record<HeroRatingMode, string> = {
    overall: 'Geral',
    pvp: 'PvP',
    pve: 'PvE',
    labyrinth: 'Labirinto',
    twisted_realm: 'Realidade Distorcida',
    temporal_rift: 'Sala do Tempo',
    abyssal_expedition: 'Expedição Abissal',
};

const RATING_STAGE_LABEL: Record<HeroRatingStage, string> = {
    early: 'Cap. 1-30',
    mid: 'Cap. 30-35',
    end: 'Cap. 35+',
    all: 'Geral',
};

const RATING_TIER_BADGE: Record<HeroRatingTier, string> = {
    'S+': 'bg-gradient-to-br from-rose-500 to-amber-400 text-white border-rose-400/60',
    S: 'bg-rose-500/90 text-white border-rose-400/60',
    'A+': 'bg-gradient-to-br from-orange-500 to-amber-500 text-white border-orange-400/60',
    A: 'bg-orange-500/90 text-white border-orange-400/60',
    'B+': 'bg-gradient-to-br from-amber-500 to-yellow-500 text-stone-900 border-amber-400/60',
    B: 'bg-amber-500/90 text-stone-900 border-amber-400/60',
    'C+': 'bg-gradient-to-br from-zinc-500 to-zinc-400 text-white border-zinc-400/60',
    C: 'bg-zinc-500/80 text-white border-zinc-400/60',
    'D+': 'bg-zinc-700/80 text-white border-zinc-600/60',
    D: 'bg-zinc-800/80 text-white border-zinc-700/60',
    F: 'bg-zinc-900/80 text-zinc-300 border-zinc-700/60',
};

const SOURCE_LABEL: Record<string, string> = {
    'afk.guide': 'afk.guide',
    allclash: 'allclash.com',
};

interface HeroDetailModalProps {
    slug: string | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
    onNavigate?: (slug: string) => void;
}

const ABILITY_TYPE_LABEL: Record<string, string> = {
    basic: 'Básica',
    active: 'Ativa',
    passive: 'Passiva',
    ultimate: 'Definitiva',
    signature: 'Assinatura',
    awakened: 'Despertar',
};

const STAT_LABELS: Record<keyof Omit<HeroStats, 'id' | 'hero_id'>, string> = {
    hp: 'HP',
    attack: 'Ataque',
    defense: 'Defesa',
    crit_rate: 'Taxa Crítica',
    crit_amp: 'Amp. Crítica',
    accuracy: 'Precisão',
    dodge: 'Esquiva',
    haste: 'Pressa',
    vitality: 'Vitalidade',
    armor: 'Armadura',
    attack_speed: 'Velocidade de Ataque',
    healing: 'Cura',
    recovery_per_second: 'Recuperação/s',
    energy_recovery_rate: 'Tx. Recuperação Energia',
    crit_block_rate: 'Tx. Bloqueio Crítico',
    crit_damage_resistance: 'Resist. Dano Crítico',
    physical_piercing: 'Perfuração Física',
    magic_piercing: 'Perfuração Mágica',
    physical_resistance: 'Resist. Física',
    magic_resistance: 'Resist. Mágica',
    magic_efficiency: 'Eficiência Mágica',
    magic_suppression: 'Supressão Mágica',
    life_steal: 'Sanguessuga',
    tenacity: 'Tenacidade',
    damage_resistance: 'Resist. a Dano',
    perception: 'Percepção',
    proficiency: 'Competência',
    dispel: 'Dissolver',
    erosion: 'Erosão',
    aid: 'Amparo',
    draconic_amplification: 'Amp. Verbo Dracônico',
    draconic_resistance: 'Resist. Verbo Dracônico',
    arena_damage_boost: 'Reforço Dano Arena',
    arena_damage_reduction: 'Redução Dano Arena',
    trial_damage_boost: 'Reforço Dano Provação',
};

export function HeroDetailModal({ slug, open, onOpenChange, onNavigate }: HeroDetailModalProps) {
    const [data, setData] = useState<DetailsPayload | null>(null);
    const [loading, setLoading] = useState(false);
    const [rosterOpen, setRosterOpen] = useState(false);
    const [statsOpen, setStatsOpen] = useState(false);
    const [favoriting, setFavoriting] = useState(false);

    const toggleFavorite = useCallback(async () => {
        if (!data) return;
        const wasFavorited = data.isFavorited;
        // Otimistic update
        setData({ ...data, isFavorited: !wasFavorited });
        setFavoriting(true);
        try {
            if (wasFavorited) {
                await axios.delete(`/heroes/${data.hero.id}/favorite`);
            } else {
                await axios.post(`/heroes/${data.hero.id}/favorite`);
            }
            // Inertia partial reload pra refrescar favoriteHeroIds na /heroes
            router.reload({ only: ['favoriteHeroIds'] });
        } catch (err) {
            // Rollback otimista em caso de erro
            console.error('Falha ao favoritar:', err);
            setData((curr) => (curr ? { ...curr, isFavorited: wasFavorited } : curr));
        } finally {
            setFavoriting(false);
        }
    }, [data]);

    const { auth } = usePage<SharedData>().props;
    const locale = (auth.user?.locale ?? 'en') as Locale;
    const isAdmin = auth.user?.is_admin === true;

    const refetch = useCallback(async () => {
        if (!slug) return;
        const res = await axios.get(`/heroes/${slug}/details`);
        setData(res.data as DetailsPayload);
    }, [slug]);

    useEffect(() => {
        if (!open || !slug) {
            setRosterOpen(false);
            setStatsOpen(false);
            return;
        }
        setLoading(true);
        axios
            .get(`/heroes/${slug}/details`)
            .then((res) => setData(res.data as DetailsPayload))
            .catch(() => setData(null))
            .finally(() => setLoading(false));
    }, [slug, open]);

    const handleClose = (next: boolean) => {
        if (!next) router.reload({ only: ['roster'] });
        onOpenChange(next);
    };

    const onMutation = async () => {
        await refetch();
        router.reload({ only: ['roster'] });
    };

    if (!open) return null;

    return (
        <>
            <Dialog open={open} onOpenChange={handleClose}>
                {/* grid-cols-[minmax(0,1fr)] força a coluna do DialogContent (display: grid)
                    a respeitar o max-w-5xl. Sem isso, conteúdo com min-content maior que
                    1024px expandiria o grid e criaria scroll horizontal. */}
                <DialogContent className="max-h-[92vh] max-w-5xl grid-cols-[minmax(0,1fr)] overflow-y-auto p-0">
                    <DialogTitle className="sr-only">{data?.hero.name ?? 'Detalhes do herói'}</DialogTitle>

                    {loading && (
                        <div className="flex h-64 items-center justify-center">
                            <Loader2 className="text-muted-foreground size-8 animate-spin" />
                        </div>
                    )}
                    {!loading && data && (
                        <HeroDetailContent
                            data={data}
                            locale={locale}
                            isAdmin={isAdmin}
                            onEditRoster={() => setRosterOpen(true)}
                            onEditStats={() => setStatsOpen(true)}
                            onMutation={onMutation}
                            onNavigate={onNavigate}
                            onToggleFavorite={toggleFavorite}
                            favoriting={favoriting}
                        />
                    )}
                </DialogContent>
            </Dialog>

            {data && (
                <>
                    <RosterEditModal
                        open={rosterOpen}
                        onOpenChange={setRosterOpen}
                        hero={data.hero}
                        userHero={data.userHero}
                        availableArtifacts={data.availableArtifacts}
                        availableCollections={data.availableCollections}
                        availableEquipments={data.availableEquipments}
                        onSaved={onMutation}
                    />
                    <StatsEditModal
                        open={statsOpen}
                        onOpenChange={setStatsOpen}
                        hero={data.hero}
                        stats={data.hero.stats ?? null}
                        onSaved={onMutation}
                    />
                </>
            )}
        </>
    );
}

interface ContentProps {
    data: DetailsPayload;
    locale: Locale;
    isAdmin: boolean;
    onEditRoster: () => void;
    onEditStats: () => void;
    onMutation: () => Promise<void> | void;
    onNavigate?: (slug: string) => void;
    onToggleFavorite: () => void;
    favoriting: boolean;
}

function HeroDetailContent({ data, locale, isAdmin, onEditRoster, onEditStats, onMutation, onNavigate, onToggleFavorite, favoriting }: ContentProps) {
    const { hero, userHero, synergies, ratings, engravings, features, signatureItem } = data;
    const { confirm, dialog: confirmDialog } = useConfirm();

    const factionTheme = FACTION_THEME[hero.faction.code as FactionCode];
    const ClassIcon = CLASS_ICON[hero.hero_class.code as ClassCode];
    const displayTierCode: TierCode = userHero ? tierFromAscension(userHero.ascension_value) : (hero.tier.code as TierCode);
    const tierTheme = TIER_THEME[displayTierCode];
    const stars = userHero ? ascendedStars(userHero.ascension_value) : { filled: 0, total: 0 };

    const owned = userHero !== null;
    const awakened = hero.is_awakened;
    const stats = hero.stats ?? null;
    const abilities = hero.abilities ?? [];
    const localizedLore = localized(hero, 'lore', locale);
    const hasLore = Boolean(localizedLore && localizedLore.trim().length > 0);
    const furnitureAbility = hero.furniture_ability;
    const userFurniture = userHero?.furniture ?? 0;
    const userEngraving = userHero?.engraving ?? 0;
    const userSI = userHero?.signature_item ?? 0;
    const f3Unlocked = userFurniture >= 3;
    const f9Unlocked = userFurniture >= 9;

    const handleRemoveFromRoster = async () => {
        if (!userHero) return;
        const ok = await confirm({
            title: `Remover ${hero.name} do roster?`,
            description: 'Os atributos cadastrados serão perdidos.',
            confirmLabel: 'Remover',
            variant: 'destructive',
        });
        if (!ok) return;
        await axios.delete(`/user/heroes/${userHero.id}`);
        await onMutation();
    };

    return (
        <>
            {confirmDialog}

            {/* Header centralizado: tier+stars > title > name > badges
                Obs: DialogTitle (sr-only) está no DialogContent. Aqui é só a apresentação visual. */}
            <header className={cn('relative border-b p-4 pr-12', factionTheme.cardGradient)}>
                {/* Botão favorito (Fase 37) — canto superior esquerdo, em paralelo ao close (pr-12 reservado pelo close) */}
                <button
                    type="button"
                    onClick={onToggleFavorite}
                    disabled={favoriting}
                    className={cn(
                        'bg-background/70 absolute top-3 left-3 z-10 grid size-9 place-items-center rounded-full border backdrop-blur transition-colors',
                        data.isFavorited
                            ? 'border-rose-500/40 text-rose-500 hover:bg-rose-500/10'
                            : 'border-border text-muted-foreground hover:text-foreground',
                    )}
                    aria-label={data.isFavorited ? 'Remover dos favoritos' : 'Adicionar aos favoritos'}
                    aria-pressed={data.isFavorited}
                    title={data.isFavorited ? 'Remover dos favoritos' : 'Adicionar aos favoritos'}
                >
                    <Heart className={cn('size-4', data.isFavorited && 'fill-current')} />
                </button>
                <div className="flex flex-col items-center gap-1.5 text-center">
                    {/* Tier + Stars */}
                    <div className="flex items-center gap-2">
                        <Badge className={cn('px-3 py-0.5 text-xs tracking-wide uppercase', tierTheme.badge)}>{tierTheme.label}</Badge>
                        {stars.total > 0 && (
                            <div className="flex gap-0.5">
                                {Array.from({ length: stars.total }).map((_, i) => (
                                    <Star
                                        key={i}
                                        className={cn('size-3.5', i < stars.filled ? 'fill-amber-400 text-amber-400' : 'text-muted-foreground/40')}
                                    />
                                ))}
                            </div>
                        )}
                    </div>

                    {/* Title (subtitle) */}
                    {hero.title && <p className="text-muted-foreground text-xs tracking-wider uppercase">{hero.title}</p>}

                    {/* Hero Name */}
                    <h1
                        className={cn(
                            'max-w-full text-2xl leading-tight font-bold break-words sm:text-3xl',
                            awakened && 'bg-gradient-to-r from-amber-400 via-orange-400 to-rose-500 bg-clip-text text-transparent',
                            !awakened && factionTheme.headerText,
                        )}
                    >
                        {hero.name}
                    </h1>

                    {/* Badges */}
                    <div className="flex flex-wrap items-center justify-center gap-1.5">
                        <Badge className={cn('gap-1', factionTheme.headerBg, factionTheme.headerText)}>{hero.faction.name_pt}</Badge>
                        <Badge variant="outline" className="gap-1">
                            <ClassIcon className="size-3" />
                            {CLASS_LABEL[hero.hero_class.code as ClassCode]}
                        </Badge>
                        {hero.type &&
                            (() => {
                                const TypeIcon = HERO_TYPE_ICON[hero.type];
                                return (
                                    <Badge variant="outline" className={cn('gap-1', HERO_TYPE_BADGE[hero.type])}>
                                        <TypeIcon className="size-3" />
                                        {HERO_TYPE_LABEL[hero.type]}
                                    </Badge>
                                );
                            })()}
                        {awakened && (
                            <Badge className="gap-1 bg-gradient-to-r from-rose-500 via-amber-400 to-rose-500 text-white">
                                <Flame className="size-3" />
                                Desperto
                            </Badge>
                        )}
                        {hero.role_label && <Badge variant="secondary">{hero.role_label}</Badge>}
                    </div>
                    {data.countersUrl && (
                        <Link
                            href={data.countersUrl}
                            title="Quem countera este herói"
                            className="text-muted-foreground hover:text-foreground mt-1 inline-flex items-center gap-1 text-xs underline-offset-2 hover:underline"
                        >
                            <Crosshair className="size-3.5" />
                            Counters deste herói
                        </Link>
                    )}
                </div>
            </header>

            {/* Upper: 3-col paperdoll com slots quadrados */}
            <section className="mx-auto grid w-full max-w-3xl grid-cols-[88px_minmax(0,1fr)_88px] gap-2 p-4 sm:grid-cols-[112px_minmax(0,1fr)_112px] sm:gap-3">
                {/* Esquerda: Arma → Capacete → Assinatura (SI) */}
                <div className="grid grid-cols-1 gap-1.5">
                    <MythicSlotView
                        icon={Sword}
                        label="Arma"
                        equipment={userHero?.weapon_equipment}
                        level={userHero?.weapon_equipment_level}
                        heroFactionId={hero.faction_id}
                    />
                    <MythicSlotView
                        icon={Shield}
                        label="Capacete"
                        equipment={userHero?.helmet_equipment}
                        level={userHero?.helmet_equipment_level}
                        heroFactionId={hero.faction_id}
                    />
                    <HeroSlotCard
                        icon={Crown}
                        label={
                            signatureItem.is_capped
                                ? `SI no cap (+${signatureItem.cap})`
                                : signatureItem.next_cost !== null
                                  ? `Próximo nível: ${signatureItem.next_cost} emblema${signatureItem.next_cost > 1 ? 's' : ''} ${EMBLEM_LABEL[signatureItem.next_emblem_type ?? 'primordial']}` +
                                    (signatureItem.cumulative_to_cap ? ` · ${signatureItem.cumulative_to_cap} até +${signatureItem.cap}` : '')
                                  : `Item de Assinatura (cap +${signatureItem.cap})`
                        }
                        empty={userSI === 0}
                        valueText={userSI > 0 ? `+${userSI}` : undefined}
                        detail={
                            userSI > 0 ? (
                                <span className="text-muted-foreground">
                                    /{signatureItem.cap}
                                    {!signatureItem.is_capped && signatureItem.next_cost !== null && (
                                        <span className="ml-1 text-[8px] text-amber-400/80">· {signatureItem.next_cost}</span>
                                    )}
                                </span>
                            ) : undefined
                        }
                        accentClass={userSI > 0 ? 'border-yellow-500/60' : undefined}
                    />
                </div>

                {/* Centro: portrait + power + stats strip */}
                <div className="flex min-w-0 flex-col items-center gap-3">
                    <div
                        className={cn(
                            'relative aspect-[3/4] w-full overflow-hidden rounded-lg border-2',
                            factionTheme.cardGradient,
                            awakened ? 'border-amber-400/90 shadow-[inset_0_0_12px_rgba(251,191,36,0.45)]' : factionTheme.cardBorder,
                        )}
                    >
                        {hero.portrait_url ? (
                            <img src={hero.portrait_url} alt={hero.name} className="size-full object-contain object-bottom" />
                        ) : (
                            <div className="text-muted-foreground grid size-full place-items-center text-xs">sem imagem</div>
                        )}
                    </div>

                    {/* Power score */}
                    {userHero?.power_rating !== undefined && (
                        <div className="flex flex-col items-center">
                            <span className="text-muted-foreground text-[10px] tracking-wider uppercase">Pontuação</span>
                            <span className="text-2xl font-bold tabular-nums">{userHero.power_rating.toLocaleString('pt-BR')}</span>
                        </div>
                    )}

                    {/* Stats strip */}
                    {owned && userHero ? (
                        <>
                            <div className="bg-muted/30 flex w-full items-center justify-center gap-3 rounded-md border px-3 py-1.5 text-xs">
                                <div className="flex flex-col items-center">
                                    <span className="text-muted-foreground text-[9px] uppercase">Nível</span>
                                    <span className="font-semibold tabular-nums">{userHero.level}</span>
                                </div>
                                {stats && (
                                    <>
                                        <Divider />
                                        <StatColumn label="HP" value={stats.hp} />
                                        <Divider />
                                        <StatColumn label="ATK" value={stats.attack} />
                                        <Divider />
                                        <StatColumn label="DEF" value={stats.defense} />
                                    </>
                                )}
                            </div>

                            {/* Atributos extras: Móveis, Inscrições, Notas */}
                            <div className="flex w-full flex-wrap items-center justify-center gap-1.5">
                                <Chip
                                    icon={Hotel}
                                    label="Móveis"
                                    value={userFurniture > 0 ? `${userFurniture}/9` : '—'}
                                    accent={
                                        f9Unlocked
                                            ? 'border-amber-400/60 bg-amber-400/10'
                                            : f3Unlocked
                                              ? 'border-emerald-500/40 bg-emerald-500/10'
                                              : undefined
                                    }
                                    extra={
                                        userFurniture > 0 && (
                                            <>
                                                <span className={cn('text-[9px]', f3Unlocked && 'text-emerald-600 dark:text-emerald-400')}>
                                                    {f3Unlocked ? '★F3' : '☆F3'}
                                                </span>
                                                <span className={cn('text-[9px]', f9Unlocked && 'text-amber-600 dark:text-amber-400')}>
                                                    {f9Unlocked ? '★F9' : '☆F9'}
                                                </span>
                                            </>
                                        )
                                    }
                                />
                                <Chip
                                    icon={PenLine}
                                    label="Inscrições"
                                    value={userEngraving > 0 ? `+${userEngraving}` : '—'}
                                    accent={userEngraving > 0 ? 'border-blue-500/40 bg-blue-500/10' : undefined}
                                />
                            </div>

                            {/* Fase 40: bloco de notas markdown renderizado quando há conteúdo */}
                            {userHero.notes && userHero.notes.trim().length > 0 && (
                                <div className="bg-muted/30 rounded-lg border px-3 py-2">
                                    <div className="text-muted-foreground mb-1 flex items-center gap-1.5 text-[10px] font-semibold tracking-wide uppercase">
                                        <ScrollText className="size-3" />
                                        Anotações
                                    </div>
                                    <div className="text-xs">
                                        <MarkdownContent>{userHero.notes}</MarkdownContent>
                                    </div>
                                </div>
                            )}
                        </>
                    ) : (
                        <p className="text-muted-foreground text-xs">Não cadastrado no roster.</p>
                    )}

                    {hero.source_url && (
                        <a
                            href={hero.source_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="text-muted-foreground hover:text-foreground inline-flex items-center gap-1 text-[10px]"
                        >
                            <ExternalLink className="size-3" />
                            Fandom Wiki
                        </a>
                    )}
                </div>

                {/* Direita: Peitoral → Bota → Artefato OU Coleção */}
                <div className="grid grid-cols-1 gap-1.5">
                    <MythicSlotView
                        icon={Shirt}
                        label="Peitoral"
                        equipment={userHero?.chest_equipment}
                        level={userHero?.chest_equipment_level}
                        heroFactionId={hero.faction_id}
                    />
                    <MythicSlotView
                        icon={Footprints}
                        label="Botas"
                        equipment={userHero?.boots_equipment}
                        level={userHero?.boots_equipment_level}
                        heroFactionId={hero.faction_id}
                    />
                    {userHero?.artifact ? (
                        <HeroSlotCard
                            icon={Gem}
                            label="Artefato"
                            empty={false}
                            imageUrl={userHero.artifact.portrait_url}
                            accentClass="border-violet-500/60"
                            detail={
                                userHero.artifact_star_level !== null ? (
                                    <span className="text-amber-500">
                                        {'★'.repeat(userHero.artifact_star_level)}
                                        {'☆'.repeat(userHero.artifact.max_stars - userHero.artifact_star_level)}
                                    </span>
                                ) : undefined
                            }
                        />
                    ) : userHero?.collection ? (
                        <HeroSlotCard
                            icon={Package}
                            label="Coleção"
                            empty={false}
                            imageUrl={userHero.collection.portrait_url}
                            accentClass="border-fuchsia-500/60"
                            detail={
                                <div className="text-muted-foreground flex flex-wrap justify-center gap-x-1 text-[9px]">
                                    {userHero.collection.hp_bonus > 0 && <span>HP+{userHero.collection.hp_bonus}</span>}
                                    {userHero.collection.attack_bonus > 0 && <span>ATK+{userHero.collection.attack_bonus}</span>}
                                    {userHero.collection.defense_bonus > 0 && <span>DEF+{userHero.collection.defense_bonus}</span>}
                                </div>
                            }
                        />
                    ) : (
                        <HeroSlotCard icon={Gem} label="Artefato / Coleção" empty />
                    )}
                </div>
            </section>

            {/* Action footer (acima das tabs) */}
            <section className="bg-muted/20 flex flex-wrap items-center justify-center gap-2 border-y px-4 py-3">
                {owned ? (
                    <>
                        <Button size="sm" variant="default" onClick={onEditRoster}>
                            <Pencil className="size-3" />
                            Editar Roster
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={handleRemoveFromRoster}
                            className="border-destructive/40 text-destructive hover:bg-destructive/10"
                        >
                            <Trash2 className="size-3" />
                            Remover
                        </Button>
                    </>
                ) : (
                    <Button size="sm" onClick={onEditRoster}>
                        <Plus className="size-3" />
                        Adicionar ao Roster
                    </Button>
                )}
                {isAdmin && (
                    <Button size="sm" variant="outline" onClick={onEditStats}>
                        <BarChart3 className="size-3" />
                        Editar Stats
                    </Button>
                )}
            </section>

            {/* Lower: Tabs */}
            <section>
                <Tabs defaultValue="detalhes" className="flex flex-col gap-3 p-4">
                    <TabsList className="self-center">
                        <TabsTrigger value="detalhes">Detalhes</TabsTrigger>
                        <TabsTrigger value="historia">História</TabsTrigger>
                    </TabsList>

                    <TabsContent value="detalhes" className="flex min-w-0 flex-col gap-4">
                        {/* Features (destaques) */}
                        {features && features.length > 0 && (
                            <div className="flex flex-wrap gap-1.5">
                                {features.map((f) => (
                                    <Badge
                                        key={f.id}
                                        variant="outline"
                                        className={cn('text-[10px]', FEATURE_TAG_BADGE[f.tag])}
                                        title={f.note ?? undefined}
                                    >
                                        {FEATURE_TAG_LABEL[f.tag]}
                                        {f.note && <span className="ml-1 opacity-70">· {f.note}</span>}
                                    </Badge>
                                ))}
                            </div>
                        )}

                        {/* Stats */}
                        <SectionBlock icon={BarChart3} title="Estatísticas Base">
                            {stats ? <StatsGrid stats={stats} /> : <Empty>Estatísticas base ainda não cadastradas.</Empty>}
                        </SectionBlock>

                        {/* Tier Ratings (multi-source) */}
                        {ratings && ratings.length > 0 && (
                            <SectionBlock icon={Trophy} title="Tier Rating">
                                <RatingsBySource ratings={ratings} />
                            </SectionBlock>
                        )}

                        {/* Eternal Engravings E30/E60 */}
                        {engravings && engravings.length > 0 && (
                            <SectionBlock
                                icon={Gem}
                                title="Eternal Engravings"
                                extra={<span className="text-muted-foreground text-[10px]">E30 / E60</span>}
                            >
                                <EngravingsList engravings={engravings} />
                            </SectionBlock>
                        )}

                        {/* Capacidades (perfil do classificador + correções do admin) */}
                        {hero.effective_capabilities && Object.keys(hero.effective_capabilities).length > 0 && (
                            <SectionBlock
                                icon={Sparkles}
                                title="Capacidades"
                                extra={<span className="text-muted-foreground text-[10px]">lidas das habilidades</span>}
                            >
                                <CapabilityList profile={hero.effective_capabilities} />
                            </SectionBlock>
                        )}

                        {/* Abilities */}
                        <SectionBlock icon={Sparkles} title="Habilidades">
                            {abilities.length === 0 ? (
                                <Empty>Habilidades ainda não importadas.</Empty>
                            ) : (
                                <div className="flex flex-col gap-2">
                                    {abilities.map((ability) => {
                                        const abilityName = localized(ability, 'name', locale) ?? ability.name;
                                        const abilityDesc = localized(ability, 'description', locale);
                                        return (
                                            <div key={ability.id} className="bg-card min-w-0 overflow-hidden rounded-lg border p-3 text-sm">
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <span className="font-semibold">{abilityName}</span>
                                                    <Badge variant="secondary" className="text-[10px]">
                                                        {ABILITY_TYPE_LABEL[ability.type] ?? ability.type}
                                                    </Badge>
                                                    {ability.ascension_unlock && (
                                                        <Badge variant="outline" className="text-[10px]">
                                                            {ability.ascension_unlock}
                                                        </Badge>
                                                    )}
                                                </div>
                                                {abilityDesc && (
                                                    <div className="text-muted-foreground mt-2">
                                                        <MarkdownContent>{abilityDesc}</MarkdownContent>
                                                    </div>
                                                )}
                                                {ability.effects && ability.effects.length > 0 && (
                                                    <div className="mt-2 flex flex-wrap gap-1">
                                                        {ability.effects.map((tag) => (
                                                            <CapabilityBadge key={tag} tag={tag} />
                                                        ))}
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}
                                </div>
                            )}
                        </SectionBlock>

                        {/* Furniture F3/F9 */}
                        {furnitureAbility &&
                            (() => {
                                const faName = localized(furnitureAbility, 'name', locale) ?? furnitureAbility.name;
                                const faF3 = localized(furnitureAbility, 'f3_description', locale);
                                const faF9 = localized(furnitureAbility, 'f9_description', locale);
                                return (
                                    <SectionBlock
                                        icon={Hotel}
                                        title="Habilidade de Móvel"
                                        extra={
                                            <>
                                                <Badge variant="secondary" className="text-xs">
                                                    {faName}
                                                </Badge>
                                                {owned && (
                                                    <Badge variant="outline" className="text-[10px]">
                                                        {userFurniture}/9
                                                    </Badge>
                                                )}
                                            </>
                                        }
                                    >
                                        <div className="grid grid-cols-1 gap-2 md:grid-cols-2">
                                            <div
                                                className={cn(
                                                    'bg-card rounded-lg border p-3',
                                                    owned && f3Unlocked && 'border-emerald-500/40 bg-emerald-500/5',
                                                )}
                                            >
                                                <Badge variant={owned && f3Unlocked ? 'default' : 'secondary'} className="gap-1">
                                                    {owned && f3Unlocked ? <Unlock className="size-3" /> : <Lock className="size-3" />}
                                                    F3 — 3/9
                                                </Badge>
                                                {faF3 ? (
                                                    <div className="text-muted-foreground mt-2 text-sm">
                                                        <MarkdownContent>{faF3}</MarkdownContent>
                                                    </div>
                                                ) : (
                                                    <p className="text-muted-foreground mt-2 text-sm">Sem descrição.</p>
                                                )}
                                            </div>
                                            <div
                                                className={cn(
                                                    'bg-card rounded-lg border p-3',
                                                    owned && f9Unlocked && 'border-amber-400/60 bg-amber-400/5',
                                                )}
                                            >
                                                <Badge
                                                    variant={owned && f9Unlocked ? 'default' : 'secondary'}
                                                    className={cn(
                                                        'gap-1',
                                                        owned && f9Unlocked && 'bg-gradient-to-r from-amber-400 to-orange-500 text-stone-900',
                                                    )}
                                                >
                                                    {owned && f9Unlocked ? <Unlock className="size-3" /> : <Lock className="size-3" />}
                                                    F9 — 9/9
                                                </Badge>
                                                {faF9 ? (
                                                    <div className="text-muted-foreground mt-2 text-sm">
                                                        <MarkdownContent>{faF9}</MarkdownContent>
                                                    </div>
                                                ) : (
                                                    <p className="text-muted-foreground mt-2 text-sm">Sem descrição.</p>
                                                )}
                                            </div>
                                        </div>
                                    </SectionBlock>
                                );
                            })()}

                        {/* Synergies */}
                        {synergies.length > 0 && (
                            <SectionBlock icon={Users} title={`Sinergias (${synergies.length})`}>
                                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5">
                                    {synergies.map(({ partner, weight, note }) => {
                                        const partnerFactionTheme = partner.faction ? FACTION_THEME[partner.faction.code as FactionCode] : null;
                                        return (
                                            <button
                                                key={partner.id}
                                                type="button"
                                                onClick={() => onNavigate?.(partner.slug)}
                                                className={cn(
                                                    'bg-card hover:border-foreground/30 flex flex-col gap-1 rounded-lg border p-2 text-left transition-colors',
                                                    partnerFactionTheme?.cardGradient,
                                                )}
                                                title={note ?? ''}
                                            >
                                                <div className="flex items-center gap-2">
                                                    {partner.portrait_url && (
                                                        <img src={partner.portrait_url} alt="" className="size-9 shrink-0 rounded object-cover" />
                                                    )}
                                                    <div className="min-w-0 flex-1">
                                                        <div className="truncate text-xs font-medium">{partner.name}</div>
                                                        <div className="text-muted-foreground text-[10px]">peso {weight}</div>
                                                    </div>
                                                </div>
                                            </button>
                                        );
                                    })}
                                </div>
                            </SectionBlock>
                        )}
                    </TabsContent>

                    <TabsContent value="historia" className="flex min-w-0 flex-col gap-2">
                        <SectionBlock icon={BookText} title="História">
                            {hasLore && localizedLore ? (
                                <div className="bg-card rounded-lg border p-4">
                                    <MarkdownContent>{localizedLore}</MarkdownContent>
                                </div>
                            ) : (
                                <Empty>{locale === 'pt' && hero.lore ? 'Tradução ainda não disponível.' : 'História ainda não importada.'}</Empty>
                            )}
                        </SectionBlock>
                    </TabsContent>
                </Tabs>
            </section>
        </>
    );
}

interface SectionBlockProps {
    icon: typeof BarChart3;
    title: string;
    extra?: React.ReactNode;
    children: React.ReactNode;
}

function SectionBlock({ icon: Icon, title, extra, children }: SectionBlockProps) {
    return (
        <section className="flex min-w-0 flex-col gap-2">
            <h2 className="flex flex-wrap items-center gap-2 text-base font-semibold">
                <Icon className="text-primary size-4 shrink-0" />
                <span className="break-words">{title}</span>
                {extra}
            </h2>
            {children}
        </section>
    );
}

function Empty({ children }: { children: React.ReactNode }) {
    return <div className="text-muted-foreground rounded-lg border border-dashed p-3 text-center text-xs">{children}</div>;
}

function Divider() {
    return <span className="bg-border h-6 w-px" />;
}

function StatColumn({ label, value }: { label: string; value: number | null }) {
    return (
        <div className="flex flex-col items-center">
            <span className="text-muted-foreground text-[9px] uppercase">{label}</span>
            <span className="font-semibold tabular-nums">{value === null ? '—' : value.toLocaleString('pt-BR')}</span>
        </div>
    );
}

interface ChipProps {
    icon: typeof BarChart3;
    label: string;
    value: React.ReactNode;
    extra?: React.ReactNode;
    accent?: string;
}

function Chip({ icon: Icon, label, value, extra, accent }: ChipProps) {
    return (
        <div className={cn('bg-card flex items-center gap-1.5 rounded-full border px-2 py-1 text-xs', accent)}>
            <Icon className="text-muted-foreground size-3" />
            <span className="text-muted-foreground text-[10px] tracking-wide uppercase">{label}</span>
            {value !== null && <span className="font-semibold tabular-nums">{value}</span>}
            {extra && <span className="flex items-center gap-1">{extra}</span>}
        </div>
    );
}

interface MythicSlotViewProps {
    icon: typeof Sword;
    label: string;
    equipment: EquippedMythicItem | null | undefined;
    level: number | null | undefined;
    heroFactionId: number;
}

const QUALITY_BADGE_CLASS: Record<string, string> = {
    mythic: 'bg-amber-500/20 text-amber-700 dark:text-amber-300 border-amber-500/40',
    mythic_t1: 'bg-orange-500/20 text-orange-700 dark:text-orange-300 border-orange-500/40',
    mythic_t2: 'bg-rose-500/20 text-rose-700 dark:text-rose-300 border-rose-500/40',
    mythic_t3: 'bg-fuchsia-500/20 text-fuchsia-700 dark:text-fuchsia-300 border-fuchsia-500/40',
    mythic_t4: 'bg-gradient-to-br from-fuchsia-500/30 to-amber-500/20 text-fuchsia-700 dark:text-fuchsia-200 border-fuchsia-400/60',
};

function tierShortLabel(quality: string): string {
    if (quality === 'mythic') return 'M';
    return quality.replace('mythic_', '').toUpperCase();
}

/**
 * Slot read-only de Equipamento Mítico para o modal de visualização do hero.
 * Mostra o item equipado com badge de tier + nome compacto + nível.
 */
function MythicSlotView({ icon, label, equipment, level, heroFactionId }: MythicSlotViewProps) {
    if (!equipment) {
        return <HeroSlotCard icon={icon} label={label} empty />;
    }

    const factionMatches = equipment.faction_id === heroFactionId;
    const tierLabel = tierShortLabel(equipment.quality_tier);
    const cleanName = equipment.name_pt.replace(/ (T\d|M)$/, '');
    const currentLevel = level ?? 0;

    return (
        <HeroSlotCard
            icon={icon}
            label={`${label}: ${cleanName} ${tierLabel}`}
            empty={false}
            accentClass={cn(QUALITY_BADGE_CLASS[equipment.quality_tier] ?? '', factionMatches && 'shadow-[inset_0_0_8px_rgba(251,191,36,0.4)]')}
            valueText={
                <span className="flex items-center justify-center gap-0.5">
                    <span className={cn('rounded px-1 text-[9px] font-bold', QUALITY_BADGE_CLASS[equipment.quality_tier] ?? '')}>{tierLabel}</span>
                </span>
            }
            detail={
                <div className="flex flex-col items-center gap-0.5 text-[9px]">
                    <span className="text-muted-foreground max-w-full truncate" title={cleanName}>
                        {cleanName}
                    </span>
                    {equipment.max_upgrade_level > 0 && (
                        <span className="text-amber-500">
                            +{currentLevel}/{equipment.max_upgrade_level}
                        </span>
                    )}
                    {factionMatches && (
                        <span className="rounded bg-amber-500/20 px-1 text-[8px] font-bold text-amber-600 dark:text-amber-400">+30%</span>
                    )}
                </div>
            }
        />
    );
}

function StatsGrid({ stats }: { stats: HeroStats }) {
    const fmt = (v: number | null) => (v === null ? '—' : v.toLocaleString('pt-BR'));

    // Mostra todos os campos cadastrados (não-null) — esconde os vazios pra não poluir.
    const allKeys = Object.keys(STAT_LABELS) as (keyof typeof STAT_LABELS)[];
    const filledKeys = allKeys.filter((k) => stats[k] !== null && stats[k] !== undefined);

    if (filledKeys.length === 0) {
        return null;
    }

    return (
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
            {filledKeys.map((key) => (
                <div key={key} className="bg-card rounded-lg border p-2">
                    <div className="text-muted-foreground line-clamp-1 text-[10px]" title={STAT_LABELS[key]}>
                        {STAT_LABELS[key]}
                    </div>
                    <div className="mt-0.5 text-base font-semibold tabular-nums">{fmt(stats[key])}</div>
                </div>
            ))}
        </div>
    );
}

function EngravingsList({ engravings }: { engravings: HeroEngraving[] }) {
    const e30 = engravings.find((e) => e.level === 'e30');
    const e60 = engravings.find((e) => e.level === 'e60');
    const url = engravings.find((e) => e.source_url)?.source_url ?? null;

    const Block = ({ title, eng, accent }: { title: string; eng: HeroEngraving | undefined; accent: string }) => {
        if (!eng) return null;
        return (
            <div className={cn('rounded-lg border p-3', accent)}>
                <div className="mb-1.5 flex items-center justify-between gap-2">
                    <div className="flex items-center gap-2">
                        <Badge variant="default" className="font-mono text-[10px]">
                            {title}
                        </Badge>
                        <span className="text-sm font-semibold">{eng.label}</span>
                    </div>
                </div>
                <p className="text-muted-foreground text-xs">{eng.description}</p>
            </div>
        );
    };

    return (
        <div className="flex flex-col gap-2">
            {url && (
                <div className="flex justify-end">
                    <a
                        href={url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="text-muted-foreground hover:text-foreground flex items-center gap-1 text-[10px]"
                    >
                        <ExternalLink className="size-3" />
                        afk.guide
                    </a>
                </div>
            )}
            <div className="grid grid-cols-1 gap-2 md:grid-cols-2">
                <Block title="E30" eng={e30} accent="border-sky-500/40 bg-sky-500/5" />
                <Block title="E60" eng={e60} accent="border-amber-500/40 bg-amber-500/5" />
            </div>
        </div>
    );
}

function RatingsBySource({ ratings }: { ratings: HeroRating[] }) {
    const bySource = new Map<string, HeroRating[]>();
    for (const r of ratings) {
        const arr = bySource.get(r.source) ?? [];
        arr.push(r);
        bySource.set(r.source, arr);
    }

    const sourceOrder = ['afk.guide', 'allclash'];
    const sortedSources = Array.from(bySource.keys()).sort((a, b) => {
        const ai = sourceOrder.indexOf(a);
        const bi = sourceOrder.indexOf(b);
        return (ai === -1 ? 99 : ai) - (bi === -1 ? 99 : bi);
    });

    return (
        <div className="flex flex-col gap-3">
            {sortedSources.map((source) => {
                const sourceRatings = bySource.get(source) ?? [];
                const url = sourceRatings.find((r) => r.source_url)?.source_url ?? null;
                return (
                    <div key={source} className="bg-card/50 rounded-lg border p-2">
                        <div className="mb-1.5 flex items-center justify-between">
                            <span className="text-muted-foreground text-[10px] font-semibold tracking-wide uppercase">
                                {SOURCE_LABEL[source] ?? source}
                            </span>
                            {url && (
                                <a
                                    href={url}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="text-muted-foreground hover:text-foreground flex items-center gap-1 text-[10px]"
                                >
                                    <ExternalLink className="size-3" />
                                    fonte
                                </a>
                            )}
                        </div>
                        <RatingsGrid ratings={sourceRatings} />
                    </div>
                );
            })}
        </div>
    );
}

function RatingsGrid({ ratings }: { ratings: HeroRating[] }) {
    const stagesAll: HeroRatingStage[] = ['early', 'mid', 'end', 'all'];
    const modes: HeroRatingMode[] = ['overall', 'pvp', 'pve', 'labyrinth', 'twisted_realm', 'temporal_rift', 'abyssal_expedition'];

    const lookup: Partial<Record<`${HeroRatingMode}|${HeroRatingStage}`, HeroRatingTier>> = {};
    for (const r of ratings) {
        lookup[`${r.mode}|${r.stage}`] = r.tier;
    }

    const stages = stagesAll.filter((s) => modes.some((m) => lookup[`${m}|${s}`] !== undefined));
    const visibleModes = modes.filter((m) => stages.some((s) => lookup[`${m}|${s}`] !== undefined));

    return (
        <div className="overflow-x-auto">
            <table className="w-full min-w-[420px] border-separate border-spacing-1 text-xs">
                <thead>
                    <tr>
                        <th className="text-muted-foreground text-left text-[10px] font-medium tracking-wide uppercase">Modo</th>
                        {stages.map((s) => (
                            <th key={s} className="text-muted-foreground text-center text-[10px] font-medium tracking-wide uppercase">
                                {RATING_STAGE_LABEL[s]}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody>
                    {visibleModes.map((mode) => (
                        <tr key={mode}>
                            <td className="text-foreground py-1 pr-2 text-xs">{RATING_MODE_LABEL[mode]}</td>
                            {stages.map((stage) => {
                                const tier = lookup[`${mode}|${stage}`];
                                return (
                                    <td key={stage} className="text-center">
                                        {tier ? (
                                            <span
                                                className={cn(
                                                    'inline-block min-w-[28px] rounded-md border px-1.5 py-0.5 text-[10px] font-bold tabular-nums',
                                                    RATING_TIER_BADGE[tier],
                                                )}
                                            >
                                                {tier}
                                            </span>
                                        ) : (
                                            <span className="text-muted-foreground text-[10px]">—</span>
                                        )}
                                    </td>
                                );
                            })}
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export { CreateHeroModal };
