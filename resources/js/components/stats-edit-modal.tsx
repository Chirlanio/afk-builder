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

interface StatField {
    key: keyof Omit<HeroStats, 'id' | 'hero_id'>;
    label: string;
    max: number;
    step: number;
    help?: string;
}

interface StatSection {
    title: string;
    fields: StatField[];
}

// Step 1 = inteiro; step 0.01 = aceita decimais (campos exibidos com % ou casas no jogo)
const SECTIONS: StatSection[] = [
    {
        title: 'Básicos',
        fields: [
            { key: 'hp', label: 'HP', max: 9_999_999_999, step: 1, help: 'Pontos de vida.' },
            { key: 'attack', label: 'Ataque', max: 999_999_999, step: 1 },
            { key: 'defense', label: 'Defesa', max: 999_999_999, step: 1 },
            { key: 'vitality', label: 'Vitalidade', max: 99_999_999.99, step: 1 },
            { key: 'armor', label: 'Armadura', max: 99_999_999.99, step: 1 },
            { key: 'attack_speed', label: 'Velocidade de Ataque', max: 9_999, step: 0.01 },
            { key: 'healing', label: 'Cura', max: 99_999_999.99, step: 1 },
            { key: 'recovery_per_second', label: 'Recuperação/s', max: 99_999_999.99, step: 1 },
            { key: 'energy_recovery_rate', label: 'Tx. Recuperação Energia', max: 9_999, step: 0.01 },
        ],
    },
    {
        title: 'Crítico',
        fields: [
            { key: 'crit_rate', label: 'Taxa Crítica', max: 9_999, step: 0.01 },
            { key: 'crit_amp', label: 'Amp. Crítica', max: 9_999, step: 0.01 },
            { key: 'crit_block_rate', label: 'Tx. Bloqueio Crítico', max: 9_999, step: 0.01, help: 'Decimal' },
            { key: 'crit_damage_resistance', label: 'Resist. Dano Crítico', max: 9_999, step: 0.01, help: 'Decimal' },
        ],
    },
    {
        title: 'Combate (Físico/Mágico)',
        fields: [
            { key: 'physical_piercing', label: 'Perfuração Física', max: 9_999, step: 0.01, help: 'Decimal' },
            { key: 'magic_piercing', label: 'Perfuração Mágica', max: 9_999, step: 0.01, help: 'Decimal' },
            { key: 'physical_resistance', label: 'Resist. Física', max: 9_999, step: 0.01, help: 'Decimal' },
            { key: 'magic_resistance', label: 'Resist. Mágica', max: 9_999, step: 0.01, help: 'Decimal' },
            { key: 'magic_efficiency', label: 'Eficiência Mágica', max: 9_999, step: 0.01 },
            { key: 'magic_suppression', label: 'Supressão Mágica', max: 9_999, step: 0.01 },
        ],
    },
    {
        title: 'Status',
        fields: [
            { key: 'accuracy', label: 'Precisão', max: 9_999, step: 0.01 },
            { key: 'dodge', label: 'Esquiva', max: 9_999, step: 0.01 },
            { key: 'haste', label: 'Pressa', max: 9_999, step: 0.01 },
            { key: 'life_steal', label: 'Sanguessuga', max: 9_999, step: 0.01 },
            { key: 'tenacity', label: 'Tenacidade', max: 9_999, step: 0.01 },
            { key: 'damage_resistance', label: 'Resist. a Dano', max: 9_999, step: 0.01 },
            { key: 'perception', label: 'Percepção', max: 9_999, step: 0.01 },
        ],
    },
    {
        title: 'Especiais',
        fields: [
            { key: 'proficiency', label: 'Competência', max: 9_999, step: 0.01 },
            { key: 'dispel', label: 'Dissolver', max: 9_999, step: 0.01 },
            { key: 'erosion', label: 'Erosão', max: 9_999, step: 0.01 },
            { key: 'aid', label: 'Amparo', max: 9_999, step: 0.01 },
        ],
    },
    {
        title: 'Dracônico',
        fields: [
            { key: 'draconic_amplification', label: 'Amp. Verbo Dracônico', max: 9_999, step: 0.01 },
            { key: 'draconic_resistance', label: 'Resist. Verbo Dracônico', max: 9_999, step: 0.01 },
        ],
    },
    {
        title: 'Modos Específicos',
        fields: [
            { key: 'arena_damage_boost', label: 'Reforço Dano Arena', max: 9_999, step: 0.01, help: 'Decimal' },
            { key: 'arena_damage_reduction', label: 'Redução Dano Arena', max: 9_999, step: 0.01, help: 'Decimal' },
            { key: 'trial_damage_boost', label: 'Reforço Dano Provação', max: 9_999, step: 0.01, help: 'Decimal' },
        ],
    },
];

const ALL_FIELD_KEYS = SECTIONS.flatMap((s) => s.fields.map((f) => f.key));

export function StatsEditModal({ open, onOpenChange, hero, stats, onSaved }: Props) {
    const init = (v: number | null | undefined) => (v === null || v === undefined ? '' : String(v));

    const [values, setValues] = useState<Record<string, string>>({});
    const [saving, setSaving] = useState(false);
    const [errors, setErrors] = useState<Record<string, string>>({});

    useEffect(() => {
        if (!open) return;
        const initial: Record<string, string> = {};
        for (const key of ALL_FIELD_KEYS) {
            initial[key] = init(stats?.[key as keyof HeroStats] as number | null | undefined);
        }
        setValues(initial);
        setErrors({});
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, hero?.id, stats?.id]);

    if (!hero) return null;

    const submit = async (e: FormEvent) => {
        e.preventDefault();
        setSaving(true);
        setErrors({});
        const payload: Record<string, number | null> = {};
        for (const key of ALL_FIELD_KEYS) {
            const v = values[key] ?? '';
            payload[key] = v === '' ? null : Number(v);
        }

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
            <DialogContent className="max-h-[92vh] max-w-3xl overflow-y-auto">
                <DialogTitle asChild>
                    <div className="flex flex-col items-center gap-1 text-center">
                        <span className="flex items-center gap-2 text-[10px] uppercase tracking-wider text-muted-foreground">
                            <BarChart3 className="size-3" />
                            Estatísticas base
                        </span>
                        <h2 className="text-xl font-bold leading-tight">{hero.name}</h2>
                    </div>
                </DialogTitle>
                <DialogDescription className="text-center">
                    Cadastre os atributos do herói. Campos vazios não são cadastrados; se TODOS forem deixados em branco, o registro é apagado.
                </DialogDescription>

                <form onSubmit={submit} className="flex flex-col gap-4 pt-2">
                    {SECTIONS.map((section) => (
                        <fieldset key={section.title} className="flex flex-col gap-2 rounded-lg border p-3">
                            <legend className="px-1 text-[10px] font-semibold uppercase tracking-wide text-muted-foreground">
                                {section.title}
                            </legend>
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                {section.fields.map(({ key, label, max, step, help }) => (
                                    <div key={key} className="flex flex-col gap-1">
                                        <Label htmlFor={`stat-${key}`} className="truncate text-xs" title={label}>
                                            {label}
                                        </Label>
                                        <Input
                                            id={`stat-${key}`}
                                            type="number"
                                            min={0}
                                            max={max}
                                            step={step}
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
                        </fieldset>
                    ))}

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
