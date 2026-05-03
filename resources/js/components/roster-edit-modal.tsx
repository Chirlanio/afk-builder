import axios from 'axios';
import { Flame, Gem, Hotel, Loader2, Package, PenLine, Save, Star } from 'lucide-react';
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
                    {/* Upper: 3-col (slots editáveis | portrait | slots editáveis) */}
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-[1fr_minmax(180px,260px)_1fr]">
                        {/* Esquerda: Artefato, Coleção, Assinatura */}
                        <div className="flex flex-col gap-2">
                            <SlotEdit icon={<Gem className="size-3" />} label="Artefato">
                                <Select
                                    value={artifactId ? String(artifactId) : 'none'}
                                    onValueChange={(v) => {
                                        if (v === 'none') {
                                            setArtifactId(null);
                                            setArtifactStarLevel(null);
                                        } else {
                                            setArtifactId(Number(v));
                                            if (!artifactStarLevel) setArtifactStarLevel(1);
                                            // Artefato sobrescreve coleção
                                            setCollectionId(null);
                                        }
                                    }}
                                >
                                    <SelectTrigger className="h-8 text-xs">
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

                            <SlotEdit icon={<Package className="size-3" />} label="Coleção">
                                <Select
                                    value={collectionId ? String(collectionId) : 'none'}
                                    onValueChange={(v) => {
                                        if (v === 'none') {
                                            setCollectionId(null);
                                        } else {
                                            setCollectionId(Number(v));
                                            // Coleção sobrescreve artefato (mesmo slot no jogo)
                                            setArtifactId(null);
                                            setArtifactStarLevel(null);
                                        }
                                    }}
                                >
                                    <SelectTrigger className="h-8 text-xs">
                                        <SelectValue placeholder="—" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="none">— nenhuma —</SelectItem>
                                        {availableCollections.map((c) => (
                                            <SelectItem key={c.id} value={String(c.id)}>{c.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {collectionId && (
                                    <p className="mt-1 text-[10px] text-muted-foreground">Substitui o artefato.</p>
                                )}
                            </SlotEdit>

                            <SlotEdit icon={<Star className="size-3" />} label="Assinatura (0-40)">
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

                        {/* Direita: Móveis, Inscrições, Notas (curtas) */}
                        <div className="flex flex-col gap-2">
                            <SlotEdit icon={<Hotel className="size-3" />} label="Móveis (0-9)">
                                <Input
                                    type="number"
                                    min={0}
                                    max={9}
                                    value={furniture}
                                    onChange={(e) => setFurniture(Math.min(9, Math.max(0, Number(e.target.value) || 0)))}
                                    className="h-8"
                                />
                                <div className="mt-1 flex gap-1 text-[10px]">
                                    <Badge variant={furniture >= 3 ? 'default' : 'outline'} className="h-4 px-1 text-[9px]">F3</Badge>
                                    <Badge variant={furniture >= 9 ? 'default' : 'outline'} className={cn('h-4 px-1 text-[9px]', furniture >= 9 && 'bg-gradient-to-r from-amber-400 to-orange-500 text-stone-900')}>F9</Badge>
                                </div>
                            </SlotEdit>

                            <SlotEdit icon={<PenLine className="size-3" />} label="Inscrições (0-100)">
                                <Input
                                    type="number"
                                    min={0}
                                    max={100}
                                    value={engraving}
                                    onChange={(e) => setEngraving(Math.min(100, Math.max(0, Number(e.target.value) || 0)))}
                                    className="h-8"
                                />
                            </SlotEdit>

                            <SlotEdit icon={<Flame className="size-3" />} label="Notas" expand>
                                <textarea
                                    className="flex min-h-[60px] w-full rounded-md border border-input bg-background px-2 py-1 text-xs"
                                    value={notes}
                                    onChange={(e) => setNotes(e.target.value)}
                                    maxLength={1000}
                                    rows={3}
                                    placeholder="Opcional"
                                />
                            </SlotEdit>
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
        <div className={cn('flex flex-col gap-1 rounded-lg border bg-card p-2', disabled && 'opacity-50', expand && 'flex-1')}>
            <div className="flex items-center gap-1.5 text-[10px] font-medium uppercase tracking-wide text-muted-foreground">
                {icon}
                <span>{label}</span>
            </div>
            {children}
        </div>
    );
}
