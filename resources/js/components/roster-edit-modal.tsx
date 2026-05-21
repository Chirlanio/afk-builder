import axios from 'axios';
import { BarChart3, Crown, Footprints, Gem, Hammer, Hotel, Loader2, PenLine, Save, ScrollText, Shield, Shirt, Sparkles, Sword } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';

import { MarkdownEditor } from '@/components/markdown-editor';
import { StatsEditModal } from '@/components/stats-edit-modal';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import {
    ascensionStagesForTier,
    EQUIPMENT_LEVEL_MAX,
    EQUIPMENT_TIER_BADGE,
    EQUIPMENT_TIER_LABEL,
    EQUIPMENT_TIERS,
    FACTION_THEME,
    TIER_THEME,
    tierFromAscension,
} from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { Artifact, AvailableEquipment, Collection, EquipmentSlot, EquipmentTier, FactionCode, Hero, HeroStats, TierCode, UserHero } from '@/types/afk';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    hero: (Hero & { faction: { code: FactionCode } }) | null;
    userHero: UserHero | null;
    availableArtifacts: Artifact[];
    availableCollections: Collection[];
    availableEquipments?: AvailableEquipment[];
    onSaved?: () => void | Promise<void>;
}

export function RosterEditModal({ open, onOpenChange, hero, userHero, availableArtifacts, availableCollections, availableEquipments = [], onSaved }: Props) {
    const tierCode = (hero?.tier?.code ?? 'common') as TierCode;
    const tierTheme = TIER_THEME[tierCode];
    const validStages = ascensionStagesForTier(tierTheme.maxLevel);
    const maxLevel = tierTheme.maxLevel;
    const isEditing = userHero !== null;
    const factionTheme = hero ? FACTION_THEME[hero.faction.code] : null;

    const [level, setLevel] = useState<number>(userHero?.level ?? 1);
    const [ascensionCode, setAscensionCode] = useState<string>(userHero?.ascension_code ?? '');
    const [signatureItem, setSignatureItem] = useState<number>(userHero?.signature_item ?? 0);
    const [furniture, setFurniture] = useState<number>(userHero?.furniture ?? 0);
    const [engraving, setEngraving] = useState<number>(userHero?.engraving ?? 0);
    const [artifactId, setArtifactId] = useState<number | null>(userHero?.artifact_id ?? null);
    const [artifactStarLevel, setArtifactStarLevel] = useState<number | null>(userHero?.artifact_star_level ?? null);
    const [collectionId, setCollectionId] = useState<number | null>(userHero?.collection_id ?? null);
    const [weapon, setWeapon] = useState<{ tier: EquipmentTier | null; level: number | null }>({
        tier: userHero?.weapon_tier ?? null,
        level: userHero?.weapon_level ?? null,
    });
    const [helmet, setHelmet] = useState<{ tier: EquipmentTier | null; level: number | null }>({
        tier: userHero?.helmet_tier ?? null,
        level: userHero?.helmet_level ?? null,
    });
    const [chest, setChest] = useState<{ tier: EquipmentTier | null; level: number | null }>({
        tier: userHero?.chest_tier ?? null,
        level: userHero?.chest_level ?? null,
    });
    const [boots, setBoots] = useState<{ tier: EquipmentTier | null; level: number | null }>({
        tier: userHero?.boots_tier ?? null,
        level: userHero?.boots_level ?? null,
    });
    const [mythicSlots, setMythicSlots] = useState<Record<EquipmentSlot, { id: number | null; level: number }>>({
        weapon: { id: userHero?.weapon_equipment_id ?? null, level: userHero?.weapon_equipment_level ?? 0 },
        helmet: { id: userHero?.helmet_equipment_id ?? null, level: userHero?.helmet_equipment_level ?? 0 },
        chest: { id: userHero?.chest_equipment_id ?? null, level: userHero?.chest_equipment_level ?? 0 },
        boots: { id: userHero?.boots_equipment_id ?? null, level: userHero?.boots_equipment_level ?? 0 },
    });
    const [notes, setNotes] = useState<string>(userHero?.notes ?? '');
    const [saving, setSaving] = useState(false);
    const [statsModalOpen, setStatsModalOpen] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    // Resync quando abre ou troca de herói
    useEffect(() => {
        if (!open) return;
        setLevel(userHero?.level ?? 1);
        setAscensionCode(userHero?.ascension_code ?? '');
        setSignatureItem(userHero?.signature_item ?? 0);
        setFurniture(userHero?.furniture ?? 0);
        setEngraving(userHero?.engraving ?? 0);
        setArtifactId(userHero?.artifact_id ?? null);
        setArtifactStarLevel(userHero?.artifact_star_level ?? null);
        setCollectionId(userHero?.collection_id ?? null);
        setWeapon({ tier: userHero?.weapon_tier ?? null, level: userHero?.weapon_level ?? null });
        setHelmet({ tier: userHero?.helmet_tier ?? null, level: userHero?.helmet_level ?? null });
        setChest({ tier: userHero?.chest_tier ?? null, level: userHero?.chest_level ?? null });
        setBoots({ tier: userHero?.boots_tier ?? null, level: userHero?.boots_level ?? null });
        setMythicSlots({
            weapon: { id: userHero?.weapon_equipment_id ?? null, level: userHero?.weapon_equipment_level ?? 0 },
            helmet: { id: userHero?.helmet_equipment_id ?? null, level: userHero?.helmet_equipment_level ?? 0 },
            chest: { id: userHero?.chest_equipment_id ?? null, level: userHero?.chest_equipment_level ?? 0 },
            boots: { id: userHero?.boots_equipment_id ?? null, level: userHero?.boots_equipment_level ?? 0 },
        });
        setNotes(userHero?.notes ?? '');
        setErrors({});
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, hero?.id, userHero?.id]);

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        if (!hero) return;
        setSaving(true);
        setErrors({});
        // Collection e Artifact compartilham slot — quando ambos preenchidos, prioriza Collection
        const payload = {
            level,
            ascension_code: ascensionCode || null,
            signature_item: signatureItem,
            furniture,
            engraving,
            weapon_tier: weapon.tier,
            weapon_level: weapon.tier ? (weapon.level ?? 1) : null,
            helmet_tier: helmet.tier,
            helmet_level: helmet.tier ? (helmet.level ?? 1) : null,
            chest_tier: chest.tier,
            chest_level: chest.tier ? (chest.level ?? 1) : null,
            boots_tier: boots.tier,
            boots_level: boots.tier ? (boots.level ?? 1) : null,
            weapon_equipment_id: mythicSlots.weapon.id,
            weapon_equipment_level: mythicSlots.weapon.id ? mythicSlots.weapon.level : 0,
            helmet_equipment_id: mythicSlots.helmet.id,
            helmet_equipment_level: mythicSlots.helmet.id ? mythicSlots.helmet.level : 0,
            chest_equipment_id: mythicSlots.chest.id,
            chest_equipment_level: mythicSlots.chest.id ? mythicSlots.chest.level : 0,
            boots_equipment_id: mythicSlots.boots.id,
            boots_equipment_level: mythicSlots.boots.id ? mythicSlots.boots.level : 0,
            artifact_id: collectionId ? null : artifactId,
            artifact_star_level: collectionId ? null : artifactId ? artifactStarLevel : null,
            collection_id: collectionId,
            notes: notes || null,
        };

        try {
            if (isEditing && userHero) {
                await axios.patch(`/user/heroes/${userHero.id}`, payload);
            } else {
                await axios.post('/user/heroes', { ...payload, hero_id: hero.id });
            }
            await onSaved?.();
            onOpenChange(false);
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

    if (!hero) return null;

    const displayTierCode = tierFromAscension(validStages.find((s) => s.code === ascensionCode)?.value ?? userHero?.ascension_value ?? 0);
    const visualTier = TIER_THEME[displayTierCode];
    const selectedArtifact = artifactId ? availableArtifacts.find((a) => a.id === artifactId) : null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] max-w-5xl overflow-y-auto p-0">
                <header className={cn('border-b p-4', factionTheme?.cardGradient)}>
                    <DialogTitle asChild>
                        <div className="flex flex-col items-center gap-1 text-center">
                            <span className="text-muted-foreground text-[10px] tracking-wider uppercase">
                                {isEditing ? 'Editar roster' : 'Adicionar ao roster'}
                            </span>
                            {hero.title && <p className="text-muted-foreground text-xs tracking-wider uppercase">{hero.title}</p>}
                            <h2 className={cn('text-2xl leading-tight font-bold', factionTheme?.headerText)}>{hero.name}</h2>
                        </div>
                    </DialogTitle>
                </header>

                <form onSubmit={submit} className="flex flex-col gap-4 p-4">
                    {/* Upper: 3-col (slots equipamentos | portrait | slots equipamentos) */}
                    <div className="mx-auto grid w-full max-w-3xl grid-cols-1 gap-3 md:grid-cols-[140px_minmax(220px,1fr)_140px]">
                        {/* Esquerda: Arma (em breve) → Capacete (em breve) → Assinatura */}
                        <div className="flex flex-col gap-2">
                            <SlotEdit icon={<Sword className="size-3" />} label="Arma">
                                <EquipmentInputs tier={weapon.tier} level={weapon.level} onChange={(tier, level) => setWeapon({ tier, level })} />
                            </SlotEdit>
                            <SlotEdit icon={<Shield className="size-3" />} label="Capacete">
                                <EquipmentInputs tier={helmet.tier} level={helmet.level} onChange={(tier, level) => setHelmet({ tier, level })} />
                            </SlotEdit>
                            <SlotEdit icon={<Crown className="size-3" />} label="Assinatura (0-40)">
                                <Input
                                    type="number"
                                    min={0}
                                    max={40}
                                    value={signatureItem}
                                    onChange={(e) => setSignatureItem(Math.min(40, Math.max(0, Number(e.target.value) || 0)))}
                                    className="h-8"
                                />
                            </SlotEdit>
                        </div>

                        {/* Centro: portrait + nível/ascensão */}
                        <div className="flex flex-col items-center gap-2">
                            <div
                                className={cn(
                                    'relative aspect-[3/4] w-full overflow-hidden rounded-lg border-2',
                                    factionTheme?.cardGradient,
                                    factionTheme?.cardBorder,
                                )}
                            >
                                {hero.portrait_url ? (
                                    <img src={hero.portrait_url} alt={hero.name} className="size-full object-contain object-bottom" />
                                ) : (
                                    <div className="text-muted-foreground grid size-full place-items-center text-xs">sem imagem</div>
                                )}
                            </div>
                            <Badge className={cn('text-xs', visualTier.badge)}>{visualTier.label}</Badge>

                            <div className="grid w-full grid-cols-2 gap-2">
                                <div className="space-y-1">
                                    <Label htmlFor="rh-level" className="text-[10px] uppercase">
                                        Nível (max {maxLevel})
                                    </Label>
                                    <Input
                                        id="rh-level"
                                        type="number"
                                        min={1}
                                        max={maxLevel}
                                        value={level}
                                        onChange={(e) => setLevel(Math.min(maxLevel, Math.max(1, Number(e.target.value) || 1)))}
                                        className="h-8"
                                    />
                                    {errors.level && <p className="text-destructive text-[10px]">{errors.level}</p>}
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="rh-asc" className="text-[10px] uppercase">
                                        Ascensão
                                    </Label>
                                    <Select value={ascensionCode || 'none'} onValueChange={(v) => setAscensionCode(v === 'none' ? '' : v)}>
                                        <SelectTrigger id="rh-asc" className="h-8 text-xs">
                                            <SelectValue placeholder="—" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">— —</SelectItem>
                                            {validStages.map((stage) => (
                                                <SelectItem key={stage.code} value={stage.code}>
                                                    {stage.label}
                                                </SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>

                        {/* Direita: Peitoral (em breve) → Bota (em breve) → Artefato OU Coleção */}
                        <div className="flex flex-col gap-2">
                            <SlotEdit icon={<Shirt className="size-3" />} label="Peitoral">
                                <EquipmentInputs tier={chest.tier} level={chest.level} onChange={(tier, level) => setChest({ tier, level })} />
                            </SlotEdit>
                            <SlotEdit icon={<Footprints className="size-3" />} label="Bota">
                                <EquipmentInputs tier={boots.tier} level={boots.level} onChange={(tier, level) => setBoots({ tier, level })} />
                            </SlotEdit>
                            <SlotEdit icon={<Gem className="size-3" />} label="Artefato / Coleção">
                                <Select
                                    value={collectionId ? `c:${collectionId}` : artifactId ? `a:${artifactId}` : 'none'}
                                    onValueChange={(v) => {
                                        if (v === 'none') {
                                            setArtifactId(null);
                                            setArtifactStarLevel(null);
                                            setCollectionId(null);
                                        } else if (v.startsWith('a:')) {
                                            const id = Number(v.slice(2));
                                            setArtifactId(id);
                                            if (!artifactStarLevel) setArtifactStarLevel(1);
                                            setCollectionId(null);
                                        } else if (v.startsWith('c:')) {
                                            const id = Number(v.slice(2));
                                            setCollectionId(id);
                                            setArtifactId(null);
                                            setArtifactStarLevel(null);
                                        }
                                    }}
                                >
                                    <SelectTrigger className="h-8 text-xs">
                                        <SelectValue placeholder="—" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">— nenhum —</SelectItem>
                                        {availableArtifacts
                                            .filter((a) => a.kind === 'dura')
                                            .map((a) => (
                                                <SelectItem key={`a-${a.id}`} value={`a:${a.id}`}>
                                                    Dura's · {a.name}
                                                </SelectItem>
                                            ))}
                                        {availableArtifacts
                                            .filter((a) => a.kind === 'class')
                                            .map((a) => (
                                                <SelectItem key={`a-${a.id}`} value={`a:${a.id}`}>
                                                    Classe · {a.name}
                                                </SelectItem>
                                            ))}
                                        {availableCollections.map((c) => (
                                            <SelectItem key={`c-${c.id}`} value={`c:${c.id}`}>
                                                Coleção · {c.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {selectedArtifact && (
                                    <Input
                                        type="number"
                                        min={1}
                                        max={selectedArtifact.max_stars}
                                        value={artifactStarLevel ?? 1}
                                        onChange={(e) =>
                                            setArtifactStarLevel(Math.min(selectedArtifact.max_stars, Math.max(1, Number(e.target.value) || 1)))
                                        }
                                        className="mt-1 h-7 text-xs"
                                        placeholder={`★ 1-${selectedArtifact.max_stars}`}
                                    />
                                )}
                            </SlotEdit>
                        </div>
                    </div>

                    {/* Atributos extras: Móveis, Inscrições, Notas */}
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div className="flex flex-col gap-1">
                            <Label htmlFor="rh-furn" className="flex items-center gap-1.5 text-xs">
                                <Hotel className="size-3" />
                                Móveis (0-9)
                            </Label>
                            <Input
                                id="rh-furn"
                                type="number"
                                min={0}
                                max={9}
                                value={furniture}
                                onChange={(e) => setFurniture(Math.min(9, Math.max(0, Number(e.target.value) || 0)))}
                                className="h-8"
                            />
                            <div className="flex gap-1">
                                <Badge
                                    variant={furniture >= 3 ? 'default' : 'outline'}
                                    className={cn('h-4 px-1 text-[9px]', furniture >= 3 && 'bg-emerald-500')}
                                >
                                    F3
                                </Badge>
                                <Badge
                                    variant={furniture >= 9 ? 'default' : 'outline'}
                                    className={cn(
                                        'h-4 px-1 text-[9px]',
                                        furniture >= 9 && 'bg-gradient-to-r from-amber-400 to-orange-500 text-stone-900',
                                    )}
                                >
                                    F9
                                </Badge>
                            </div>
                        </div>

                        <div className="flex flex-col gap-1">
                            <Label htmlFor="rh-eng" className="flex items-center gap-1.5 text-xs">
                                <PenLine className="size-3" />
                                Inscrições (0-100)
                            </Label>
                            <Input
                                id="rh-eng"
                                type="number"
                                min={0}
                                max={100}
                                value={engraving}
                                onChange={(e) => setEngraving(Math.min(100, Math.max(0, Number(e.target.value) || 0)))}
                                className="h-8"
                            />
                        </div>

                        <div className="flex flex-col gap-1">
                            <MarkdownEditor
                                value={notes}
                                onChange={setNotes}
                                maxLength={1000}
                                minHeight="min-h-[100px]"
                                label={
                                    <span className="flex items-center gap-1.5">
                                        <ScrollText className="size-3" />
                                        Notas (markdown)
                                    </span>
                                }
                                placeholder="ex: precisa de F9 antes de subir SI; plano: Ascended 3⭐ até fim do mês"
                            />
                        </div>
                    </div>

                    {/* Equipamentos Míticos — colapsável, oculto quando lista vazia */}
                    {availableEquipments.length > 0 && (
                        <details className="rounded-md border bg-card p-3">
                            <summary className="flex cursor-pointer items-center gap-1.5 text-xs font-semibold">
                                <Hammer className="size-3.5" />
                                Equipamentos Míticos
                                <span className="text-muted-foreground ml-auto text-[10px]">
                                    {Object.values(mythicSlots).filter((s) => s.id !== null).length}/4 equipado(s)
                                </span>
                            </summary>
                            <div className="mt-3 grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-4">
                                {(['weapon', 'helmet', 'chest', 'boots'] as EquipmentSlot[]).map((slot) => (
                                    <MythicSlotEdit
                                        key={slot}
                                        slot={slot}
                                        available={availableEquipments.filter((e) => e.slot === slot)}
                                        selectedId={mythicSlots[slot].id}
                                        level={mythicSlots[slot].level}
                                        onChange={(id, level) =>
                                            setMythicSlots((prev) => ({ ...prev, [slot]: { id, level } }))
                                        }
                                    />
                                ))}
                            </div>
                        </details>
                    )}

                    <div className="flex flex-wrap items-center justify-between gap-2 border-t pt-3">
                        {isEditing && userHero && hero && (
                            <Button type="button" variant="outline" size="sm" onClick={() => setStatsModalOpen(true)} disabled={saving}>
                                <BarChart3 className="size-4" />
                                Stats avançados
                            </Button>
                        )}
                        <div className="ml-auto flex gap-2">
                            <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={saving}>
                                Cancelar
                            </Button>
                            <Button type="submit" disabled={saving}>
                                {saving ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                                {saving ? 'Salvando...' : isEditing ? 'Salvar' : 'Adicionar'}
                            </Button>
                        </div>
                    </div>
                </form>
            </DialogContent>

            {/* Modal de stats reais do roster — só aparece em edição (precisa userHero) */}
            {isEditing && hero && userHero && (
                <StatsEditModal
                    open={statsModalOpen}
                    onOpenChange={setStatsModalOpen}
                    hero={hero}
                    stats={userHero as unknown as HeroStats}
                    endpoint={`/user/heroes/${userHero.id}/stats`}
                    contextLabel="Stats do meu roster"
                    onSaved={() => onSaved?.()}
                />
            )}
        </Dialog>
    );
}

interface SlotEditProps {
    icon: React.ReactNode;
    label: string;
    children: React.ReactNode;
    disabled?: boolean;
    expand?: boolean;
}

function SlotEdit({ icon, label, children, disabled, expand }: SlotEditProps) {
    return (
        <div className={cn('bg-card flex flex-col gap-1 rounded-md border p-1.5', disabled && 'opacity-50', expand && 'flex-1')}>
            <div className="text-muted-foreground flex items-center gap-1 text-[9px] font-medium tracking-wide uppercase">
                {icon}
                <span className="truncate">{label}</span>
            </div>
            {children}
        </div>
    );
}

interface EquipmentInputsProps {
    tier: EquipmentTier | null;
    level: number | null;
    onChange: (tier: EquipmentTier | null, level: number | null) => void;
}

const MYTHIC_SLOT_ICON: Record<EquipmentSlot, React.ReactNode> = {
    weapon: <Sword className="size-3" />,
    helmet: <Shield className="size-3" />,
    chest: <Shirt className="size-3" />,
    boots: <Footprints className="size-3" />,
};

const MYTHIC_SLOT_LABEL: Record<EquipmentSlot, string> = {
    weapon: 'Arma',
    helmet: 'Elmo',
    chest: 'Peitoral',
    boots: 'Botas',
};

interface MythicSlotEditProps {
    slot: EquipmentSlot;
    available: AvailableEquipment[];
    selectedId: number | null;
    level: number;
    onChange: (id: number | null, level: number) => void;
}

function MythicSlotEdit({ slot, available, selectedId, level, onChange }: MythicSlotEditProps) {
    const selected = selectedId ? available.find((e) => e.id === selectedId) ?? null : null;
    const max = selected?.max_upgrade_level ?? 0;

    return (
        <div className="bg-background flex flex-col gap-1 rounded border p-2">
            <div className="text-muted-foreground flex items-center gap-1 text-[10px] font-medium tracking-wide uppercase">
                {MYTHIC_SLOT_ICON[slot]}
                <span>{MYTHIC_SLOT_LABEL[slot]}</span>
                {selected?.faction_matches && (
                    <span className="ml-auto rounded bg-amber-500/20 px-1 py-0.5 text-[8px] font-bold text-amber-300">
                        +30%
                    </span>
                )}
            </div>
            <Select
                value={selectedId !== null ? String(selectedId) : 'none'}
                onValueChange={(v) => {
                    if (v === 'none') {
                        onChange(null, 0);
                    } else {
                        onChange(Number(v), 0);
                    }
                }}
            >
                <SelectTrigger className="h-7 text-[10px]">
                    <SelectValue placeholder="—" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="none">— vazio —</SelectItem>
                    {available.map((e) => (
                        <SelectItem key={e.id} value={String(e.id)}>
                            {e.name_pt}
                            <span className="text-muted-foreground ml-1">
                                ({e.quality_tier.replace('mythic_', 'T') === 'mythic' ? 'M' : e.quality_tier.replace('mythic_', 'T').toUpperCase()}, {e.faction_name_pt})
                            </span>
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {selected && max > 0 && (
                <div className="flex items-center gap-1">
                    <Label className="text-[9px]">Nv:</Label>
                    <Input
                        type="number"
                        min={0}
                        max={max}
                        value={level}
                        onChange={(e) => onChange(selectedId, Math.min(max, Math.max(0, Number(e.target.value) || 0)))}
                        className="h-6 text-[10px]"
                    />
                    <span className="text-muted-foreground text-[9px]">/{max}</span>
                </div>
            )}
            {selected && max === 0 && (
                <span className="text-muted-foreground text-[9px]">Sem upgrades</span>
            )}
        </div>
    );
}

function EquipmentInputs({ tier, level, onChange }: EquipmentInputsProps) {
    return (
        <div className="flex flex-col gap-1">
            <Select
                value={tier ?? 'none'}
                onValueChange={(v) => {
                    if (v === 'none') {
                        onChange(null, null);
                    } else {
                        onChange(v as EquipmentTier, level ?? 1);
                    }
                }}
            >
                <SelectTrigger className={cn('h-7 text-[11px]', tier && EQUIPMENT_TIER_BADGE[tier])}>
                    <SelectValue placeholder="—" />
                </SelectTrigger>
                <SelectContent>
                    <SelectItem value="none">— vazio —</SelectItem>
                    {EQUIPMENT_TIERS.map((t) => (
                        <SelectItem key={t} value={t}>
                            {t} · {EQUIPMENT_TIER_LABEL[t]}
                        </SelectItem>
                    ))}
                </SelectContent>
            </Select>
            {tier && (
                <Input
                    type="number"
                    min={1}
                    max={EQUIPMENT_LEVEL_MAX}
                    value={level ?? 1}
                    onChange={(e) => onChange(tier, Math.min(EQUIPMENT_LEVEL_MAX, Math.max(1, Number(e.target.value) || 1)))}
                    placeholder={`Lv 1-${EQUIPMENT_LEVEL_MAX}`}
                    className="h-7 text-[11px]"
                />
            )}
        </div>
    );
}
