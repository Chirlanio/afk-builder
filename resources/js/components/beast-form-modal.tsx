import { BeastBuffsEditor, hasInvalidBuffs, normalizeBuffs, type BuffsMap } from '@/components/beast-buffs-editor';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import type { Beast } from '@/types/afk';
import axios from 'axios';
import { ImagePlus, Loader2, PawPrint, Save, X } from 'lucide-react';
import { useEffect, useRef, useState, type FormEvent } from 'react';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    beast: Beast | null;
    onSaved?: () => void;
}

type Scope = 'strength' | 'intelligence' | 'agility' | 'team';

interface FormState {
    slug: string;
    name: string;
    description: string;
    buffs_strength: BuffsMap;
    buffs_intelligence: BuffsMap;
    buffs_agility: BuffsMap;
    buffs_team: BuffsMap;
    tier: string;
    tier_note: string;
    portrait_url: string;
    source_url: string;
    is_active: boolean;
}

const EMPTY: FormState = {
    slug: '',
    name: '',
    description: '',
    buffs_strength: {},
    buffs_intelligence: {},
    buffs_agility: {},
    buffs_team: {},
    tier: '',
    tier_note: '',
    portrait_url: '',
    source_url: '',
    is_active: true,
};

const BEAST_TIERS = ['', 'S+', 'S', 'A', 'B', 'C', 'D', 'F'];

const SCOPE_LABELS: Record<Scope, string> = {
    strength: 'Força',
    intelligence: 'Inteligência',
    agility: 'Agilidade',
    team: 'Equipe',
};

const SCOPE_ACCENT: Record<Scope, 'rose' | 'sky' | 'emerald' | 'amber'> = {
    strength: 'rose',
    intelligence: 'sky',
    agility: 'emerald',
    team: 'amber',
};

