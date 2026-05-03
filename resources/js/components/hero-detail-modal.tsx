import { router, usePage } from '@inertiajs/react';
import axios from 'axios';
import {
    BarChart3,
    BookText,
    ExternalLink,
    Flame,
    Gem,
    Hotel,
    Loader2,
    Lock,
    Pencil,
    Plus,
    Save,
    Sparkles,
    Trash2,
    Unlock,
    Users,
    X,
} from 'lucide-react';
import { type FormEvent, useCallback, useEffect, useState } from 'react';

import { MarkdownContent } from '@/components/markdown-content';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/components/ui/confirm-dialog';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Tabs, TabsContent, TabsList, TabsTrigger } from '@/components/ui/tabs';
import {
    ASCENSION_STAGES,
    ascensionStagesForTier,
    CLASS_ICON,
    CLASS_LABEL,
    FACTION_THEME,
    HERO_TYPE_BADGE,
    HERO_TYPE_ICON,
    HERO_TYPE_LABEL,
    TIER_THEME,
    localized,
    tierFromAscension,
} from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { Locale, SharedData } from '@/types';
import type {
    Artifact,
    ClassCode,
    Faction,
    FactionCode,
    Hero,
    HeroClass,
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

interface DetailsPayload {
    hero: Hero & { faction: Faction; hero_class: HeroClass; tier: Tier };
    userHero: UserHero | null;
    synergies: SynergyEntry[];
    availableArtifacts: Artifact[];
}

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
};

export function HeroDetailModal({ slug, open, onOpenChange, onNavigate }: HeroDetailModalProps) {
    const [data, setData] = useState<DetailsPayload | null>(null);
    const [loading, setLoading] = useState(false);
    const [editingRoster, setEditingRoster] = useState(false);
    const [editingStats, setEditingStats] = useState(false);

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
            setEditingRoster(false);
            setEditingStats(false);
            return;
        }
        setLoading(true);
        setEditingRoster(false);
        setEditingStats(false);
        axios
            .get(`/heroes/${slug}/details`)
            .then((res) => setData(res.data as DetailsPayload))
            .catch(() => setData(null))
            .finally(() => setLoading(false));
    }, [slug, open]);

    const handleClose = (next: boolean) => {
        if (!next) {
            // Sincroniza roster do índice quando o modal fecha
            router.reload({ only: ['roster'] });
        }
        onOpenChange(next);
    };

    const onMutation = async () => {
        await refetch();
        // Refresh do roster do parent para manter cards "no roster" coerentes
        router.reload({ only: ['roster'] });
    };

    if (!open) return null;

    return (
        <Dialog open={open} onOpenChange={handleClose}>
            <DialogContent className="max-h-[90vh] max-w-4xl overflow-y-auto p-0">
                {loading && (
                    <div className="flex h-64 items-center justify-center">
                        <Loader2 className="size-8 animate-spin text-muted-foreground" />
                    </div>
                )}
                {!loading && data && (
                    <HeroDetailContent
                        data={data}
                        locale={locale}
                        isAdmin={isAdmin}
                        editingRoster={editingRoster}
                        editingStats={editingStats}
                        onEditRoster={() => setEditingRoster(true)}
                        onCancelEditRoster={() => setEditingRoster(false)}
                        onEditStats={() => setEditingStats(true)}
                        onCancelEditStats={() => setEditingStats(false)}
                        onMutation={onMutation}
                        onClose={() => handleClose(false)}
                        onNavigate={onNavigate}
                    />
                )}
            </DialogContent>
        </Dialog>
    );
}

interface ContentProps {
    data: DetailsPayload;
    locale: Locale;
    isAdmin: boolean;
    editingRoster: boolean;
    editingStats: boolean;
    onEditRoster: () => void;
    onCancelEditRoster: () => void;
    onEditStats: () => void;
    onCancelEditStats: () => void;
    onMutation: () => Promise<void> | void;
    onClose: () => void;
    onNavigate?: (slug: string) => void;
}

