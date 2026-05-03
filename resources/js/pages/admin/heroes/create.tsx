import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft, Plus } from 'lucide-react';
import { type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { Faction, HeroClass, Tier } from '@/types/afk';

interface PageProps {
    factions: Faction[];
    heroClasses: HeroClass[];
    tiers: Tier[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Aprovar heróis', href: '/admin/heroes/pending' },
    { title: 'Adicionar manualmente', href: '/admin/heroes/create' },
];

interface ManualHeroForm {
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

export default function HeroCreateManual({ factions, heroClasses, tiers }: PageProps) {
    const { data, setData, post, processing, errors } = useForm<ManualHeroForm>({
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
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        post('/admin/heroes');
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Adicionar herói" />

            <div className="mx-auto flex w-full max-w-2xl flex-col gap-4 p-4">
                <div className="flex items-center gap-2">
                    <Link href="/admin/heroes/pending" className="text-muted-foreground hover:text-foreground">
                        <ArrowLeft className="size-4" />
                    </Link>
                    <h1 className="text-2xl font-semibold">Adicionar herói manualmente</h1>
                </div>
                <p className="text-sm text-muted-foreground">
                    Use este formulário para heróis que o sync automático não conseguiu capturar (Saveas, Daimon, Arden, etc.). O herói criado fica ativo e visível no catálogo imediatamente.
                </p>

                <form onSubmit={handleSubmit} className="flex flex-col gap-4 rounded-xl border bg-card p-4">
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
                            <p className="text-xs text-muted-foreground">Apenas letras minúsculas, números e hífens.</p>
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
                        {errors.title && <p className="text-xs text-destructive">{errors.title}</p>}
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
                                        <SelectItem key={f.id} value={String(f.id)}>
                                            {f.name_pt}
                                        </SelectItem>
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
                                        <SelectItem key={c.id} value={String(c.id)}>
                                            {c.name_pt}
                                        </SelectItem>
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
                                        <SelectItem key={t.id} value={String(t.id)}>
                                            {t.name_pt}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {errors.tier_id && <p className="text-xs text-destructive">{errors.tier_id}</p>}
                        </div>
                    </div>

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                        <div className="flex flex-col gap-1.5">
                            <Label>Gênero</Label>
                            <Select value={data.gender || 'unset'} onValueChange={(v) => setData('gender', v === 'unset' ? '' : (v as ManualHeroForm['gender']))}>
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
                            <Label htmlFor="role_label">Papel (texto livre)</Label>
                            <Input
                                id="role_label"
                                value={data.role_label}
                                onChange={(e) => setData('role_label', e.target.value)}
                                placeholder="Burst Damage"
                            />
                        </div>
                    </div>

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
                        <Label htmlFor="source_url">URL da fonte (Fandom Wiki)</Label>
                        <Input
                            id="source_url"
                            type="url"
                            value={data.source_url}
                            onChange={(e) => setData('source_url', e.target.value)}
                            placeholder="https://afk-arena.fandom.com/wiki/Saveas"
                        />
                        {errors.source_url && <p className="text-xs text-destructive">{errors.source_url}</p>}
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <Link href="/admin/heroes/pending">
                            <Button type="button" variant="outline">
                                Cancelar
                            </Button>
                        </Link>
                        <Button type="submit" disabled={processing}>
                            <Plus className="size-4" />
                            {processing ? 'Salvando...' : 'Criar herói'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
