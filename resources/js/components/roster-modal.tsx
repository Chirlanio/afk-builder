import { useForm } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { ASCENSION_STAGES, ascensionStagesForTier, FACTION_THEME, TIER_THEME } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { FactionCode, Hero, TierCode, UserHero } from '@/types/afk';

interface RosterModalProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    hero: Hero | null;
    factionCode: FactionCode | null;
    tierCode: TierCode | null;
    userHero?: UserHero;
}

interface FormShape {
    hero_id: number | null;
    level: number;
    ascension_code: string;
    signature_item: number;
    furniture: number;
    engraving: number;
    notes: string;
    [key: string]: string | number | null;
}

export function RosterModal({ open, onOpenChange, hero, factionCode, tierCode, userHero }: RosterModalProps) {
    const tierTheme = tierCode ? TIER_THEME[tierCode] : null;
    const factionTheme = factionCode ? FACTION_THEME[factionCode] : null;
    const validStages = tierTheme ? ascensionStagesForTier(tierTheme.maxLevel) : ASCENSION_STAGES;
    const isEditing = userHero !== undefined;

    const form = useForm<FormShape>({
        hero_id: hero?.id ?? null,
        level: userHero?.level ?? 1,
        ascension_code: userHero?.ascension_code ?? '',
        signature_item: userHero?.signature_item ?? 0,
        furniture: userHero?.furniture ?? 0,
        engraving: userHero?.engraving ?? 0,
        notes: userHero?.notes ?? '',
    });

    useEffect(() => {
        if (!open || !hero) return;

        form.setData({
            hero_id: hero.id,
            level: userHero?.level ?? 1,
            ascension_code: userHero?.ascension_code ?? '',
            signature_item: userHero?.signature_item ?? 0,
            furniture: userHero?.furniture ?? 0,
            engraving: userHero?.engraving ?? 0,
            notes: userHero?.notes ?? '',
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, hero?.id, userHero?.id]);

    if (!hero) return null;

    const submit = (event: React.FormEvent) => {
        event.preventDefault();

        const payload = {
            level: form.data.level,
            ascension_code: form.data.ascension_code || null,
            signature_item: form.data.signature_item,
            furniture: form.data.furniture,
            engraving: form.data.engraving,
            notes: form.data.notes || null,
        };

        if (isEditing && userHero) {
            form.transform(() => payload);
            form.patch(`/user/heroes/${userHero.id}`, {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        } else {
            form.transform(() => ({ ...payload, hero_id: hero.id }));
            form.post('/user/heroes', {
                preserveScroll: true,
                onSuccess: () => onOpenChange(false),
            });
        }
    };

    const remove = () => {
        if (!userHero) return;
        if (!confirm(`Remover ${hero.name} do seu roster?`)) return;

        form.delete(`/user/heroes/${userHero.id}`, {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    const maxLevel = tierTheme?.maxLevel ?? 240;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle className={cn('flex items-center gap-2', factionTheme?.headerText)}>
                        {hero.portrait_url && (
                            <img src={hero.portrait_url} alt="" className="size-10 rounded-md object-cover" />
                        )}
                        <span className="flex flex-col">
                            <span>{hero.name}</span>
                            {hero.title && <span className="text-xs font-normal text-muted-foreground">{hero.title}</span>}
                        </span>
                    </DialogTitle>
                    <DialogDescription>
                        {isEditing ? 'Editar dados do herói no seu roster.' : 'Adicionar este herói ao seu roster.'}
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="space-y-3">
                    <div className="grid grid-cols-2 gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="level">Nível (max {maxLevel})</Label>
                            <Input
                                id="level"
                                type="number"
                                min={1}
                                max={maxLevel}
                                value={form.data.level}
                                onChange={(e) => form.setData('level', Math.min(maxLevel, Math.max(1, Number(e.target.value) || 1)))}
                            />
                            {form.errors.level && <p className="text-xs text-destructive">{form.errors.level}</p>}
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="ascension">Ascensão</Label>
                            <Select
                                value={form.data.ascension_code || 'none'}
                                onValueChange={(v) => form.setData('ascension_code', v === 'none' ? '' : v)}
                            >
                                <SelectTrigger id="ascension">
                                    <SelectValue placeholder="—" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">— não definido —</SelectItem>
                                    {validStages.map((stage) => (
                                        <SelectItem key={stage.code} value={stage.code}>
                                            {stage.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.ascension_code && <p className="text-xs text-destructive">{form.errors.ascension_code}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-3 gap-3">
                        <div className="space-y-1">
                            <Label htmlFor="si" title="Assinatura (Signature Item) — equipamento exclusivo do herói, vai de 0 a +40.">
                                Assinatura <span className="text-muted-foreground">(0-40)</span>
                            </Label>
                            <Input
                                id="si"
                                type="number"
                                min={0}
                                max={40}
                                value={form.data.signature_item}
                                onChange={(e) => form.setData('signature_item', Math.min(40, Math.max(0, Number(e.target.value) || 0)))}
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="furn" title="Móveis (Furniture) — peças de mobília que dão buffs em set de 3 e 9.">
                                Móveis <span className="text-muted-foreground">(0-9)</span>
                            </Label>
                            <Input
                                id="furn"
                                type="number"
                                min={0}
                                max={9}
                                value={form.data.furniture}
                                onChange={(e) => form.setData('furniture', Math.min(9, Math.max(0, Number(e.target.value) || 0)))}
                            />
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="eng" title="Inscrições (Engraving) — bônus de stats em níveis de 0 a 100, exclusivo de heróis Ascendidos.">
                                Inscrições <span className="text-muted-foreground">(0-100)</span>
                            </Label>
                            <Input
                                id="eng"
                                type="number"
                                min={0}
                                max={100}
                                value={form.data.engraving}
                                onChange={(e) => form.setData('engraving', Math.min(100, Math.max(0, Number(e.target.value) || 0)))}
                            />
                        </div>
                    </div>

                    <div className="space-y-1">
                        <Label htmlFor="notes">Notas (opcional)</Label>
                        <textarea
                            id="notes"
                            className="flex min-h-[60px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-hidden focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50"
                            value={form.data.notes}
                            onChange={(e) => form.setData('notes', e.target.value)}
                            maxLength={1000}
                            rows={2}
                        />
                    </div>

                    <DialogFooter className="gap-2 sm:gap-2">
                        {isEditing && (
                            <Button
                                type="button"
                                variant="destructive"
                                onClick={remove}
                                disabled={form.processing}
                                className="mr-auto"
                            >
                                <Trash2 className="size-4" />
                                Remover
                            </Button>
                        )}
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={form.processing}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {isEditing ? 'Salvar' : 'Adicionar'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