export function BeastFormModal({ open, onOpenChange, beast, onSaved }: Props) {
    const isEditing = beast !== null;
    const [data, setData] = useState<FormState>(EMPTY);
    const [scope, setScope] = useState<Scope>('strength');
    const [saving, setSaving] = useState(false);
    const [uploading, setUploading] = useState(false);
    const [uploadError, setUploadError] = useState<string | null>(null);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const fileInputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        if (!open) {
            setData(EMPTY);
            setErrors({});
            setUploadError(null);
            setScope('strength');
            return;
        }
        if (beast) {
            setData({
                slug: beast.slug,
                name: beast.name,
                description: beast.description ?? '',
                buffs_strength: (beast.buffs_strength ?? {}) as BuffsMap,
                buffs_intelligence: (beast.buffs_intelligence ?? {}) as BuffsMap,
                buffs_agility: (beast.buffs_agility ?? {}) as BuffsMap,
                buffs_team: (beast.buffs_team ?? {}) as BuffsMap,
                tier: beast.tier ?? '',
                tier_note: beast.tier_note ?? '',
                portrait_url: beast.portrait_url ?? '',
                source_url: beast.source_url ?? '',
                is_active: beast.is_active,
            });
        } else {
            setData(EMPTY);
        }
        setErrors({});
        // Reseta o form só ao abrir ou trocar de besta — não a cada re-render do objeto.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, beast?.id]);

    const handleUpload = async (file: File) => {
        setUploading(true);
        setUploadError(null);
        try {
            const form = new FormData();
            form.append('image', file);
            const res = await axios.post<{ url: string }>('/admin/beasts/upload-image', form, {
                headers: { 'Content-Type': 'multipart/form-data' },
            });
            setData((s) => ({ ...s, portrait_url: res.data.url }));
        } catch (err: unknown) {
            const e = err as { response?: { data?: { message?: string; errors?: { image?: string[] } } } };
            const msg = e.response?.data?.errors?.image?.[0] ?? e.response?.data?.message ?? 'Falha no upload da imagem.';
            setUploadError(msg);
        } finally {
            setUploading(false);
            if (fileInputRef.current) fileInputRef.current.value = '';
        }
    };

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setErrors({});

        // Buff inválido seria descartado em silêncio pelo normalizeBuffs.
        if ([data.buffs_strength, data.buffs_intelligence, data.buffs_agility, data.buffs_team].some(hasInvalidBuffs)) {
            setErrors({ buffs: 'Há buffs com número inválido (destacados em vermelho). Use vírgula para decimais, de 0 a 999,99.' });
            return;
        }
        setSaving(true);

        const payload = {
            slug: data.slug,
            name: data.name,
            description: data.description || null,
            buffs_strength: normalizeBuffs(data.buffs_strength),
            buffs_intelligence: normalizeBuffs(data.buffs_intelligence),
            buffs_agility: normalizeBuffs(data.buffs_agility),
            buffs_team: normalizeBuffs(data.buffs_team),
            tier: data.tier || null,
            tier_note: data.tier_note || null,
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

    const buffsForScope = (s: Scope): BuffsMap => {
        switch (s) {
            case 'strength':
                return data.buffs_strength;
            case 'intelligence':
                return data.buffs_intelligence;
            case 'agility':
                return data.buffs_agility;
            case 'team':
                return data.buffs_team;
        }
    };

    const setBuffsForScope = (s: Scope, next: BuffsMap) => {
        setData((prev) => {
            switch (s) {
                case 'strength':
                    return { ...prev, buffs_strength: next };
                case 'intelligence':
                    return { ...prev, buffs_intelligence: next };
                case 'agility':
                    return { ...prev, buffs_agility: next };
                case 'team':
                    return { ...prev, buffs_team: next };
            }
        });
    };

    const countActive = (s: Scope) =>
        Object.keys(buffsForScope(s)).filter((k) => {
            const v = buffsForScope(s)[k];
            const n = typeof v === 'string' ? parseFloat(v) : v;
            return Number.isFinite(n) && (n as number) > 0;
        }).length;

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[92vh] max-w-3xl overflow-y-auto">
                <DialogTitle asChild>
                    <div className="flex flex-col items-center gap-1 text-center">
                        <span className="text-muted-foreground flex items-center gap-2 text-[10px] tracking-wider uppercase">
                            <PawPrint className="size-3" />
                            Besta
                        </span>
                        <h2 className="text-xl leading-tight font-bold">{isEditing ? 'Editar besta' : 'Nova besta'}</h2>
                    </div>
                </DialogTitle>
                <DialogDescription className="text-center">
                    Bestas dão buffs por tipo de herói (Força/Inteligência/Agilidade) e à equipe inteira. Valores em % no nível máximo (36).
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
                            <p className="text-muted-foreground text-[10px]">Apenas minúsculas, números e hífens.</p>
                            {errors.slug && <p className="text-destructive text-xs">{errors.slug}</p>}
                        </div>
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="b-name">Nome *</Label>
                            <Input
                                id="b-name"
                                value={data.name}
                                onChange={(e) => setData((s) => ({ ...s, name: e.target.value }))}
                                placeholder="Dreary Ball"
                            />
                            {errors.name && <p className="text-destructive text-xs">{errors.name}</p>}
                        </div>
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label htmlFor="b-desc">Descrição</Label>
                        <textarea
                            id="b-desc"
                            value={data.description}
                            onChange={(e) => setData((s) => ({ ...s, description: e.target.value }))}
                            className="border-input bg-background flex min-h-[60px] w-full rounded-md border px-3 py-2 text-sm"
                            rows={3}
                            maxLength={5000}
                            placeholder="Skills/efeitos da besta quando ativa..."
                        />
                    </div>

                    <div className="flex flex-col gap-2">
                        <Label>Buffs (em %, no nível 36)</Label>
                        <div className="bg-muted/30 flex flex-wrap gap-1 rounded-md border p-0.5">
                            {(['strength', 'intelligence', 'agility', 'team'] as Scope[]).map((s) => {
                                const active = scope === s;
                                const count = countActive(s);
                                return (
                                    <button
                                        key={s}
                                        type="button"
                                        onClick={() => setScope(s)}
                                        className={cn(
                                            'flex-1 rounded px-2 py-1.5 text-xs font-medium transition-colors',
                                            active ? 'bg-primary text-primary-foreground' : 'text-muted-foreground hover:text-foreground',
                                        )}
                                    >
                                        {SCOPE_LABELS[s]}
                                        {count > 0 && (
                                            <span
                                                className={cn(
                                                    'ml-1.5 rounded-full px-1.5 py-0.5 text-[9px]',
                                                    active ? 'bg-primary-foreground/20' : 'bg-muted-foreground/20',
                                                )}
                                            >
                                                {count}
                                            </span>
                                        )}
                                    </button>
                                );
                            })}
                        </div>
                        <BeastBuffsEditor
                            buffs={buffsForScope(scope)}
                            onChange={(next) => setBuffsForScope(scope, next)}
                            accent={SCOPE_ACCENT[scope]}
                        />
                        {errors.buffs && <p className="text-destructive text-[10px]">{errors.buffs}</p>}
                        <p className="text-muted-foreground text-[10px]">
                            {scope === 'team'
                                ? 'Aplicados a todos os heróis da equipe.'
                                : `Aplicados apenas a heróis de tipo ${SCOPE_LABELS[scope]}.`}
                        </p>
                    </div>

                    <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <div className="flex flex-col gap-1.5">
                            <Label htmlFor="b-tier">Tier (afk.guide)</Label>
                            <select
                                id="b-tier"
                                value={data.tier}
                                onChange={(e) => setData((s) => ({ ...s, tier: e.target.value }))}
                                className="border-input bg-background flex h-9 w-full rounded-md border px-3 py-1 text-sm"
                            >
                                {BEAST_TIERS.map((t) => (
                                    <option key={t || 'none'} value={t}>
                                        {t || '(sem tier)'}
                                    </option>
                                ))}
                            </select>
                            {errors.tier && <p className="text-destructive text-[10px]">{errors.tier}</p>}
                        </div>
                        <div className="flex flex-col gap-1.5 sm:col-span-2">
                            <Label htmlFor="b-tier-note">Observação do tier</Label>
                            <Input
                                id="b-tier-note"
                                value={data.tier_note}
                                onChange={(e) => setData((s) => ({ ...s, tier_note: e.target.value }))}
                                placeholder="Ex: Buffa carry, debuffa time inimigo"
                            />
                            {errors.tier_note && <p className="text-destructive text-[10px]">{errors.tier_note}</p>}
                        </div>
                    </div>

                    <div className="flex flex-col gap-1.5">
                        <Label>Imagem da besta</Label>
                        <div className="flex items-start gap-3">
                            <div className="bg-muted/30 relative grid size-24 shrink-0 place-items-center overflow-hidden rounded-lg border">
                                {data.portrait_url ? (
                                    <>
                                        <img src={data.portrait_url} alt="preview" className="size-full object-contain" />
                                        <button
                                            type="button"
                                            onClick={() => setData((s) => ({ ...s, portrait_url: '' }))}
                                            className="bg-background/80 text-destructive hover:bg-background absolute top-1 right-1 grid size-5 place-items-center rounded-full"
                                            aria-label="Remover imagem"
                                            title="Remover imagem"
                                        >
                                            <X className="size-3" />
                                        </button>
                                    </>
                                ) : (
                                    <PawPrint className="text-muted-foreground size-8" />
                                )}
                            </div>
                            <div className="flex min-w-0 flex-1 flex-col gap-1.5">
                                <div className="flex items-center gap-2">
                                    <input
                                        ref={fileInputRef}
                                        type="file"
                                        accept="image/png,image/jpeg,image/webp,image/gif"
                                        className="hidden"
                                        onChange={(e) => {
                                            const f = e.target.files?.[0];
                                            if (f) handleUpload(f);
                                        }}
                                    />
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        onClick={() => fileInputRef.current?.click()}
                                        disabled={uploading}
                                    >
                                        {uploading ? <Loader2 className="size-4 animate-spin" /> : <ImagePlus className="size-4" />}
                                        {uploading ? 'Enviando...' : 'Upload'}
                                    </Button>
                                    <p className="text-muted-foreground text-[10px]">PNG/JPG/WebP até 2 MB.</p>
                                </div>
                                <Input
                                    id="b-portrait"
                                    type="text"
                                    value={data.portrait_url}
                                    onChange={(e) => setData((s) => ({ ...s, portrait_url: e.target.value }))}
                                    placeholder="https://... ou /storage/beasts/..."
                                />
                                {uploadError && <p className="text-destructive text-[10px]">{uploadError}</p>}
                                {errors.portrait_url && <p className="text-destructive text-[10px]">{errors.portrait_url}</p>}
                            </div>
                        </div>
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
                        {errors.source_url && <p className="text-destructive text-[10px]">{errors.source_url}</p>}
                    </div>

                    <label className="flex items-center gap-2 text-sm">
                        <input
                            type="checkbox"
                            checked={data.is_active}
                            onChange={(e) => setData((s) => ({ ...s, is_active: e.target.checked }))}
                            className="border-input size-4 rounded"
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
