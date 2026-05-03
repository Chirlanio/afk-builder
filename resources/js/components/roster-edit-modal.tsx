import axios from 'axios';
import { Crown, Footprints, Gem, Hotel, Loader2, PenLine, Save, ScrollText, Shield, Shirt, Sword } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';

import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ascensionStagesForTier, FACTION_THEME, TIER_THEME, tierFromAscension } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { Artifact, Collection, FactionCode, Hero, TierCode, UserHero } from '@/types/afk';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    hero: (Hero & { faction: { code: FactionCode } }) | null;
    userHero: UserHero | null;
    availableArtifacts: Artifact[];
    availableCollections: Collection[];
    onSaved?: () => void | Promise<void>;
}

export function RosterEditModal({ open, onOpenChange, hero, userHero, availableArtifacts, availableCollections, onSaved }: Props) {
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
    const [notes, setNotes] = useState<string>(userHero?.notes ?? '');
    const [saving, setSaving] = useState(false);
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
            artifact_id: collectionId ? null : artifactId,
            artifact_star_level: collectionId ? null : (artifactId ? artifactStarLevel : null),
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

    const displayTierCode = tierFromAscension(
        validStages.find((s) => s.code === ascensionCode)?.value ?? userHero?.ascension_value ?? 0,
    );
    const visualTier = TIER_THEME[displayTierCode];
    const selectedArtifact = artifactId ? availableArtifacts.find((a) => a.id === artifactId) : null;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] max-w-5xl overflow-y-auto p-0">
                <header className={cn('border-b p-4', factionTheme?.cardGradient)}>
                    <DialogTitle asChild>
                        <div className="flex flex-col items-center gap-1 text-center">
                            <span className="text-[10px] uppercase tracking-wider text-muted-foreground">
                                {isEditing ? 'Editar roster' : 'Adicionar ao roster'}
                            </span>
                            {hero.title && <p className="text-xs uppercase tracking-wider text-muted-foreground">{hero.title}</p>}
                            <h2 className={cn('text-2xl font-bold leading-tight', factionTheme?.headerText)}>{hero.name}</h2>
                        </div>
                    </DialogTitle>
                </header>

                <form onSubmit={submit} className="flex flex-col gap-4 p-4">
                    {/* Upper: 3-col (slots equipamentos | portrait | slots equipamentos) */}
                    <div className="mx-auto grid w-full max-w-3xl grid-cols-1 gap-3 md:grid-cols-[140px_minmax(220px,1fr)_140px]">
                        {/* Esquerda: Arma (em breve) → Capacete (em breve) → Assinatura */}
                        <div className="flex flex-col gap-2">
                            <SlotEdit icon={<Sword className="size-3" />} label="Arma" disabled>
                                <span className="text-[10px] text-muted-foreground">em breve</span>
                            </SlotEdit>
                            <SlotEdit icon={<Shield className="size-3" />} label="Capacete" disabled>
                                <span className="text-[10px] text-muted-foreground">em breve</span>
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
                            <div className={cn('relative aspect-[3/4] w-full overflow-hidden rounded-lg border-2', factionTheme?.cardGradient, factionTheme?.cardBorder)}>
                                {hero.portrait_url ? (
                                    <img src={hero.portrait_url} alt={hero.name} className="size-full object-contain object-bottom" />
                                ) : (
                                    <div className="grid size-full place-items-center text-xs text-muted-foreground">sem imagem</div>
                                )}
                            </div>
                            <Badge className={cn('text-xs', visualTier.badge)}>{visualTier.label}</Badge>

                            <div className="grid w-full grid-cols-2 gap-2">
                                <div className="space-y-1">
                                    <Label htmlFor="rh-level" className="text-[10px] uppercase">Nível (max {maxLevel})</Label>
                                    <Input id="rh-level" type="number" min={1} max={maxLevel} value={level} onChange={(e) => setLevel(Math.min(maxLevel, Math.max(1, Number(e.target.value) || 1)))} className="h-8" />
                                    {errors.level && <p className="text-[10px] text-destructive">{errors.level}</p>}
                                </div>
                                <div className="space-y-1">
                                    <Label htmlFor="rh-asc" className="text-[10px] uppercase">Ascensão</Label>
                                    <Select value={ascensionCode || 'none'} onValueChange={(v) => setAscensionCode(v === 'none' ? '' : v)}>
                                        <SelectTrigger id="rh-asc" className="h-8 text-xs">
                                            <SelectValue placeholder="—" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="none">— —</SelectItem>
                                            {validStages.map((stage) => (
                                                <SelectItem key={stage.code} value={stage.code}>{stage.label}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                        </div>

                        {/* Direita: Peitoral (em breve) → Bota (em breve) → Artefato OU Coleção */}
                        <div className="flex flex-col gap-2">
                            <SlotEdit icon={<Shirt className="size-3" />} label="Peitoral" disabled>
                                <span className="text-[10px] text-muted-foreground">em breve</span>
                            </SlotEdit>
                            <SlotEdit icon={<Footprints className="size-3" />} label="Bota" disabled>
                                <span className="text-[10px] text-muted-foreground">em breve</span>
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
                                        {availableArtifacts.filter((a) => a.kind === 'dura').map((a) => (
                                            <SelectItem key={`a-${a.id}`} value={`a:${a.id}`}>Dura's · {a.name}</SelectItem>
                                        ))}
                                        {availableArtifacts.filter((a) => a.kind === 'class').map((a) => (
                                            <SelectItem key={`a-${a.id}`} value={`a:${a.id}`}>Classe · {a.name}</SelectItem>
                                        ))}
                                        {availableCollections.map((c) => (
                                            <SelectItem key={`c-${c.id}`} value={`c:${c.id}`}>Coleção · {c.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {selectedArtifact && (
                                    <Input
                                        type="number"
                                        min={1}
                                        max={selectedArtifact.max_stars}
                                        value={artifactStarLevel ?? 1}
                                        onChange={(e) => setArtifactStarLevel(Math.min(selectedArtifact.max_stars, Math.max(1, Number(e.target.value) || 1)))}
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
                                <Badge variant={furniture >= 3 ? 'default' : 'outline'} className={cn('h-4 px-1 text-[9px]', furniture >= 3 && 'bg-emerald-500')}>F3</Badge>
                                <Badge variant={furniture >= 9 ? 'default' : 'outline'} className={cn('h-4 px-1 text-[9px]', furniture >= 9 && 'bg-gradient-to-r from-amber-400 to-orange-500 text-stone-900')}>F9</Badge>
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
                            <Label htmlFor="rh-notes" className="flex items-center gap-1.5 text-xs">
                                <ScrollText className="size-3" />
                                Notas
                            </Label>
                            <textarea
                                id="rh-notes"
                                className="flex min-h-[60px] w-full rounded-md border border-input bg-background px-2 py-1 text-xs"
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                maxLength={1000}
                                rows={2}
                                placeholder="Opcional"
                            />
                        </div>
                    </div>

                    <div className="flex justify-end gap-2 border-t pt-3">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={saving}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={saving}>
                            {saving ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                            {saving ? 'Salvando...' : isEditing ? 'Salvar' : 'Adicionar'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
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
        <div className={cn('flex flex-col gap-1 rounded-md border bg-card p-1.5', disabled && 'opacity-50', expand && 'flex-1')}>
            <div className="flex items-center gap-1 text-[9px] font-medium uppercase tracking-wide text-muted-foreground">
                {icon}
                <span className="truncate">{label}</span>
            </div>
            {children}
        </div>
    );
}
