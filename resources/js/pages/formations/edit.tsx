import { DndContext, DragOverlay, type DragEndEvent, type DragStartEvent, MouseSensor, TouchSensor, useSensor, useSensors } from '@dnd-kit/core';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { Copy, Download, FileText, History, Link2, Loader2, MoreHorizontal, RotateCcw, Save, Share2, Sparkles, Target, Trash2, Users } from 'lucide-react';
import { TeamsCountDialog } from '@/components/teams-count-dialog';
import { useCallback, useMemo, useState } from 'react';
import { EditorPool } from '@/components/formation-editor/editor-pool';
import { HeroChip } from '@/components/formation-editor/hero-chip';
import { TeamRow, type TeamSlotsState } from '@/components/formation-editor/team-row';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useConfirm } from '@/components/ui/confirm-dialog';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
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
    ScoreBreakdownPayload,
    Tier,
    TierCode,
} from '@/types/afk';
import { ScoreBreakdownCard } from '@/components/score-breakdown-card';
import { FormationExportPreview } from '@/components/formation-export-preview';
import { FormationNotesSheet } from '@/components/formation-notes-sheet';
import { FormationTagsMenu, tagColorClasses, type FormationTagPayload } from '@/components/formation-tags-menu';
import { WeightTunerSheet, type WeightOverrides, type WeightPreset } from '@/components/weight-tuner-sheet';
import { useAutoScrollOnDrag } from '@/hooks/use-auto-scroll-on-drag';
import { useFormationExport } from '@/hooks/use-formation-export';

interface PageProps {
    formation: Formation & {
        challenge_type: ChallengeType;
        teams: FormationTeam[];
        tags?: FormationTagPayload[];
    };
    heroPool: Hero[];
    beasts: Beast[];
    factions: Faction[];
    heroClasses: HeroClass[];
    tiers: Tier[];
    recommendedMatches?: Record<number, RecommendedMatch[]>;
    scoresByTeamPosition?: Record<number, ScoreBreakdownPayload | null>;
    scoringProfileDefaults?: WeightOverrides;
    scoringProfileOverrides?: Partial<WeightOverrides> | null;
    weightPresets?: WeightPreset[];
    availableTags?: FormationTagPayload[];
    /** Fase 52: hero_id → engraving level do roster do user (badges E30/E60/E80 nos slots). */
    engravingByHeroId?: Record<number, number>;
}

const BEAST_DEFAULT_LEVEL = 36;

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
            beastId: team?.beast_id ?? null,
            beastLevel: team?.beast_level ?? BEAST_DEFAULT_LEVEL,
            slots,
        };
    });
}

