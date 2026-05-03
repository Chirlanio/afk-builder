import axios from 'axios';
import { Loader2, Package, Save } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Collection } from '@/types/afk';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    collection: Collection | null;
    onSaved?: () => void;
}

interface FormState {
    slug: string;
    name: string;
    description: string;
    hp_bonus: string;
    attack_bonus: string;
    defense_bonus: string;
    portrait_url: string;
    source_url: string;
    is_active: boolean;
}

const EMPTY: FormState = {
    slug: '',
    name: '',
    description: '',
    hp_bonus: '0',
    attack_bonus: '0',
    defense_bonus: '0',
    portrait_url: '',
    source_url: '',
    is_active: true,
};

export function CollectionFormModal({ open, onOpenChange, collection, onSaved }: Props) {
    const isEditing = collection !== null;
    const [data, setData] = useState<FormState>(EMPTY);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (!open) {
            setData(EMPTY);
            setErrors({});
            return;
        }
        if (collection) {
            setData({
                slug: collection.slug,
                name: collection.name,
                description: collection.description ?? '',
                hp_bonus: String(collection.hp_bonus),
                attack_bonus: String(collection.attack_bonus),
                defense_bonus: String(collection.defense_bonus),
                portrait_url: collection.portrait_url ?? '',
                source_url: collection.source_url ?? '',
                is_active: collection.is_active,
            });
        } else {
            setData(EMPTY);
        }
        setErrors({});
    }, [open, collection?.id]);

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});

        const payload = {
            slug: data.slug,
            name: data.name,
            description: data.description || null,
            hp_bonus: data.hp_bonus === '' ? 0 : Number(data.hp_bonus),
            attack_bonus: data.attack_bonus === '' ? 0 : Number(data.attack_bonus),
            defense_bonus: data.defense_bonus === '' ? 0 : Number(data.defense_bonus),
            portrait_url: data.portrait_url || null,
            source_url: data.source_url || null,
            is_active: data.is_active,
        };

        try {
            if (isEditing && collection) {
                await axios.patch(`/admin/collections/${collection.id}`, payload);
            } else {
                await axios.post('/admin/collections', payload);
            }
            onSaved?.();
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

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[92vh] max-w-3xl overflow-y-auto">
                <DialogTitle className="flex items-center gap-2 text-xl font-semibold">
                    <Package className="size-5 text-primary" />
                    {isEditing ? 'Editar coleção' : 'Nova coleção'}
                </DialogTitle>
                <DialogDescription>
                    Coleções da Galeria Tenebrosa fornecem bônus de HP/ATK/DEF e ocupam o slot de artefato do herói.
                </DialogDescription>

                <form onSubmit={submit} className="flex flex-col gap-3 pt-2">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="c-slug">Slug *</Label>
                            <Input
                                id="c-slug"
                                value={data.slug}
                                onChange={(e) => setData((s) => ({ ...s, slug: e.target.value }))}
                                placeholder="reapers-talon"
                                pattern="[a-z0-9-]+"
                                disabled={isEditing}
                            />
                            <p className="text-[10px] text-muted-foreground">Apenas minúsculas, números e hífens.</p>
                            {errors.slug && <p className="text-xs text-destructive">{errors.slug}</p>}
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="c-name">Nome *</Label>
                            <Input
                                id="c-name"
                                value={data.name}
                                onChange={(e) => setData((s) => ({ ...s, name: e.target.value }))}
                                placeholder="Reaper's Talon"
                            />
                            {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                        </div>
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="c-desc">Descrição</Label>
                        <textarea
                            id="c-desc"
                            value={data.description}
                            onChange={(e) => setData((s) => ({ ...s, description: e.target.value }))}
                            className="flex min-h-[60px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            rows={3}
                            maxLength={5000}
                            placeholder="Efeito da coleção quando equipada..."
                        />
                    </div>

                    <div className="grid grid-cols-3 gap-3">
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="c-hp">HP base +</Label>
                            <Input id="c-hp" type="number" min={0} value={data.hp_bonus} onChange={(e) => setData((s) => ({ ...s, hp_bonus: e.target.value }))} />
                            {errors.hp_bonus && <p className="text-[10px] text-destructive">{errors.hp_bonus}</p>}
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="c-atk">ATK base +</Label>
                            <Input id="c-atk" type="number" min={0} value={data.attack_bonus} onChange={(e) => setData((s) => ({ ...s, attack_bonus: e.target.value }))} />
                            {errors.attack_bonus && <p className="text-[10px] text-destructive">{errors.attack_bonus}</p>}
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="c-def">DEF base +</Label>
                            <Input id="c-def" type="number" min={0} value={data.defense_bonus} onChange={(e) => setData((s) => ({ ...s, defense_bonus: e.target.value }))} />
                            {errors.defense_bonus && <p className="text-[10px] text-destructive">{errors.defense_bonus}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="c-portrait">URL da imagem</Label>
                            <Input
                                id="c-portrait"
                                type="url"
                                value={data.portrait_url}
                                onChange={(e) => setData((s) => ({ ...s, portrait_url: e.target.value }))}
                                placeholder="https://..."
                            />
                            {errors.portrait_url && <p className="text-[10px] text-destructive">{errors.portrait_url}</p>}
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="c-source">URL fonte (Fandom)</Label>
                            <Input
                                id="c-source"
                                type="url"
                                value={data.source_url}
                                onChange={(e) => setData((s) => ({ ...s, source_url: e.target.value }))}
                                placeholder="https://afk-arena.fandom.com/wiki/..."
                            />
                            {errors.source_url && <p className="text-[10px] text-destructive">{errors.source_url}</p>}
                        </div>
                    </div>

                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(e) => setData((s) => ({ ...s, is_active: e.target.checked }))}
                            className="size-4 rounded border-input"
                        />
                        Ativa (visível para equipar nos heróis)
                    </label>

                    <div className="flex justify-end gap-2 border-t pt-3">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={saving}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={saving}>
                            {saving ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                            {saving ? 'Salvando...' : isEditing ? 'Salvar' : 'Criar'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