function HeroDetailContent({
    data,
    locale,
    isAdmin,
    editingRoster,
    editingStats,
    onEditRoster,
    onCancelEditRoster,
    onEditStats,
    onCancelEditStats,
    onMutation,
    onClose,
    onNavigate,
}: ContentProps) {
    const { hero, userHero, synergies, availableArtifacts } = data;
    const { confirm, dialog: confirmDialog } = useConfirm();

    const factionTheme = FACTION_THEME[hero.faction.code as FactionCode];
    const ClassIcon = CLASS_ICON[hero.hero_class.code as ClassCode];
    const displayTierCode: TierCode = userHero ? tierFromAscension(userHero.ascension_value) : (hero.tier.code as TierCode);
    const tierTheme = TIER_THEME[displayTierCode];

    const owned = userHero !== null;
    const awakened = hero.is_awakened;
    const stats = hero.stats ?? null;
    const abilities = hero.abilities ?? [];
    const localizedLore = localized(hero, 'lore', locale);
    const hasLore = Boolean(localizedLore && localizedLore.trim().length > 0);
    const furnitureAbility = hero.furniture_ability;
    const userFurniture = userHero?.furniture ?? 0;
    const f3Unlocked = userFurniture >= 3;
    const f9Unlocked = userFurniture >= 9;

    const handleRemoveFromRoster = async () => {
        if (!userHero) return;
        const ok = await confirm({
            title: `Remover ${hero.name} do roster?`,
            description: 'Os atributos cadastrados (nível, ascensão, Assinatura/Móveis/Inscrições) serão perdidos.',
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
            <DialogTitle className="sr-only">{hero.name}</DialogTitle>

            {/* Header card */}
            <div
                className={cn(
                    'flex flex-col gap-4 overflow-hidden border-b p-4 sm:flex-row',
                    factionTheme.cardGradient,
                    awakened ? 'border-amber-400/90 shadow-[0_0_18px_-2px_rgba(251,191,36,0.55)]' : factionTheme.cardBorder,
                )}
            >
                <div className={cn('relative flex aspect-[3/4] w-full max-w-[160px] shrink-0 items-end justify-center overflow-hidden rounded-lg', factionTheme.cardGradient)}>
                    {hero.portrait_url ? (
                        <img src={hero.portrait_url} alt={hero.name} className="size-full object-contain object-bottom" />
                    ) : (
                        <div className="flex size-full items-center justify-center text-xs text-muted-foreground">sem imagem</div>
                    )}
                </div>

                <div className="flex flex-1 flex-col gap-3">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <div>
                            <h1
                                className={cn(
                                    'text-2xl font-semibold leading-tight',
                                    awakened && 'bg-gradient-to-r from-amber-400 via-orange-400 to-rose-500 bg-clip-text text-transparent',
                                )}
                            >
                                {hero.name}
                            </h1>
                            {hero.title && <p className="text-sm text-muted-foreground">{hero.title}</p>}
                        </div>

                        <Button variant="ghost" size="icon" onClick={onClose} className="size-7">
                            <X className="size-4" />
                        </Button>
                    </div>

                    <div className="flex flex-wrap items-center gap-1.5">
                        <Badge className={cn('gap-1', factionTheme.headerBg, factionTheme.headerText)}>{hero.faction.name_pt}</Badge>
                        <Badge variant="outline" className="gap-1">
                            <ClassIcon className="size-3" />
                            {CLASS_LABEL[hero.hero_class.code as ClassCode]}
                        </Badge>
                        {hero.type && (() => {
                            const TypeIcon = HERO_TYPE_ICON[hero.type];
                            return (
                                <Badge variant="outline" className={cn('gap-1', HERO_TYPE_BADGE[hero.type])}>
                                    <TypeIcon className="size-3" />
                                    {HERO_TYPE_LABEL[hero.type]}
                                </Badge>
                            );
                        })()}
                        <Badge className={cn('text-xs', tierTheme.badge)}>{tierTheme.label}</Badge>
                        {awakened && (
                            <Badge className="gap-1 bg-gradient-to-r from-rose-500 via-amber-400 to-rose-500 text-white">
                                <Flame className="size-3" />
                                Desperto
                            </Badge>
                        )}
                        {hero.role_label && <Badge variant="secondary">{hero.role_label}</Badge>}
                    </div>

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

            <Tabs defaultValue="detalhes" className="flex flex-col gap-3 p-4">
                <TabsList className="self-start">
                    <TabsTrigger value="detalhes">Detalhes</TabsTrigger>
                    <TabsTrigger value="historia">História</TabsTrigger>
                </TabsList>

                <TabsContent value="detalhes" className="flex flex-col gap-4">
                    {/* Roster Inline */}
                    {editingRoster ? (
                        <RosterFormInline
                            hero={hero}
                            userHero={userHero}
                            availableArtifacts={availableArtifacts}
                            tierCode={hero.tier.code as TierCode}
                            onCancel={onCancelEditRoster}
                            onSaved={async () => {
                                onCancelEditRoster();
                                await onMutation();
                            }}
                        />
                    ) : (
                        <section className="flex flex-col gap-2 rounded-lg border bg-card p-3">
                            <div className="flex items-center justify-between gap-2">
                                <h3 className="text-sm font-semibold">No roster</h3>
                                <div className="flex gap-2">
                                    {owned ? (
                                        <>
                                            <Button size="sm" variant="outline" onClick={onEditRoster}>
                                                <Pencil className="size-3" />
                                                Editar
                                            </Button>
                                            <Button size="sm" variant="outline" onClick={handleRemoveFromRoster} className="border-destructive/40 text-destructive hover:bg-destructive/10">
                                                <Trash2 className="size-3" />
                                                Remover
                                            </Button>
                                        </>
                                    ) : (
                                        <Button size="sm" onClick={onEditRoster}>
                                            <Plus className="size-3" />
                                            Adicionar ao roster
                                        </Button>
                                    )}
                                </div>
                            </div>

                            {owned && userHero ? (
                                <div className="flex flex-wrap items-center gap-1.5 text-xs">
                                    <Badge variant="secondary">Nv {userHero.level}</Badge>
                                    {userHero.ascension_code && <Badge variant="secondary">{userHero.ascension_code.replace(/_/g, ' ')}</Badge>}
                                    {userHero.signature_item > 0 && <Badge variant="outline">Assinatura {userHero.signature_item}</Badge>}
                                    {userHero.furniture > 0 && <Badge variant="outline">Móveis {userHero.furniture}/9</Badge>}
                                    {userHero.engraving > 0 && <Badge variant="outline">Inscrições {userHero.engraving}</Badge>}
                                    {userHero.artifact && (
                                        <Badge variant="outline" className="gap-1">
                                            <Gem className="size-3" />
                                            {userHero.artifact.name}
                                            {userHero.artifact_star_level && ` ${'★'.repeat(userHero.artifact_star_level)}`}
                                        </Badge>
                                    )}
                                </div>
                            ) : (
                                <p className="text-xs text-muted-foreground">Não cadastrado no roster.</p>
                            )}
                        </section>
                    )}

                    {/* Stats */}
                    <section className="flex flex-col gap-2">
                        <h2 className="flex items-center gap-2 text-base font-semibold">
                            <BarChart3 className="size-4 text-primary" />
                            Estatísticas Base
                            {isAdmin && !editingStats && (
                                <Button size="sm" variant="outline" onClick={onEditStats} className="ml-auto h-7">
                                    <Pencil className="size-3" />
                                    Editar
                                </Button>
                            )}
                        </h2>
                        {editingStats ? (
                            <StatsFormInline
                                slug={hero.slug}
                                stats={stats}
                                onCancel={onCancelEditStats}
                                onSaved={async () => {
                                    onCancelEditStats();
                                    await onMutation();
                                }}
                            />
                        ) : stats ? (
                            <StatsGrid stats={stats} />
                        ) : (
                            <div className="rounded-lg border border-dashed p-3 text-center text-xs text-muted-foreground">
                                Estatísticas base ainda não cadastradas.
                            </div>
                        )}
                    </section>

                    {/* Abilities */}
                    <section className="flex flex-col gap-2">
                        <h2 className="flex items-center gap-2 text-base font-semibold">
                            <Sparkles className="size-4 text-primary" />
                            Habilidades
                        </h2>
                        {abilities.length === 0 ? (
                            <div className="rounded-lg border border-dashed p-3 text-center text-xs text-muted-foreground">
                                Habilidades ainda não importadas.
                            </div>
                        ) : (
                            <div className="flex flex-col gap-2">
                                {abilities.map((ability) => {
                                    const abilityName = localized(ability, 'name', locale) ?? ability.name;
                                    const abilityDesc = localized(ability, 'description', locale);
                                    return (
                                        <div key={ability.id} className="rounded-lg border bg-card p-3 text-sm">
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

                    {/* Furniture */}
                    {furnitureAbility && (() => {
                        const faName = localized(furnitureAbility, 'name', locale) ?? furnitureAbility.name;
                        const faF3 = localized(furnitureAbility, 'f3_description', locale);
                        const faF9 = localized(furnitureAbility, 'f9_description', locale);
                        return (
                            <section className="flex flex-col gap-2">
                                <h2 className="flex items-center gap-2 text-base font-semibold">
                                    <Hotel className="size-4 text-primary" />
                                    Habilidade de Móvel
                                    <Badge variant="secondary" className="text-xs">{faName}</Badge>
                                    {owned && <Badge variant="outline" className="text-[10px]">{userFurniture}/9</Badge>}
                                </h2>
                                <div className="grid grid-cols-1 gap-2 md:grid-cols-2">
                                    <div className={cn('rounded-lg border bg-card p-3', owned && f3Unlocked && 'border-emerald-500/40 bg-emerald-500/5')}>
                                        <Badge variant={owned && f3Unlocked ? 'default' : 'secondary'} className="gap-1">
                                            {owned && f3Unlocked ? <Unlock className="size-3" /> : <Lock className="size-3" />}
                                            F3 — 3/9
                                        </Badge>
                                        {faF3 ? <div className="mt-2 text-sm text-muted-foreground"><MarkdownContent>{faF3}</MarkdownContent></div> : <p className="mt-2 text-sm text-muted-foreground">Sem descrição.</p>}
                                    </div>
                                    <div className={cn('rounded-lg border bg-card p-3', owned && f9Unlocked && 'border-amber-400/60 bg-amber-400/5')}>
                                        <Badge variant={owned && f9Unlocked ? 'default' : 'secondary'} className={cn('gap-1', owned && f9Unlocked && 'bg-gradient-to-r from-amber-400 to-orange-500 text-stone-900')}>
                                            {owned && f9Unlocked ? <Unlock className="size-3" /> : <Lock className="size-3" />}
                                            F9 — 9/9
                                        </Badge>
                                        {faF9 ? <div className="mt-2 text-sm text-muted-foreground"><MarkdownContent>{faF9}</MarkdownContent></div> : <p className="mt-2 text-sm text-muted-foreground">Sem descrição.</p>}
                                    </div>
                                </div>
                            </section>
                        );
                    })()}

                    {/* Artifact */}
                    {userHero?.artifact && (
                        <section className="flex flex-col gap-2">
                            <h2 className="flex items-center gap-2 text-base font-semibold">
                                <Gem className="size-4 text-primary" />
                                Artefato Equipado
                                <Badge variant="secondary" className="text-xs uppercase">
                                    {userHero.artifact.kind === 'dura' ? "Dura's" : 'Classe'}
                                </Badge>
                                {userHero.artifact_star_level !== null && (
                                    <Badge className="text-xs">
                                        {'★'.repeat(userHero.artifact_star_level)}
                                        {'☆'.repeat(userHero.artifact.max_stars - userHero.artifact_star_level)}
                                    </Badge>
                                )}
                            </h2>
                            <div className="flex gap-3 rounded-lg border bg-card p-3">
                                {userHero.artifact.portrait_url && (
                                    <img src={userHero.artifact.portrait_url} alt={userHero.artifact.name} className="size-16 shrink-0 rounded-lg object-contain" />
                                )}
                                <div className="min-w-0 flex-1">
                                    <div className="text-sm font-semibold">{userHero.artifact.name}</div>
                                    {userHero.artifact.description && <p className="mt-1 text-xs text-muted-foreground">{userHero.artifact.description}</p>}
                                </div>
                            </div>
                        </section>
                    )}

                    {/* Synergies */}
                    {synergies.length > 0 && (
                        <section className="flex flex-col gap-2">
                            <h2 className="flex items-center gap-2 text-base font-semibold">
                                <Users className="size-4 text-primary" />
                                Sinergias ({synergies.length})
                            </h2>
                            <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4">
                                {synergies.map(({ partner, weight, note }) => {
                                    const partnerFactionTheme = partner.faction ? FACTION_THEME[partner.faction.code as FactionCode] : null;
                                    return (
                                        <button
                                            key={partner.id}
                                            type="button"
                                            onClick={() => onNavigate?.(partner.slug)}
                                            className={cn(
                                                'flex flex-col gap-1 rounded-lg border bg-card p-2 text-left transition-colors hover:border-foreground/30',
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
                                                    <div className="text-[10px] text-muted-foreground">peso {weight}</div>
                                                </div>
                                            </div>
                                        </button>
                                    );
                                })}
                            </div>
                        </section>
                    )}
                </TabsContent>

                <TabsContent value="historia" className="flex flex-col gap-2">
                    <h2 className="flex items-center gap-2 text-base font-semibold">
                        <BookText className="size-4 text-primary" />
                        História
                    </h2>
                    {hasLore && localizedLore ? (
                        <div className="rounded-lg border bg-card p-3">
                            <MarkdownContent>{localizedLore}</MarkdownContent>
                        </div>
                    ) : (
                        <div className="rounded-lg border border-dashed p-3 text-center text-xs text-muted-foreground">
                            {locale === 'pt' && hero.lore ? 'Tradução ainda não disponível.' : 'História ainda não importada.'}
                        </div>
                    )}
                </TabsContent>
            </Tabs>
        </>
    );
}

function StatsGrid({ stats }: { stats: HeroStats }) {
    const fmt = (v: number | null) => (v === null ? '—' : v.toLocaleString('pt-BR'));
    const order: (keyof typeof STAT_LABELS)[] = ['hp', 'attack', 'defense', 'crit_rate', 'crit_amp', 'accuracy', 'dodge', 'haste'];

    return (
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
            {order.map((key) => (
                <div key={key} className="rounded-lg border bg-card p-2">
                    <div className="text-[10px] text-muted-foreground">{STAT_LABELS[key]}</div>
                    <div className="mt-0.5 text-lg font-semibold tabular-nums">{fmt(stats[key])}</div>
                </div>
            ))}
        </div>
    );
}

interface RosterFormProps {
    hero: Hero & { tier: Tier };
    userHero: UserHero | null;
    availableArtifacts: Artifact[];
    tierCode: TierCode;
    onCancel: () => void;
    onSaved: () => Promise<void> | void;
}

function RosterFormInline({ hero, userHero, availableArtifacts, tierCode, onCancel, onSaved }: RosterFormProps) {
    const tierTheme = TIER_THEME[tierCode];
    const validStages = ascensionStagesForTier(tierTheme.maxLevel);
    const maxLevel = tierTheme.maxLevel;
    const isEditing = userHero !== null;

    const [level, setLevel] = useState<number>(userHero?.level ?? 1);
    const [ascensionCode, setAscensionCode] = useState<string>(userHero?.ascension_code ?? '');
    const [signatureItem, setSignatureItem] = useState<number>(userHero?.signature_item ?? 0);
    const [furniture, setFurniture] = useState<number>(userHero?.furniture ?? 0);
    const [engraving, setEngraving] = useState<number>(userHero?.engraving ?? 0);
    const [artifactId, setArtifactId] = useState<number | null>(userHero?.artifact_id ?? null);
    const [artifactStarLevel, setArtifactStarLevel] = useState<number | null>(userHero?.artifact_star_level ?? null);
    const [notes, setNotes] = useState<string>(userHero?.notes ?? '');
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        const payload = {
            level,
            ascension_code: ascensionCode || null,
            signature_item: signatureItem,
            furniture,
            engraving,
            artifact_id: artifactId,
            artifact_star_level: artifactId ? artifactStarLevel : null,
            notes: notes || null,
        };

        try {
            if (isEditing && userHero) {
                await axios.patch(`/user/heroes/${userHero.id}`, payload);
            } else {
                await axios.post('/user/heroes', { ...payload, hero_id: hero.id });
            }
            await onSaved();
        } catch (err: unknown) {
            const e = err as { response?: { data?: { errors?: Record<string, string[]> } } };
            const validationErrors = e.response?.data?.errors;
            if (validationErrors) {
                const flat: Record<string, string> = {};
                Object.entries(validationErrors).forEach(([k, v]) => {
                    flat[k] = Array.isArray(v) ? v[0] : String(v);
                });
                setErrors(flat);
            }
        } finally {
            setSaving(false);
        }
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-3 rounded-lg border bg-card p-3">
            <div className="flex items-center justify-between">
                <h3 className="text-sm font-semibold">{isEditing ? 'Editar roster' : 'Adicionar ao roster'}</h3>
                <Button type="button" variant="ghost" size="sm" onClick={onCancel} className="h-7">
                    Cancelar
                </Button>
            </div>

            <div className="grid grid-cols-2 gap-3">
                <div className="space-y-1">
                    <Label htmlFor="rh-level">Nível (max {maxLevel})</Label>
                    <Input id="rh-level" type="number" min={1} max={maxLevel} value={level} onChange={(e) => setLevel(Math.min(maxLevel, Math.max(1, Number(e.target.value) || 1)))} />
                    {errors.level && <p className="text-xs text-destructive">{errors.level}</p>}
                </div>
                <div className="space-y-1">
                    <Label htmlFor="rh-asc">Ascensão</Label>
                    <Select value={ascensionCode || 'none'} onValueChange={(v) => setAscensionCode(v === 'none' ? '' : v)}>
                        <SelectTrigger id="rh-asc">
                            <SelectValue placeholder="—" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="none">— não definido —</SelectItem>
                            {validStages.map((stage) => (
                                <SelectItem key={stage.code} value={stage.code}>{stage.label}</SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>

            <div className="grid grid-cols-3 gap-3">
                <div className="space-y-1">
                    <Label htmlFor="rh-si">Assinatura (0-40)</Label>
                    <Input id="rh-si" type="number" min={0} max={40} value={signatureItem} onChange={(e) => setSignatureItem(Math.min(40, Math.max(0, Number(e.target.value) || 0)))} />
                </div>
                <div className="space-y-1">
                    <Label htmlFor="rh-furn">Móveis (0-9)</Label>
                    <Input id="rh-furn" type="number" min={0} max={9} value={furniture} onChange={(e) => setFurniture(Math.min(9, Math.max(0, Number(e.target.value) || 0)))} />
                </div>
                <div className="space-y-1">
                    <Label htmlFor="rh-eng">Inscrições (0-100)</Label>
                    <Input id="rh-eng" type="number" min={0} max={100} value={engraving} onChange={(e) => setEngraving(Math.min(100, Math.max(0, Number(e.target.value) || 0)))} />
                </div>
            </div>

            {availableArtifacts.length > 0 && (
                <div className="grid grid-cols-3 gap-3">
                    <div className="col-span-2 space-y-1">
                        <Label htmlFor="rh-art">Artefato</Label>
                        <Select
                            value={artifactId ? String(artifactId) : 'none'}
                            onValueChange={(v) => {
                                if (v === 'none') {
                                    setArtifactId(null);
                                    setArtifactStarLevel(null);
                                } else {
                                    setArtifactId(Number(v));
                                    if (!artifactStarLevel) setArtifactStarLevel(1);
                                }
                            }}
                        >
                            <SelectTrigger id="rh-art">
                                <SelectValue placeholder="—" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="none">— nenhum —</SelectItem>
                                {availableArtifacts.filter((a) => a.kind === 'dura').map((a) => (
                                    <SelectItem key={a.id} value={String(a.id)}>Dura's · {a.name}</SelectItem>
                                ))}
                                {availableArtifacts.filter((a) => a.kind === 'class').map((a) => (
                                    <SelectItem key={a.id} value={String(a.id)}>Classe · {a.name}</SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    {artifactId && (() => {
                        const selected = availableArtifacts.find((a) => a.id === artifactId);
                        const max = selected?.max_stars ?? 5;
                        return (
                            <div className="space-y-1">
                                <Label htmlFor="rh-art-stars">★ (1-{max})</Label>
                                <Input
                                    id="rh-art-stars"
                                    type="number"
                                    min={1}
                                    max={max}
                                    value={artifactStarLevel ?? 1}
                                    onChange={(e) => setArtifactStarLevel(Math.min(max, Math.max(1, Number(e.target.value) || 1)))}
                                />
                            </div>
                        );
                    })()}
                </div>
            )}

            <div className="space-y-1">
                <Label htmlFor="rh-notes">Notas</Label>
                <textarea
                    id="rh-notes"
                    className="flex min-h-[60px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                    value={notes}
                    onChange={(e) => setNotes(e.target.value)}
                    maxLength={1000}
                    rows={2}
                />
            </div>

            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={onCancel} disabled={saving}>
                    Cancelar
                </Button>
                <Button type="submit" disabled={saving}>
                    {saving ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                    {saving ? 'Salvando...' : 'Salvar'}
                </Button>
            </div>
        </form>
    );
}

interface StatsFormProps {
    slug: string;
    stats: HeroStats | null;
    onCancel: () => void;
    onSaved: () => Promise<void> | void;
}

const STAT_FIELDS: { key: keyof typeof STAT_LABELS; max: number }[] = [
    { key: 'hp', max: 999_999 },
    { key: 'attack', max: 999_999 },
    { key: 'defense', max: 999_999 },
    { key: 'crit_rate', max: 9_999 },
    { key: 'crit_amp', max: 9_999 },
    { key: 'accuracy', max: 9_999 },
    { key: 'dodge', max: 9_999 },
    { key: 'haste', max: 9_999 },
];

function StatsFormInline({ slug, stats, onCancel, onSaved }: StatsFormProps) {
    const init = (v: number | null | undefined) => (v === null || v === undefined ? '' : String(v));

    const [values, setValues] = useState<Record<string, string>>({
        hp: init(stats?.hp),
        attack: init(stats?.attack),
        defense: init(stats?.defense),
        crit_rate: init(stats?.crit_rate),
        crit_amp: init(stats?.crit_amp),
        accuracy: init(stats?.accuracy),
        dodge: init(stats?.dodge),
        haste: init(stats?.haste),
    });
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        const payload: Record<string, number | null> = {};
        Object.entries(values).forEach(([k, v]) => {
            payload[k] = v === '' ? null : Number(v);
        });

        try {
            await axios.patch(`/admin/heroes/${slug}/stats`, payload);
            await onSaved();
        } catch (err: unknown) {
            const e = err as { response?: { data?: { errors?: Record<string, string[]> } } };
            const validationErrors = e.response?.data?.errors;
            if (validationErrors) {
                const flat: Record<string, string> = {};
                Object.entries(validationErrors).forEach(([k, v]) => {
                    flat[k] = Array.isArray(v) ? v[0] : String(v);
                });
                setErrors(flat);
            }
        } finally {
            setSaving(false);
        }
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-3 rounded-lg border bg-card p-3">
            <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                {STAT_FIELDS.map(({ key, max }) => (
                    <div key={key} className="space-y-1">
                        <Label htmlFor={`stat-${key}`} className="text-xs">{STAT_LABELS[key]}</Label>
                        <Input
                            id={`stat-${key}`}
                            type="number"
                            min={0}
                            max={max}
                            value={values[key]}
                            onChange={(e) => setValues((s) => ({ ...s, [key]: e.target.value }))}
                            placeholder="—"
                            className="h-8"
                        />
                        {errors[key] && <p className="text-[10px] text-destructive">{errors[key]}</p>}
                    </div>
                ))}
            </div>
            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" size="sm" onClick={onCancel} disabled={saving}>
                    Cancelar
                </Button>
                <Button type="submit" size="sm" disabled={saving}>
                    {saving ? <Loader2 className="size-3 animate-spin" /> : <Save className="size-3" />}
                    Salvar
                </Button>
            </div>
        </form>
    );
}