export default function FormationsEdit({
    formation,
    heroPool,
    beasts,
    factions,
    heroClasses,
    tiers,
    recommendedMatches,
    scoresByTeamPosition,
    scoringProfileDefaults,
    scoringProfileOverrides,
    weightPresets = [],
    availableTags = [],
    engravingByHeroId = {},
}: PageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Formações', href: '/formations' },
        { title: formation.name, href: `/formations/${formation.id}/edit` },
    ];

    const heroById = useMemo(() => Object.fromEntries(heroPool.map((h) => [h.id, h])) as Record<number, Hero>, [heroPool]);
    const beastById = useMemo(() => Object.fromEntries(beasts.map((b) => [b.id, b])) as Record<number, Beast>, [beasts]);
    const factionById = useMemo(() => Object.fromEntries(factions.map((f) => [f.id, f])) as Record<number, Faction>, [factions]);
    const classById = useMemo(() => Object.fromEntries(heroClasses.map((c) => [c.id, c])) as Record<number, HeroClass>, [heroClasses]);
    const tierById = useMemo(() => Object.fromEntries(tiers.map((t) => [t.id, t])) as Record<number, Tier>, [tiers]);

    const [name, setName] = useState(formation.name);
    const [notes, setNotes] = useState<string>(formation.notes ?? '');
    const [teams, setTeams] = useState<TeamSlotsState[]>(() => teamsFromFormation(formation.teams, formation.teams_count));
    const [pickingBeastForTeamPos, setPickingBeastForTeamPos] = useState<number | null>(null);
    const [bestBeastError, setBestBeastError] = useState<string | null>(null);
    const [teamsCountDialogOpen, setTeamsCountDialogOpen] = useState(false);
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
    const [notesSheetOpen, setNotesSheetOpen] = useState(false);
    const [mobileTargetTeamPos, setMobileTargetTeamPos] = useState<number>(1);
    const { confirm, dialog: confirmDialog } = useConfirm();
    const [activeDragHeroId, setActiveDragHeroId] = useState<number | null>(null);
    const [customWeights, setCustomWeights] = useState<WeightOverrides | null>(null);
    const [weightsAreDefault, setWeightsAreDefault] = useState(true);
    const [savedOverrides, setSavedOverrides] = useState<Partial<WeightOverrides> | null>(scoringProfileOverrides ?? null);
    const [presets, setPresets] = useState<WeightPreset[]>(weightPresets);
    const [tags, setTags] = useState<FormationTagPayload[]>(availableTags);
    const [selectedTagIds, setSelectedTagIds] = useState<number[]>(
        formation.tags ? formation.tags.map((t) => t.id) : [],
    );
    const autoScroll = useAutoScrollOnDrag();
    const exportFilename = `${formation.name.replace(/[^a-zA-Z0-9-_]+/g, '_').toLowerCase()}.png`;
    const { ref: exportRef, download: downloadPng, isExporting, error: exportError, clearError } = useFormationExport({
        filename: exportFilename,
    });

    const saveScoringWeights = async (weights: WeightOverrides, isDefault: boolean) => {
        try {
            const response = await axios.patch<{ scoring_weights_override: Partial<WeightOverrides> | null }>(
                `/formations/${formation.id}/scoring-weights`,
                { weights: isDefault ? null : weights },
            );
            setSavedOverrides(response.data.scoring_weights_override);
            // Inertia reload pra refrescar scoresByTeamPosition com os novos pesos persistidos.
            router.reload({ only: ['scoresByTeamPosition', 'scoringProfileOverrides'] });
        } catch (error) {
            console.error('Falha ao salvar pesos:', error);
        }
    };

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

    const clearBeastForTeam = useCallback((teamPos: number) => {
        setTeams((prev) => prev.map((team) => (team.position === teamPos ? { ...team, beastId: null } : team)));
        setIsDirty(true);
    }, []);

    const setBeastForTeam = useCallback((teamPos: number, beastId: number) => {
        setTeams((prev) => prev.map((team) => (team.position === teamPos ? { ...team, beastId } : team)));
        setIsDirty(true);
    }, []);

    const setBeastLevelForTeam = useCallback((teamPos: number, level: number) => {
        const clamped = Math.max(1, Math.min(36, Math.round(level) || 1));
        setTeams((prev) => prev.map((team) => (team.position === teamPos ? { ...team, beastLevel: clamped } : team)));
        setIsDirty(true);
    }, []);

    const autoPickBestBeast = useCallback(
        async (teamPos: number) => {
            const team = teams.find((t) => t.position === teamPos);
            if (!team) return;
            const teamId = formation.teams.find((t) => t.position === teamPos)?.id;
            if (!teamId) return;

            setPickingBeastForTeamPos(teamPos);
            setBestBeastError(null);
            try {
                const response = await axios.post<{ ok: boolean; beast?: Beast; message?: string }>(
                    `/formations/${formation.id}/teams/${teamId}/best-beast`,
                );
                if (response.data.ok && response.data.beast) {
                    setBeastForTeam(teamPos, response.data.beast.id);
                }
            } catch (error) {
                // 422 do backend traz { ok: false, reason, message } — surface a mensagem pro user
                // (motivos: team_empty, no_typed_heroes, no_beasts, no_buff_match).
                let message = 'Falha ao escolher a melhor besta.';
                if (axios.isAxiosError(error) && error.response?.data?.message) {
                    message = `Equipe ${teamPos}: ${error.response.data.message}`;
                }
                setBestBeastError(message);
                console.error('Falha ao escolher melhor besta:', error);
            } finally {
                setPickingBeastForTeamPos(null);
            }
        },
        [teams, formation.teams, formation.id, setBeastForTeam],
    );

    // Tap-to-add (mobile): adiciona herói ao próximo slot vazio da team alvo.
    // Se enforceUnique e herói já está em outro slot, faz swap pro próximo livre.
    // Retorna true se conseguiu adicionar (caller pode fechar Sheet).
    const addHeroToTeam = useCallback(
        (heroId: number, teamPos: number): boolean => {
            let added = false;
            setTeams((prev) => {
                const team = prev.find((t) => t.position === teamPos);
                if (!team) return prev;

                const emptySlotIdx = team.slots.findIndex((s) => s.heroId === null);
                if (emptySlotIdx === -1) return prev; // sem slot vazio

                const targetSlotPos = emptySlotIdx + 1;

                if (enforceUnique) {
                    const existingPos = findHeroPosition(prev, heroId);
                    if (existingPos) {
                        if (existingPos.teamPos === teamPos) {
                            return prev; // já está nesta team, no-op
                        }
                        // swap: limpa do slot antigo e coloca no novo
                        let next = updateSlot(prev, existingPos.teamPos, existingPos.slotPos, null);
                        next = updateSlot(next, teamPos, targetSlotPos, heroId);
                        added = true;
                        return next;
                    }
                }

                added = true;
                return updateSlot(prev, teamPos, targetSlotPos, heroId);
            });
            if (added) setIsDirty(true);
            return added;
        },
        [enforceUnique, updateSlot],
    );

    const handleTapHero = useCallback(
        (heroId: number) => {
            // 1) tenta a team alvo selecionada; 2) se cheia, faz fallback pra primeira com slot livre
            if (addHeroToTeam(heroId, mobileTargetTeamPos)) {
                setPoolSheetOpen(false);
                return;
            }
            const fallback = teams.find((t) => t.slots.some((s) => s.heroId === null));
            if (fallback && fallback.position !== mobileTargetTeamPos) {
                if (addHeroToTeam(heroId, fallback.position)) {
                    setMobileTargetTeamPos(fallback.position);
                    setPoolSheetOpen(false);
                }
            }
        },
        [addHeroToTeam, mobileTargetTeamPos, teams],
    );

    const handleDragStart = (event: DragStartEvent) => {
        const data = event.active.data.current as { kind: string; heroId: number | null } | undefined;
        setActiveDragHeroId(data?.heroId ?? null);
        autoScroll.setActive(true);
        // Em mobile, fechar o sheet do pool ao começar drag — assim o usuário vê
        // os slots-alvo enquanto arrasta o herói pra cima deles.
        setPoolSheetOpen(false);
    };

    const handleDragEnd = (event: DragEndEvent) => {
        setActiveDragHeroId(null);
        autoScroll.setActive(false);
        const { active, over } = event;
        if (!over) return;

        const sourceData = active.data.current as
            | { kind: 'pool' | 'slot'; heroId: number | null; teamPosition?: number; slotPosition?: number }
            | { kind: 'beast'; beastId: number }
            | undefined;
        const targetData = over.data.current as
            | { kind: 'slot'; teamPosition: number; slotPosition: number }
            | { kind: 'beast-slot'; teamPosition: number }
            | undefined;
        if (!sourceData || !targetData) return;

        // Drop de besta no slot de besta de uma team
        if (sourceData.kind === 'beast' && targetData.kind === 'beast-slot') {
            const beastId = sourceData.beastId;
            const targetTeamPosition = targetData.teamPosition;
            setTeams((prev) =>
                prev.map((team) =>
                    team.position === targetTeamPosition ? { ...team, beastId } : team,
                ),
            );
            setIsDirty(true);
            return;
        }

        // Drag de herói só faz sentido pra slot de herói
        if (sourceData.kind === 'beast' || targetData.kind !== 'slot') return;

        const targetTeamPos = targetData.teamPosition;
        const targetSlotPos = targetData.slotPosition;

        setTeams((prev) => {
            if (sourceData.kind === 'pool') {
                const heroId = sourceData.heroId;
                if (heroId == null) return prev;

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
            const payload: {
                teams: Array<{ position: number; heroes: Array<{ slot: number; hero_id: number }> }>;
                weight_overrides?: WeightOverrides;
            } = {
                teams: teams.map((team) => ({
                    position: team.position,
                    heroes: team.slots
                        .map((slot, idx) => ({ slot: idx + 1, hero_id: slot.heroId }))
                        .filter((h): h is { slot: number; hero_id: number } => h.hero_id !== null),
                })),
            };
            if (!weightsAreDefault && customWeights) {
                payload.weight_overrides = customWeights;
            }

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
            const payload: {
                teams: Array<{ position: number; heroes: Array<{ slot: number; hero_id: number }> }>;
                weight_overrides?: WeightOverrides;
            } = {
                teams: teams.map((team) => ({
                    position: team.position,
                    heroes: team.slots
                        .map((slot, idx) => ({ slot: idx + 1, hero_id: slot.heroId }))
                        .filter((h): h is { slot: number; hero_id: number } => h.hero_id !== null),
                })),
            };
            if (!weightsAreDefault && customWeights) {
                payload.weight_overrides = customWeights;
            }

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
            notes: notes.trim() === '' ? null : notes,
            tag_ids: selectedTagIds,
            teams: teams.map((team) => ({
                position: team.position,
                name: team.name,
                beast_id: team.beastId,
                beast_level: team.beastLevel,
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

            <DndContext
                sensors={sensors}
                onDragStart={handleDragStart}
                onDragEnd={handleDragEnd}
                onDragCancel={() => {
                    setActiveDragHeroId(null);
                    autoScroll.setActive(false);
                }}
            >
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
                            {scoringProfileDefaults && (
                                <WeightTunerSheet
                                    formationId={formation.id}
                                    challengeTypeId={formation.challenge_type_id}
                                    defaults={scoringProfileDefaults}
                                    savedOverrides={savedOverrides}
                                    presets={presets}
                                    onChange={(weights, isDefault) => {
                                        setCustomWeights(weights);
                                        setWeightsAreDefault(isDefault);
                                    }}
                                    onSave={saveScoringWeights}
                                    onPresetsChange={setPresets}
                                />
                            )}
                            <FormationTagsMenu
                                availableTags={tags}
                                selectedTagIds={selectedTagIds}
                                onSelectionChange={(next) => {
                                    setSelectedTagIds(next);
                                    setIsDirty(true);
                                }}
                                onTagsChange={setTags}
                            />
                            <Button
                                variant="outline"
                                onClick={() => setNotesSheetOpen(true)}
                                disabled={isSaving}
                                className="gap-1.5"
                            >
                                <FileText className="size-4" />
                                Notas
                                {notes.trim().length > 0 && <span className="ml-1 size-1.5 rounded-full bg-amber-400" aria-hidden />}
                            </Button>
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
                                onClick={downloadPng}
                                disabled={isSaving || isExporting}
                            >
                                {isExporting ? <Loader2 className="size-4 animate-spin" /> : <Download className="size-4" />}
                                {isExporting ? 'Gerando...' : 'PNG'}
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
                                    <DropdownMenuItem onClick={() => setNotesSheetOpen(true)} disabled={isSaving}>
                                        <FileText className="size-4" />
                                        Notas
                                        {notes.trim().length > 0 && <span className="ml-auto size-1.5 rounded-full bg-amber-400" aria-hidden />}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={openHistory} disabled={isSaving}>
                                        <History className="size-4" />
                                        Histórico
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={() => setShareDialogOpen(true)} disabled={isSaving}>
                                        <Share2 className="size-4" />
                                        {shareToken ? 'Compartilhada' : 'Compartilhar'}
                                    </DropdownMenuItem>
                                    <DropdownMenuItem onClick={downloadPng} disabled={isSaving || isExporting}>
                                        {isExporting ? <Loader2 className="size-4 animate-spin" /> : <Download className="size-4" />}
                                        Exportar PNG
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
                        {(() => {
                            const minT = formation.challenge_type.min_teams ?? 1;
                            const maxT = formation.challenge_type.max_teams ?? 1;
                            const adjustable = minT !== maxT;
                            return (
                                <button
                                    type="button"
                                    onClick={() => adjustable && setTeamsCountDialogOpen(true)}
                                    disabled={!adjustable}
                                    className={cn(
                                        'inline-flex h-6 items-center gap-1 rounded-md border bg-background px-2 text-xs font-medium',
                                        adjustable
                                            ? 'cursor-pointer hover:border-primary hover:text-primary'
                                            : 'cursor-default text-muted-foreground',
                                    )}
                                    title={adjustable ? 'Clique para ajustar a quantidade de equipes' : `Este desafio fixa em ${maxT} equipe(s)`}
                                >
                                    <Users className="size-3" />
                                    {formation.teams_count} {formation.teams_count === 1 ? 'equipe' : 'equipes'}
                                </button>
                            );
                        })()}
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

                        {/* Tags selecionadas (Fase 35) */}
                        {selectedTagIds.length > 0 && tags.length > 0 && (
                            <div className="flex flex-wrap items-center gap-1">
                                {selectedTagIds.map((id) => {
                                    const tag = tags.find((t) => t.id === id);
                                    if (!tag) return null;
                                    const c = tagColorClasses(tag.color);
                                    return (
                                        <Badge key={id} variant="outline" className={cn('gap-1 text-[10px]', c.bg, c.text, c.border)}>
                                            <span className={cn('size-1.5 rounded-full', c.dot)} aria-hidden />
                                            {tag.name}
                                        </Badge>
                                    );
                                })}
                            </div>
                        )}

                        {/* Fase 48: beast agora é por team (slot DnD no TeamRow + botão Auto). */}
                    </div>

                    <div className="grid flex-1 gap-3 lg:grid-cols-[1fr_320px]">
                        <div className="flex flex-col gap-3 overflow-y-auto pr-1 pb-20 lg:pb-0">
                            {teams.map((team) => {
                                const teamScore = scoresByTeamPosition?.[team.position] ?? null;
                                return (
                                    <div key={team.position} className="flex flex-col gap-1">
                                        <TeamRow
                                            team={team}
                                            heroById={heroById}
                                            beastById={beastById}
                                            factionById={factionById}
                                            classById={classById}
                                            tierById={tierById}
                                            engravingByHeroId={engravingByHeroId}
                                            onRemoveHero={(slotPosition) => removeFromSlot(team.position, slotPosition)}
                                            onClearBeast={() => clearBeastForTeam(team.position)}
                                            onBeastLevelChange={(level) => setBeastLevelForTeam(team.position, level)}
                                            onAutoBestBeast={() => autoPickBestBeast(team.position)}
                                            isPickingBeast={pickingBeastForTeamPos === team.position}
                                            onSuggest={() => suggestForTeam(team.position)}
                                            isSuggesting={suggestingTeamPos === team.position}
                                        />
                                        <RecommendedMatchesStripe matches={recommendedMatches?.[team.position] ?? []} />
                                        {teamScore && !isDirty && (
                                            <details className="rounded-lg border bg-card/30 text-xs">
                                                <summary className="flex cursor-pointer items-center justify-between px-3 py-1.5 text-muted-foreground hover:text-foreground">
                                                    <span className="font-medium">Ver score detalhado</span>
                                                    <span className={cn('font-mono tabular-nums', !weightsAreDefault && 'text-amber-400')}>
                                                        {weightsAreDefault || !customWeights
                                                            ? teamScore.total.toFixed(2)
                                                            : (
                                                                  teamScore.power * customWeights.power_weight +
                                                                  teamScore.synergy * customWeights.synergy_weight +
                                                                  teamScore.faction_bonus * customWeights.faction_bonus_weight +
                                                                  teamScore.class_balance * customWeights.class_balance_weight +
                                                                  teamScore.tier_rating * customWeights.tier_rating_weight +
                                                                  teamScore.feature_bonus * customWeights.feature_weight +
                                                                  teamScore.furniture_effects * customWeights.furniture_effect_weight +
                                                                  teamScore.engraving_bonus * customWeights.engraving_weight
                                                              ).toFixed(2)}
                                                    </span>
                                                </summary>
                                                <ScoreBreakdownCard
                                                    score={teamScore}
                                                    compact
                                                    className="border-0"
                                                    weights={weightsAreDefault ? undefined : customWeights ?? undefined}
                                                />
                                            </details>
                                        )}
                                    </div>
                                );
                            })}
                        </div>

                        {/* Pool lateral (desktop) — heroes + bestas em tabs */}
                        <div className="hidden h-[calc(100vh-180px)] lg:block">
                            <EditorPool
                                heroes={heroPool}
                                beasts={beasts}
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
                            <p className="text-xs text-muted-foreground">
                                Toque num herói para adicioná-lo ao próximo slot livre, ou arraste pra um slot específico.
                            </p>
                        </SheetHeader>

                        {teams.length > 1 && (
                            <div className="flex shrink-0 flex-wrap items-center gap-1.5 rounded-lg border bg-muted/20 p-1.5">
                                <span className="px-1 text-[11px] font-medium text-muted-foreground">
                                    Adicionar à:
                                </span>
                                {teams.map((team) => {
                                    const filled = team.slots.filter((s) => s.heroId !== null).length;
                                    const full = filled >= 5;
                                    const active = mobileTargetTeamPos === team.position;
                                    return (
                                        <button
                                            key={team.position}
                                            type="button"
                                            onClick={() => setMobileTargetTeamPos(team.position)}
                                            disabled={full}
                                            className={cn(
                                                'min-h-9 rounded-md px-2.5 py-1 text-xs font-medium transition-colors',
                                                active
                                                    ? 'bg-primary text-primary-foreground'
                                                    : 'text-muted-foreground hover:bg-muted',
                                                full && 'cursor-not-allowed opacity-50',
                                            )}
                                            aria-pressed={active}
                                            aria-label={`Definir equipe ${team.position} como alvo (${filled}/5)`}
                                        >
                                            Equipe {team.position} · {filled}/5
                                        </button>
                                    );
                                })}
                            </div>
                        )}

                        <div className="min-h-0 flex-1 overflow-hidden">
                            <EditorPool
                                heroes={heroPool}
                                beasts={beasts}
                                factions={factions}
                                heroClasses={heroClasses}
                                factionById={factionById}
                                classById={classById}
                                tierById={tierById}
                                placedHeroIds={placedHeroIds}
                                enforceUnique={enforceUnique}
                                onTapHero={handleTapHero}
                                onTapBeast={(beastId) => setBeastForTeam(mobileTargetTeamPos, beastId)}
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

                <FormationNotesSheet
                    value={notes}
                    open={notesSheetOpen}
                    onOpenChange={setNotesSheetOpen}
                    hideTrigger
                    onChange={(v) => {
                        setNotes(v);
                        setIsDirty(true);
                    }}
                />

                {/* Fase 50: dialog pra mudar quantidade de equipes */}
                <TeamsCountDialog
                    open={teamsCountDialogOpen}
                    onOpenChange={setTeamsCountDialogOpen}
                    formationId={formation.id}
                    currentCount={formation.teams_count}
                    minTeams={formation.challenge_type.min_teams ?? 1}
                    maxTeams={formation.challenge_type.max_teams ?? 1}
                />

                {/* Preview off-screen pra export PNG (Fase 36) */}
                <FormationExportPreview
                    ref={exportRef}
                    formation={{
                        ...formation,
                        teams: teams.map((t, idx) => ({
                            id: formation.teams[idx]?.id ?? idx,
                            formation_id: formation.id,
                            position: t.position,
                            name: t.name,
                            score: null,
                            beast_id: t.beastId,
                            beast_level: t.beastLevel,
                            beast: t.beastId ? beastById[t.beastId] ?? null : null,
                            team_heroes: t.slots
                                .map((slot, slotIdx) => ({
                                    id: slotIdx,
                                    formation_team_id: formation.teams[idx]?.id ?? idx,
                                    hero_id: slot.heroId ?? 0,
                                    slot: slotIdx + 1,
                                    row: null,
                                }))
                                .filter((th) => th.hero_id !== 0),
                        })),
                        tags: formation.tags,
                    }}
                    heroById={heroById}
                    factionById={factionById}
                    classById={classById}
                    tierById={tierById}
                    scoresByTeamPosition={scoresByTeamPosition}
                />

                {/* Toast pro erro do botão Auto besta — explica o motivo (team vazio, heroes sem type, etc.) */}
                {bestBeastError && (
                    <div
                        role="alert"
                        className="fixed bottom-4 left-1/2 z-50 max-w-md -translate-x-1/2 rounded-lg border border-amber-500 bg-amber-500/10 px-4 py-3 text-sm text-amber-700 shadow-lg dark:text-amber-300"
                    >
                        <div className="flex items-start gap-2">
                            <span className="flex-1">{bestBeastError}</span>
                            <button
                                type="button"
                                onClick={() => setBestBeastError(null)}
                                className="rounded p-0.5 hover:bg-amber-500/20"
                                aria-label="Fechar"
                            >
                                ×
                            </button>
                        </div>
                    </div>
                )}

                {/* Toast minimalista pro erro de export */}
                {exportError && (
                    <div
                        role="alert"
                        className="fixed bottom-4 left-1/2 z-50 max-w-md -translate-x-1/2 rounded-lg border border-destructive bg-destructive/10 px-4 py-3 text-sm text-destructive shadow-lg"
                    >
                        <div className="flex items-start gap-2">
                            <span className="flex-1">{exportError}</span>
                            <button
                                type="button"
                                onClick={clearError}
                                className="rounded p-0.5 hover:bg-destructive/20"
                                aria-label="Fechar"
                            >
                                ×
                            </button>
                        </div>
                    </div>
                )}

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
