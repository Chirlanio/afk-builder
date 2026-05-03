import { useForm } from '@inertiajs/react';
import { Loader2, Plus } from 'lucide-react';
import { type FormEvent, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import type { Faction, HeroClass, Tier } from '@/types/afk';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    factions: Faction[];
    heroClasses: HeroClass[];
    tiers: Tier[];
}

interface FormShape {
    slug: string;
    name: string;
    title: string;
    faction_id: string;
    hero_class_id: string;
    tier_id: string;
    gender: '' | 'male' | 'female' | 'other';
    role_label: string;
    portrait_url: string;
    source_url: string;
    [key: string]: string;
}

const EMPTY: FormShape = {
    slug: '',
    name: '',
    title: '',
    faction_id: '',
    hero_class_id: '',
    tier_id: '',
    gender: '',
    role_label: '',
    portrait_url: '',
    source_url: '',
};

export function CreateHeroModal({ open, onOpenChange, factions, heroClasses, tiers }: Props) {
    const { data, setData, post, processing, errors, reset, clearErrors } = useForm<FormShape>(EMPTY);

    useEffect(() => {
        if (!open) {
            reset();
            clearErrors();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/heroes', {
            preserveScroll: true,
            onSuccess: () => onOpenChange(false),
        });
    };

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>Adicionar herói manualmente</DialogTitle>
                    <DialogDescription>
                        Para heróis que o sync automático não conseguiu capturar (Saveas, Daimon, etc.). Fica ativo e visível no catálogo imediatamente.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={handleSubmit} className="flex flex-col gap-3">
                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="slug">Slug *</Label>
                            <Input
                                id="slug"
                                value={data.slug}
                                onChange={(e) => setData('slug', e.target.value)}
                                placeholder="saveas"
                                pattern="[a-z0-9-]+"
                            />
                            <p className="text-xs text-muted-foreground">Apenas minúsculas, números e hífens.</p>
                            {errors.slug && <p className="text-xs text-destructive">{errors.slug}</p>}
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="name">Nome *</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                placeholder="Saveas"
                            />
                            {errors.name && <p className="text-xs text-destructive">{errors.name}</p>}
                        </div>
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="title">Título</Label>
                        <Input
                            id="title"
                            value={data.title}
                            onChange={(e) => setData('title', e.target.value)}
                            placeholder="Slayer of Souls"
                        />
                    </div>

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div className="flex flex-col gap-1.5">
                            <Label>Facção *</Label>
                            <Select value={data.faction_id} onValueChange={(v) => setData('faction_id', v)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Selecionar..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {factions.map((f) => (
                                        <SelectItem key={f.id} value={String(f.id)}>{f.name_pt}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.faction_id && <p className="text-xs text-destructive">{errors.faction_id}</p>}
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <Label>Classe *</Label>
                            <Select value={data.hero_class_id} onValueChange={(v) => setData('hero_class_id', v)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Selecionar..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {heroClasses.map((c) => (
                                        <SelectItem key={c.id} value={String(c.id)}>{c.name_pt}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.hero_class_id && <p className="text-xs text-destructive">{errors.hero_class_id}</p>}
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <Label>Tier *</Label>
                            <Select value={data.tier_id} onValueChange={(v) => setData('tier_id', v)}>
                                <SelectTrigger>
                                    <SelectValue placeholder="Selecionar..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {tiers.map((t) => (
                                        <SelectItem key={t.id} value={String(t.id)}>{t.name_pt}</SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.tier_id && <p className="text-xs text-destructive">{errors.tier_id}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div className="flex flex-col gap-1.5">
                            <Label>Gênero</Label>
                            <Select value={data.gender || 'unset'} onValueChange={(v) => setData('gender', v === 'unset' ? '' : (v as FormShape['gender']))}>
                                <SelectTrigger>
                                    <SelectValue placeholder="—" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="unset">—</SelectItem>
                                    <SelectItem value="male">Masculino</SelectItem>
                                    <SelectItem value="female">Feminino</SelectItem>
                                    <SelectItem value="other">Outro</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="role_label">Papel</Label>
                            <Input
                                id="role_label"
                                value={data.role_label}
                                onChange={(e) => setData('role_label', e.target.value)}
                                placeholder="Burst Damage"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="portrait_url">URL do portrait</Label>
                            <Input
                                id="portrait_url"
                                type="url"
                                value={data.portrait_url}
                                onChange={(e) => setData('portrait_url', e.target.value)}
                                placeholder="https://static.wikia.nocookie.net/..."
                            />
                            {errors.portrait_url && <p className="text-xs text-destructive">{errors.portrait_url}</p>}
                        </div>

                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="source_url">URL Fandom</Label>
                            <Input
                                id="source_url"
                                type="url"
                                value={data.source_url}
                                onChange={(e) => setData('source_url', e.target.value)}
                                placeholder="https://afk-arena.fandom.com/wiki/Saveas"
                            />
                            {errors.source_url && <p className="text-xs text-destructive">{errors.source_url}</p>}
                        </div>
                    </div>

                    <DialogFooter className="gap-2 sm:gap-2">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={processing}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? <Loader2 className="size-4 animate-spin" /> : <Plus className="size-4" />}
                            {processing ? 'Salvando...' : 'Criar herói'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}
