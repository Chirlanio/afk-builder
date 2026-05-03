import axios from 'axios';
import { BarChart3, Loader2, Save } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Hero, HeroStats } from '@/types/afk';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    hero: Hero | null;
    stats: HeroStats | null;
    onSaved?: () => void | Promise<void>;
}

const FIELDS: { key: keyof Omit<HeroStats, 'id' | 'hero_id'>; label: string; max: number; help?: string }[] = [
    { key: 'hp', label: 'HP', max: 999_999, help: 'Pontos de vida base (Nv 1).' },
    { key: 'attack', label: 'Ataque', max: 999_999, help: 'Atributo de ataque base.' },
    { key: 'defense', label: 'Defesa', max: 999_999, help: 'Atributo de defesa base.' },
    { key: 'crit_rate', label: 'Taxa Crítica', max: 9_999, help: 'Em pontos.' },
    { key: 'crit_amp', label: 'Amp. Crítica', max: 9_999, help: 'Amplificação crítica.' },
    { key: 'accuracy', label: 'Precisão', max: 9_999, help: 'Hit Rating.' },
    { key: 'dodge', label: 'Esquiva', max: 9_999, help: 'Dodge Rating.' },
    { key: 'haste', label: 'Pressa', max: 9_999, help: 'Energia.' },
];

export function StatsEditModal({ open, onOpenChange, hero, stats, onSaved }: Props) {
    const init = (v: number | null | undefined) => (v === null || v === undefined ? '' : String(v));

    const [values, setValues] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (!open) return;
        setValues({
            hp: init(stats?.hp),
            attack: init(stats?.attack),
            defense: init(stats?.defense),
            crit_rate: init(stats?.crit_rate),
            crit_amp: init(stats?.crit_amp),
            accuracy: init(stats?.accuracy),
            dodge: init(stats?.dodge),
            haste: init(stats?.haste),
        });
        setErrors({});
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, hero?.id, stats?.id]);

    if (!hero) return null;

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        const payload: Record<string, number | null> = {};
        Object.entries(values).forEach(([k, v]) => {
            payload[k] = v === '' ? null : Number(v);
        });

        try {
            await axios.patch(`/admin/heroes/${hero.slug}/stats`, payload);
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

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-2xl">
                <DialogTitle className="flex items-center gap-2">
                    <BarChart3 className="size-5 text-primary" />
                    Estatísticas Base — <span className="font-bold">{hero.name}</span>
                </DialogTitle>
                <DialogDescription>
                    Cadastre os atributos <strong>base</strong> do herói (Nv 1, sem progressão). Campos vazios não são cadastrados; se todos forem deixados em branco, o registro é apagado.
                </DialogDescription>

                <form onSubmit={submit} className="flex flex-col gap-3 pt-2">
                    <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                        {FIELDS.map(({ key, label, max, help }) => (
                            <div key={key} className="flex flex-col gap-1">
                                <Label htmlFor={`stat-${key}`} className="text-xs">{label}</Label>
                                <Input
                                    id={`stat-${key}`}
                                    type="number"
                                    min={0}
                                    max={max}
                                    value={values[key] ?? ''}
                                    onChange={(e) => setValues((s) => ({ ...s, [key]: e.target.value }))}
                                    placeholder="—"
                                    className="h-8"
                                />
                                {help && <p className="text-[10px] text-muted-foreground">{help}</p>}
                                {errors[key] && <p className="text-[10px] text-destructive">{errors[key]}</p>}
                            </div>
                        ))}
                    </div>

                    <div className="flex justify-end gap-2 border-t pt-3">
                        <Button type="button" variant="outline" onClick={() => onOpenChange(false)} disabled={saving}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={saving}>
                            {saving ? <Loader2 className="size-4 animate-spin" /> : <Save className="size-4" />}
                            {saving ? 'Salvando...' : 'Salvar'}
                        </Button>
                    </div>
                </form>
            </DialogContent>
        </Dialog>
    );
}
