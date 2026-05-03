import axios from 'axios';
import { Loader2, PawPrint, Save } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Beast } from '@/types/afk';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    beast: Beast | null;
    onSaved?: () => void;
}

interface FormState {
    slug: string;
    name: string;
    description: string;
    strength_buff: string;
    intelligence_buff: string;
    agility_buff: string;
    portrait_url: string;
    source_url: string;
    is_active: boolean;
}

const EMPTY: FormState = {
    slug: '',
    name: '',
    description: '',
    strength_buff: '0',
    intelligence_buff: '0',
    agility_buff: '0',
    portrait_url: '',
    source_url: '',
    is_active: true,
};

export function BeastFormModal({ open, onOpenChange, beast, onSaved }: Props) {
    const isEditing = beast !== null;
    const [data, setData] = useState<FormState>(EMPTY);
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (!open) {
            setData(EMPTY);
            setErrors({});
            return;
        }
        if (beast) {
            setData({
                slug: beast.slug,
                name: beast.name,
                description: beast.description ?? '',
                strength_buff: String(beast.strength_buff),
                intelligence_buff: String(beast.intelligence_buff),
                agility_buff: String(beast.agility_buff),
                portrait_url: beast.portrait_url ?? '',
                source_url: beast.source_url ?? '',
                is_active: beast.is_active,
            });
        } else {
            setData(EMPTY);
        }
        setErrors({});
    }, [open, beast?.id]);

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});

        const payload = {
            slug: data.slug,
            name: data.name,
            description: data.description || null,
            strength_buff: data.strength_buff === '' ? 0 : Number(data.strength_buff),
            intelligence_buff: data.intelligence_buff === '' ? 0 : Number(data.intelligence_buff),
            agility_buff: data.agility_buff === '' ? 0 : Number(data.agility_buff),
            portrait_url: data.portrait_url || null,
            source_url: data.source_url || null,
            is_active: data.is_active,
        };

        try {
            if (isEditing && beast) {
                await axios.patch(`/admin/beasts/${beast.id}`, payload);
            } else {
                await axios.post('/admin/beasts', payload);
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
                <DialogTitle asChild>
                    <div className="flex flex-col items-center gap-1 text-center">
                        <span className="flex items-center gap-2 text-[10px] uppercase tracking-wider text-muted-foreground">
                            <PawPrint className="size-3" />
                            Pet
                        </span>
                        <h2 className="text-xl font-bold leading-tight">{isEditing ? 'Editar beast' : 'Novo beast'}</h2>
                    </div>
                </DialogTitle>
                <DialogDescription className="text-center">
                    Beasts buffam todos os heróis do tipo correspondente (Força/Inteligência/Agilidade) na equipe ativa.
                </DialogDescription>

                <form onSubmit={submit} className="flex flex-col gap-3 pt-2">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="b-slug">Slug *</Label>
                            <Input
                                id="b-slug"
                                value={data.slug}
                                onChange={(e) => setData((s) => ({ ...s, slug: e.target.value }))}
                                placeholder="dreary-ball"
                                pattern="[a-z0-9-]+"
                                disabled={isEditing}
                            />
                            <p className="text-[10px] text-muted-foreground">Apenas minúsculas, números e hífens.</p>
                            {errors.slug && <p className="text-xs text-destructive">{errors.slug}</p>}
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="b-name">Nome *</Label>
                            <Input
                                id="b-name"
                                value={data.name}
                                onChange={(e) => setData((s) => ({ ...s, name: e.target.value }))}
                                placeholder="Dreary Ball"
                            />
                            {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                        </div>
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="b-desc">Descrição</Label>
                        <textarea
                            id="b-desc"
                            value={data.description}
                            onChange={(e) => setData((s) => ({ ...s, description: e.target.value }))}
                            className="flex min-h-[60px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                            rows={3}
                            maxLength={5000}
                            placeholder="Skills/efeitos do beast quando ativo..."
                        />
                    </div>

                    <div className="grid grid-cols-3 gap-3">
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="b-str">Buff Força</Label>
                            <Input id="b-str" type="number" min={0} value={data.strength_buff} onChange={(e) => setData((s) => ({ ...s, strength_buff: e.target.value }))} />
                            <p className="text-[10px] text-muted-foreground">Pontos aplicados a heróis Strength.</p>
                            {errors.strength_buff && <p className="text-[10px] text-destructive">{errors.strength_buff}</p>}
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="b-int">Buff Inteligência</Label>
                            <Input id="b-int" type="number" min={0} value={data.intelligence_buff} onChange={(e) => setData((s) => ({ ...s, intelligence_buff: e.target.value }))} />
                            <p className="text-[10px] text-muted-foreground">Pontos aplicados a heróis Intelligence.</p>
                            {errors.intelligence_buff && <p className="text-[10px] text-destructive">{errors.intelligence_buff}</p>}
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="b-agi">Buff Agilidade</Label>
                            <Input id="b-agi" type="number" min={0} value={data.agility_buff} onChange={(e) => setData((s) => ({ ...s, agility_buff: e.target.value }))} />
                            <p className="text-[10px] text-muted-foreground">Pontos aplicados a heróis Agility.</p>
                            {errors.agility_buff && <p className="text-[10px] text-destructive">{errors.agility_buff}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="b-portrait">URL da imagem</Label>
                            <Input
                                id="b-portrait"
                                type="url"
                                value={data.portrait_url}
                                onChange={(e) => setData((s) => ({ ...s, portrait_url: e.target.value }))}
                                placeholder="https://..."
                            />
                            {errors.portrait_url && <p className="text-[10px] text-destructive">{errors.portrait_url}</p>}
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="b-source">URL fonte (Fandom)</Label>
                            <Input
                                id="b-source"
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
                        Ativo (visível para escolher em formações)
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
