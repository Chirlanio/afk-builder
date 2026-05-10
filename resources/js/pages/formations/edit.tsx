import { DndContext, DragOverlay, type DragEndEvent, type DragStartEvent, MouseSensor, TouchSensor, useSensor, useSensors } from '@dnd-kit/core';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { Copy, History, Link2, Loader2, MoreHorizontal, PawPrint, RotateCcw, Save, Share2, Sparkles, Target, Trash2, Users } from 'lucide-react';
import { useCallback, useMemo, useState } from 'react';
import { HeroChip } from '@/components/formation-editor/hero-chip';
import { HeroPool } from '@/components/formation-editor/hero-pool';
import { TeamRow, type TeamSlotsState } from '@/components/formation-editor/team-row';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/components/ui/confirm-dialog';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Sheet, SheetContent, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import AppLayout from '@/layouts/app-layout';
import { FACTION_THEME } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type {
    Beast,
    ChallengeType,
    ClassCode,
    Faction,
    FactionCode,
    Formation,
    FormationTeam,
    Hero,
    HeroClass,
    RecommendedMatch,
    Tier,
    TierCode,
} from '@/types/afk';

interface PageProps {
    formation: Formation & {
        challenge_type: ChallengeType;
        teams: FormationTeam[];
    };
    heroPool: Hero[];
    beasts: Beast[];
    factions: Faction[];
    heroClasses: HeroClass[];
    tiers: Tier[];
    recommendedMatches?: Record<number, RecommendedMatch[]>;
}

function teamsFromFormation(teams: FormationTeam[], teamsCount: number): TeamSlotsState[] {
    const byPosition = new Map(teams.map((t) => [t.position, t]));

    return Array.from({ length: teamsCount }, (_, i) => {
        const position = i + 1;
        const team = byPosition.get(position);
        const slots = Array.from({ length: 5 }, (_, slotIdx) => {
            const slotPosition = slotIdx + 1;
            const teamHero = team?.team_heroes?.find((h) => h.slot === slotPosition);
            return { heroId: teamHero?.hero_id ?? null };
        });
        return {
            position,
            name: team?.name ?? null,
            slots,
        };
    });
}

export default function FormationsEdit({ formation, heroPool, beasts, factions, heroClasses, tiers, recommendedMatches }: PageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Formações', href: '/formations' },
        { title: formation.name, href: `/formations/${formation.id}/edit` },
    ];

    const heroById = useMemo(() => Object.fromEntries(heroPool.map((h) => [h.id, h])) as Record<number, Hero>, [heroPool]);
    const factionById = useMemo(() => Object.fromEntries(factions.map((f) => [f.id, f])) as Record<number, Faction>, [factions]);
    const classById = useMemo(() => Object.fromEntries(heroClasses.map((c) => [c.id, c])) as Record<number, HeroClass>, [heroClasses]);
    const tierById = useMemo(() => Object.fromEntries(tiers.map((t) => [t.id, t])) as Record<number, Tier>, [tiers]);

    const [name, setName] = useState(formation.name);
    const [beastId, setBeastId] = useState<number | null>(formation.beast_id ?? null);
    const [teams, setTeams] = useState<TeamSlotsState[]>(() => teamsFromFormation(formation.teams, formation.teams_count));
    const [isDirty, setIsDirty] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [suggestingTeamPos, setSuggestingTeamPos] = useState<number | null>(null);
    const [isSuggestingAll, setIsSuggestingAll] = useState(false);
    const [shareToken, setShareToken] = useState<string | null>(formation.share_token);
    const [shareDialogOpen, setShareDialogOpen] = useState(false);
    const [isSharing, setIsSharing] = useState(false);
    const [copySuccess, setCopySuccess] = useState(false);
    const [historyDialogOpen, setHistoryDialogOpen] = useState(false);
    const [versions, setVersions] = useState<Array<{ id: number; created_at: string | null; team_count: number; hero_count: number; name: string | null }>>([]);
    const [isLoadingVersions, setIsLoadingVersions] = useState(false);
    const [poolSheetOpen, setPoolSheetOpen] = useState(false);
    const { confirm, dialog: confirmDialog } = useConfirm();
    const [activeDragHeroId, setActiveDragHeroId] = useState<number | null>(null);

    const enforceUnique = formation.challenge_type.teams_must_be_unique;

    const placedHeroIds = useMemo(() => {
        const set = new Set<number>();
        for (const team of teams) {
            for (const slot of team.slots) {
                if (slot.heroId !== null) set.add(slot.heroId);
            }
        }
        return set;
    }, [teams]);

    const sensors = useSensors(
        useSensor(MouseSensor, { activationConstraint: { distance: 5 } }),
        useSensor(TouchSensor, { activationConstraint: { delay: 150, tolerance: 5 } }),
    );

    const updateSlot = useCallback((teams: TeamSlotsState[], teamPos: number, slotPos: number, heroId: number | null): TeamSlotsState[] => {
        return teams.map((team) =>
            team.position === teamPos
                ? {
                      ...team,
                      slots: team.slots.map((slot, idx) => (idx + 1 === slotPos ? { heroId } : slot)),
                  }
                : team,
        );
    }, []);

    const removeFromSlot = useCallback(
        (teamPos: number, slotPos: number) => {
            setTeams((prev) => updateSlot(prev, teamPos, slotPos, null));
            setIsDirty(true);
        },
        [updateSlot],
    );

    const handleDragStart = (event: DragStartEvent) => {
        const data = event.active.data.current as { kind: string; heroId: number | null } | undefined;
        setActiveDragHeroId(data?.heroId ?? null);
        // Em mobile, fechar o sheet do pool ao começar drag — assim o usuário vê
        // os slots-alvo enquanto arrasta o herói pra cima deles.
        setPoolSheetOpen(false);
    };

    const handleDragEnd = (event: DragEndEvent) => {
        setActiveDragHeroId(null);
        const { active, over } = event;
        if (!over) return;

        const sourceData = active.data.current as { kind: 'pool' | 'slot'; heroId: number | null; teamPosition?: number; slotPosition?: number } | undefined;
        const targetData = over.data.current as { kind: 'slot'; teamPosition: number; slotPosition: number } | undefined;
        if (!sourceData || targetData?.kind !== 'slot') return;

        const targetTeamPos = targetData.teamPosition;
        const targetSlotPos = targetData.slotPosition;

        setTeams((prev) => {
            if (sourceData.kind === 'pool') {
                const heroId = sourceData.heroId;
                if (heroId === null) return prev;

                if (enforceUnique) {
                    const existingPos = findHeroPosition(prev, heroId);
                    if (existingPos && (existingPos.teamPos !== targetTeamPos || existingPos.slotPos !== targetSlotPos)) {
                        const targetCurrent = getSlotHero(prev, targetTeamPos, targetSlotPos);
                        let next = updateSlot(prev, targetTeamPos, targetSlotPos, heroId);
                        next = updateSlot(next, existingPos.teamPos, existingPos.slotPos, targetCurrent);
                        return next;
                    }
                }

                return updateSlot(prev, targetTeamPos, targetSlotPos, heroId);
            }

            if (sourceData.kind === 'slot' && sourceData.teamPosition !== undefined && sourceData.slotPosition !== undefined) {
                const sourceTeamPos = sourceData.teamPosition;
                const sourceSlotPos = sourceData.slotPosition;
                if (sourceTeamPos === targetTeamPos && sourceSlotPos === targetSlotPos) return prev;

                const sourceHero = getSlotHero(prev, sourceTeamPos, sourceSlotPos);
                const targetHero = getSlotHero(prev, targetTeamPos, targetSlotPos);
                let next = updateSlot(prev, targetTeamPos, targetSlotPos, sourceHero);
                next = updateSlot(next, sourceTeamPos, sourceSlotPos, targetHero);
                return next;
            }

            return prev;
        });

        setIsDirty(true);
    };

    const suggestForTeam = async (teamPos: number) => {
        setSuggestingTeamPos(teamPos);
        try {
            const payload = {
                teams: teams.map((team) => ({
                    position: team.position,
                    heroes: team.slots
                        .map((slot, idx) => ({ slot: idx + 1, hero_id: slot.heroId }))
                        .filter((h): h is { slot: number; hero_id: number } => h.hero_id !== null),
                })),
            };

            const response = await axios.post<{ additions: Array<{ slot: number; hero_id: number }> }>(
                `/formations/${formation.id}/teams/${teamPos}/suggest`,
                payload,
            );

            const additions = response.data.additions;
            if (additions.length === 0) return;

            setTeams((prev) =>
                prev.map((team) =>
                    team.position === teamPos
                        ? {
                              ...team,
                              slots: team.slots.map((slot, idx) => {
                                  const slotPos = idx + 1;
                                  const addition = additions.find((a) => a.slot === slotPos);
                                  return addition ? { heroId: addition.hero_id } : slot;
                              }),
                          }
                        : team,
                ),
            );
            setIsDirty(true);
        } catch (error) {
            console.error('Falha ao sugerir herois:', error);
        } finally {
            setSuggestingTeamPos(null);
        }
    };

    const suggestAll = async () => {
        setIsSuggestingAll(true);
        try {
            const payload = {
                teams: teams.map((team) => ({
                    position: team.position,
                    heroes: team.slots
                        .map((slot, idx) => ({ slot: idx + 1, hero_id: slot.heroId }))
                        .filter((h): h is { slot: number; hero_id: number } => h.hero_id !== null),
                })),
            };

            const response = await axios.post<{
                teams: Array<{ position: number; additions: Array<{ slot: number; hero_id: number }> }>;
            }>(`/formations/${formation.id}/suggest-all`, payload);

            const additionsByPos = new Map<number, Array<{ slot: number; hero_id: number }>>();
            for (const t of response.data.teams) {
                additionsByPos.set(t.position, t.additions);
            }

            setTeams((prev) =>
                prev.map((team) => {
                    const additions = additionsByPos.get(team.position);
                    if (!additions || additions.length === 0) return team;

                    return {
                        ...team,
                        slots: team.slots.map((slot, idx) => {
                            const slotPos = idx + 1;
                            const addition = additions.find((a) => a.slot === slotPos);
                            return addition ? { heroId: addition.hero_id } : slot;
                        }),
                    };
                }),
            );
            setIsDirty(true);
        } catch (error) {
            console.error('Falha ao sugerir todas as equipes:', error);
        } finally {
            setIsSuggestingAll(false);
        }
    };

    const generateShareLink = async () => {
        setIsSharing(true);
        try {
            const response = await axios.post<{ share_token: string; public_url: string }>(
                `/formations/${formation.id}/share`,
            );
            setShareToken(response.data.share_token);
        } catch (error) {
            console.error('Falha ao gerar link de compartilhamento:', error);
        } finally {
            setIsSharing(false);
        }
    };

    const revokeShareLink = async () => {
        setIsSharing(true);
        try {
            await axios.delete(`/formations/${formation.id}/share`);
            setShareToken(null);
        } catch (error) {
            console.error('Falha ao revogar link:', error);
        } finally {
            setIsSharing(false);
        }
    };

    const copyShareLink = async () => {
        if (!shareToken) return;
        const url = `${window.location.origin}/f/${shareToken}`;
        try {
            await navigator.clipboard.writeText(url);
            setCopySuccess(true);
            setTimeout(() => setCopySuccess(false), 1500);
        } catch (error) {
            console.error('Falha ao copiar:', error);
        }
    };

    const openHistory = async () => {
        setHistoryDialogOpen(true);
        setIsLoadingVersions(true);
        try {
            const response = await axios.get<{ versions: Array<{ id: number; created_at: string | null; team_count: number; hero_count: number; name: string | null }> }>(
                `/formations/${formation.id}/versions`,
            );
            setVersions(response.data.versions);
        } catch (error) {
            console.error('Falha ao carregar histórico:', error);
        } finally {
            setIsLoadingVersions(false);
        }
    };

    const restoreVersion = async (versionId: number) => {
        const ok = await confirm({
            title: 'Restaurar esta versão?',
            description: 'A versão atual será salva no histórico antes de ser substituída.',
            confirmLabel: 'Restaurar',
        });
        if (!ok) return;

        router.post(
            `/formations/${formation.id}/versions/${versionId}/restore`,
            {},
            {
                preserveScroll: false,
                onSuccess: () => setHistoryDialogOpen(false),
            },
        );
    };

    const save = () => {
        setIsSaving(true);

        const payload = {
            name,
            notes: formation.notes,
            beast_id: beastId,
            teams: teams.map((team) => ({
                position: team.position,
                name: team.name,
                heroes: team.slots
                    .map((slot, idx) => ({ slot: idx + 1, hero_id: slot.heroId, row: null }))
                    .filter((h): h is { slot: number; hero_id: number; row: null } => h.hero_id !== null),
            })),
        };

        router.patch(`/formations/${formation.id}`, payload, {
            preserveScroll: true,
            onSuccess: () => {
                setIsDirty(false);
            },
            onFinish: () => {
                setIsSaving(false);
            },
        });
    };

    const activeDragHero = activeDragHeroId !== null ? heroById[activeDragHeroId] : null;
    const activeDragFaction = activeDragHero ? factionById[activeDragHero.faction_id] : null;
    const activeDragClass = activeDragHero ? classById[activeDragHero.hero_class_id] : null;
    const activeDragTier = activeDragHero ? tierById[activeDragHero.tier_id] : null;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Editar ${formation.name}`} />

            <DndContext sensors={sensors} onDragStart={handleDragStart} onDragEnd={handleDragEnd}>
                <div className="flex h-full flex-1 flex-col gap-4 p-3 sm:gap-6 sm:p-4 md:p-6">
                    {/* Linha 1: nome + ações primárias */}
                    <div className="flex items-start gap-2">
                        <Input
                            value={name}
                            onChange={(e) => {
                                setName(e.target.value);
                                setIsDirty(true);
                            }}
                            maxLength={100}
                            className="h-9 flex-1 max-w-md text-base font-semibold"
                        />

                        {/* Ações desktop (md+) */}
                        <div className="hidden items-center gap-2 md:flex">
                            {isDirty && <span className="text-xs text-amber-600 dark:text-amber-400">Alterações não salvas</span>}
                            <Button
                                variant="outline"
                                onClick={openHistory}
                                disabled={isSaving}
                            >
                                <History className="size-4" />
                                Histórico
                            </Button>
                            <Button
                                variant="outline"
                                onClick={() => setShareDialogOpen(true)}
                                disabled={isSaving}
                            >
                                <Share2 className="size-4" />
                                {shareToken ? 'Compartilhada' : 'Compartilhar'}
                            </Button>
                            <Button
                                variant="outline"
                                onClick={suggestAll}
                                disabled={isSuggestingAll || isSaving}
                            >
                                {isSuggestingAll ? <Loader2 className="size-4 animate-spin" /> : <Sparkles className="size-4" />}
                                {isSuggestingAll ? 'Sugerindo...' : 'Sugerir todas'}
                            </Button>
                            <Button onClick={save} disabled={!isDirty || isSaving}>
                                <Save className="size-4" />
                                {isSaving ? 'Salvando...' : 'Salvar'}
                            </Button>
                        </div>

                        {/* Ações mobile: Salvar + dropdown menu */}
                        <div className="flex shrink-0 items-center gap-2 md:hidden">
                            <Button onClick={save} disabled={!isDirty || isSaving} size="sm" className="h-9">
                                <Save className="size-4" />
                                {isSaving ? '...' : 'Salvar'}
                            </Button>
                            <DropdownMenu>
                                <DropdownMenuTrigger asChild>
                                    <Button variant="outline" size="icon" className="size-9 shrink-0" aria-label="Mais opções">
                                        <MoreHorizontal className="size-4" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end">
                                    <DropdownMenuItem onClick={openHistory} disabled={isSaving}>
                                        <History className="size-4" />
                                        Histórico
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => setShareDialogOpen(true)} disabled={isSaving}>
                                        <Share2 className="size-4" />
                                        {shareToken ? 'Compartilhada' : 'Compartilhar'}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={suggestAll} disabled={isSuggestingAll || isSaving}>
                                        {isSuggestingAll ? <Loader2 className="size-4 animate-spin" /> : <Sparkles className="size-4" />}
                                        Sugerir todas
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>

                    {/* Indicador "alterações não salvas" mobile */}
                    {isDirty && (
                        <p className="-mt-2 text-xs text-amber-600 dark:text-amber-400 md:hidden">
                            Alterações não salvas
                        </p>
                    )}

                    {/* Linha 2: badges + beast selector */}
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="secondary">{formation.challenge_type.name_pt}</Badge>
                        {formation.mode === 'target' && (
                            <Badge variant="outline" className="gap-1">
                                <Target className="size-3" />
                                Alvo
                            </Badge>
                        )}
                        {formation.challenge_type.is_pvp && (
                            <Badge variant="outline" className="border-rose-500/40 text-rose-600 dark:text-rose-400">
                                PvP
                            </Badge>
                        )}
                        {enforceUnique && (
                            <Badge variant="outline" className="border-amber-500/40 text-amber-600 dark:text-amber-400">
                                Heróis únicos por equipe
                            </Badge>
                        )}

                        {/* Beast ativo — vai pro fim da row em qualquer largura */}
                        <div className="ml-auto flex items-center gap-2">
                            <PawPrint className="size-4 text-muted-foreground" />
                            <span className="hidden text-xs uppercase tracking-wide text-muted-foreground sm:inline">Beast:</span>
                            <Select
                                value={beastId ? String(beastId) : 'none'}
                                onValueChange={(v) => {
                                    setBeastId(v === 'none' ? null : Number(v));
                                    setIsDirty(true);
                                }}
                            >
                                <SelectTrigger className="h-8 w-auto min-w-[140px] text-xs sm:min-w-[180px]">
                                    <SelectValue placeholder="Nenhum" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">— nenhum —</SelectItem>
                                    {beasts.map((b) => {
                                        const buffs = [];
                                        if (b.strength_buff > 0) buffs.push(`F+${b.strength_buff}`);
                                        if (b.intelligence_buff > 0) buffs.push(`I+${b.intelligence_buff}`);
                                        if (b.agility_buff > 0) buffs.push(`A+${b.agility_buff}`);
                                        return (
                                            <SelectItem key={b.id} value={String(b.id)}>
                                                {b.name}{buffs.length > 0 ? ` · ${buffs.join(' / ')}` : ''}
                                            </SelectItem>
                                        );
                                    })}
                                </SelectContent>
                            </Select>
                        </div>
                    </div>

                    <div className="grid flex-1 gap-3 lg:grid-cols-[1fr_320px]">
                        <div className="flex flex-col gap-3 overflow-y-auto pr-1 pb-20 lg:pb-0">
                            {teams.map((team) => (
                                <div key={team.position} className="flex flex-col gap-1">
                                    <TeamRow
                                        team={team}
                                        heroById={heroById}
                                        factionById={factionById}
                                        classById={classById}
                                        tierById={tierById}
                                        onRemoveHero={(slotPosition) => removeFromSlot(team.position, slotPosition)}
                                        onSuggest={() => suggestForTeam(team.position)}
                                        isSuggesting={suggestingTeamPos === team.position}
                                    />
                                    <RecommendedMatchesStripe matches={recommendedMatches?.[team.position] ?? []} />
                                </div>
                            ))}
                        </div>

                        {/* Pool lateral (desktop) */}
                        <div className="hidden h-[calc(100vh-180px)] lg:block">
                            <HeroPool
                                heroes={heroPool}
                                factions={factions}
                                heroClasses={heroClasses}
                                factionById={factionById}
                                classById={classById}
                                tierById={tierById}
                                placedHeroIds={placedHeroIds}
                                enforceUnique={enforceUnique}
                            />
                        </div>
                    </div>
                </div>

                {/* Pool em bottom sheet (mobile/tablet) */}
                <Sheet open={poolSheetOpen} onOpenChange={setPoolSheetOpen}>
                    <SheetContent side="bottom" className="flex h-[80dvh] flex-col gap-2 p-3 lg:hidden">
                        <SheetHeader className="text-left">
                            <SheetTitle className="flex items-center gap-2 text-sm">
                                <Users className="size-4" />
                                Heróis disponíveis
                            </SheetTitle>
                        </SheetHeader>
                        <div className="min-h-0 flex-1 overflow-hidden">
                            <HeroPool
                                heroes={heroPool}
                                factions={factions}
                                heroClasses={heroClasses}
                                factionById={factionById}
                                classById={classById}
                                tierById={tierById}
                                placedHeroIds={placedHeroIds}
                                enforceUnique={enforceUnique}
                            />
                        </div>
                    </SheetContent>
                </Sheet>

                {/* FAB pra abrir o pool em mobile */}
                <button
                    type="button"
                    onClick={() => setPoolSheetOpen(true)}
                    className="fixed bottom-4 right-4 z-40 flex size-14 items-center justify-center rounded-full bg-gradient-to-br from-amber-500 to-yellow-400 text-zinc-900 shadow-[0_0_24px_-4px_rgba(251,191,36,0.7)] transition-transform hover:scale-105 active:scale-95 lg:hidden"
                    aria-label="Abrir lista de heróis"
                >
                    <Users className="size-6" />
                </button>

                <Dialog open={historyDialogOpen} onOpenChange={setHistoryDialogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <History className="size-5" />
                                Histórico de versões
                            </DialogTitle>
                            <DialogDescription>
                                Cada vez que você salva, uma versão anterior é guardada (até 20). Restaurar substitui o estado atual — a versão de agora também vai pro histórico antes da troca.
                            </DialogDescription>
                        </DialogHeader>

                        {isLoadingVersions ? (
                            <div className="flex justify-center py-8">
                                <Loader2 className="size-6 animate-spin text-muted-foreground" />
                            </div>
                        ) : versions.length === 0 ? (
                            <p className="py-8 text-center text-sm text-muted-foreground">
                                Sem versões anteriores ainda. Salve a formação ao menos uma vez para começar o histórico.
                            </p>
                        ) : (
                            <div className="flex max-h-[60vh] flex-col gap-2 overflow-y-auto">
                                {versions.map((v) => (
                                    <div
                                        key={v.id}
                                        className="flex items-center justify-between gap-3 rounded-lg border p-2.5"
                                    >
                                        <div className="flex flex-col text-xs">
                                            <span className="font-medium">
                                                {v.created_at
                                                    ? new Date(v.created_at).toLocaleString('pt-BR')
                                                    : 'sem data'}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {v.name && v.name !== formation.name ? `"${v.name}" · ` : ''}
                                                {v.hero_count} herói{v.hero_count !== 1 ? 's' : ''} em {v.team_count} equipe{v.team_count !== 1 ? 's' : ''}
                                            </span>
                                        </div>
                                        <Button
                                            type="button"
                                            variant="outline"
                                            size="sm"
                                            onClick={() => restoreVersion(v.id)}
                                        >
                                            <RotateCcw className="size-3" />
                                            Restaurar
                                        </Button>
                                    </div>
                                ))}
                            </div>
                        )}
                    </DialogContent>
                </Dialog>

                <Dialog open={shareDialogOpen} onOpenChange={setShareDialogOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2">
                                <Share2 className="size-5" />
                                Compartilhar formação
                            </DialogTitle>
                            <DialogDescription>
                                Gere um link público read-only que qualquer pessoa pode abrir sem login. Você pode revogar a qualquer momento.
                            </DialogDescription>
                        </DialogHeader>

                        {shareToken ? (
                            <div className="flex flex-col gap-3">
                                <div className="flex items-center gap-2">
                                    <Input
                                        readOnly
                                        value={`${window.location.origin}/f/${shareToken}`}
                                        className="font-mono text-xs"
                                    />
                                    <Button type="button" variant="outline" size="sm" onClick={copyShareLink}>
                                        <Copy className="size-4" />
                                        {copySuccess ? 'Copiado!' : 'Copiar'}
                                    </Button>
                                </div>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={revokeShareLink}
                                    disabled={isSharing}
                                    className="border-destructive/40 text-destructive hover:bg-destructive/10"
                                >
                                    {isSharing ? <Loader2 className="size-4 animate-spin" /> : <Trash2 className="size-4" />}
                                    Revogar link
                                </Button>
                            </div>
                        ) : (
                            <Button type="button" onClick={generateShareLink} disabled={isSharing}>
                                {isSharing ? <Loader2 className="size-4 animate-spin" /> : <Link2 className="size-4" />}
                                Gerar link público
                            </Button>
                        )}
                    </DialogContent>
                </Dialog>

                {confirmDialog}

                <DragOverlay dropAnimation={null}>
                    {activeDragHero && activeDragFaction && activeDragClass && activeDragTier ? (
                        <div className={cn('rotate-3 opacity-95')}>
                            <HeroChip
                                hero={activeDragHero}
                                factionCode={activeDragFaction.code as FactionCode}
                                classCode={activeDragClass.code as ClassCode}
                                tierCode={activeDragTier.code as TierCode}
                                size="md"
                                className={FACTION_THEME[activeDragFaction.code as FactionCode].cardHoverGlow}
                            />
                        </div>
                    ) : null}
                </DragOverlay>
            </DndContext>
        </AppLayout>
    );
}

function getSlotHero(teams: TeamSlotsState[], teamPos: number, slotPos: number): number | null {
    const team = teams.find((t) => t.position === teamPos);
    return team?.slots[slotPos - 1]?.heroId ?? null;
}

function findHeroPosition(teams: TeamSlotsState[], heroId: number): { teamPos: number; slotPos: number } | null {
    for (const team of teams) {
        for (let i = 0; i < team.slots.length; i++) {
            if (team.slots[i].heroId === heroId) {
                return { teamPos: team.position, slotPos: i + 1 };
            }
        }
    }
    return null;
}

const MATCH_TIER_BADGE: Record<string, string> = {
    'S+': 'bg-gradient-to-br from-rose-500 to-amber-400 text-white',
    S: 'bg-rose-500 text-white',
    A: 'bg-orange-500 text-white',
    B: 'bg-amber-500 text-stone-900',
    C: 'bg-zinc-500 text-white',
};

function RecommendedMatchesStripe({ matches }: { matches: RecommendedMatch[] }) {
    if (matches.length === 0) return null;

    return (
        <div className="flex flex-wrap items-center gap-1.5 rounded-md border border-dashed border-amber-500/40 bg-amber-500/5 px-2 py-1">
            <span className="flex items-center gap-1 text-[10px] font-semibold uppercase tracking-wide text-amber-700 dark:text-amber-400">
                <Sparkles className="size-3" />
                Comps próximos:
            </span>
            {matches.map((match) => {
                const titleLines: string[] = [
                    `${match.name}${match.tier ? ` (${match.tier})` : ''}`,
                    `Core: ${match.core_overlap}/${match.total_core}`,
                    `Total no comp: ${match.total_overlap}`,
                ];
                if (match.missing_core.length > 0) {
                    titleLines.push(`Faltando do core: ${match.missing_core.join(', ')}`);
                }

                return (
                    <a
                        key={match.slug}
                        href="/formations/recommended"
                        title={titleLines.join('\n')}
                        className="group flex items-center gap-1 rounded-md border bg-card px-1.5 py-0.5 text-[10px] hover:border-amber-500/60 hover:bg-amber-500/10"
                    >
                        {match.tier && (
                            <span className={cn('rounded px-1 py-0.5 text-[9px] font-bold leading-none', MATCH_TIER_BADGE[match.tier] ?? 'bg-muted text-foreground')}>
                                {match.tier}
                            </span>
                        )}
                        <span className="truncate font-medium max-w-[140px]">{match.name}</span>
                        <span className="text-muted-foreground">
                            {match.core_overlap}/{match.total_core} core
                        </span>
                        {match.missing_core.length > 0 && (
                            <span className="text-amber-700 dark:text-amber-400" aria-label={`faltando ${match.missing_core.length}`}>
                                · falta {match.missing_core.length}
                            </span>
                        )}
                    </a>
                );
            })}
        </div>
    );
}
