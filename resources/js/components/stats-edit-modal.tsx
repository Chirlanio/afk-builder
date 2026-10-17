import axios from 'axios';
import { BarChart3, Loader2, Save } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';

import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { formatDecimalInput, parseDecimal } from '@/lib/decimal';
import type { Hero, HeroStats } from '@/types/afk';

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    hero: Hero | null;
    stats: HeroStats | null;
    onSaved?: () => void | Promise<void>;
    /**
     * Endpoint para PATCH dos stats. Default: `/admin/heroes/${hero.slug}/stats`
     * (cadastro do admin para os stats "base"). Pode ser sobrescrito para
     * `/user/heroes/${userHeroId}/stats` ao editar stats do roster do user.
     */
    endpoint?: string;
    /** Subtítulo opcional acima do nome (ex: "Stats do meu roster"). */
    contextLabel?: string;
}

interface StatField {
    key: keyof Omit<HeroStats, 'id' | 'hero_id'>;
    label: string;
    help?: string;
}

/**
 * Espelha o backend (UserHeroController::updateStats / HeroStatsAdminController):
 * HP, ATK e DEF são inteiros; todos os demais aceitam até 2 casas decimais.
 */
const INTEGER_MAX: Partial<Record<StatField['key'], number>> = {
    hp: 9_999_999_999,
    attack: 999_999_999,
    defense: 999_999_999,
};
const DECIMAL_MAX = 99_999_999.99;

interface StatSection {
    title: string;
    fields: StatField[];
}

const SECTIONS: StatSection[] = [
    {
        title: 'Básicos',
        fields: [
            { key: 'hp', label: 'HP', help: 'Pontos de vida.' },
            { key: 'attack', label: 'Ataque' },
            { key: 'defense', label: 'Defesa' },
            { key: 'vitality', label: 'Vitalidade' },
            { key: 'armor', label: 'Armadura' },
            { key: 'attack_speed', label: 'Velocidade de Ataque' },
            { key: 'healing', label: 'Cura' },
            { key: 'recovery_per_second', label: 'Recuperação/s' },
            { key: 'energy_recovery_rate', label: 'Tx. Recuperação Energia' },
        ],
    },
    {
        title: 'Crítico',
        fields: [
            { key: 'crit_rate', label: 'Taxa Crítica' },
            { key: 'crit_amp', label: 'Amp. Crítica' },
            { key: 'crit_block_rate', label: 'Tx. Bloqueio Crítico' },
            { key: 'crit_damage_resistance', label: 'Resist. Dano Crítico' },
        ],
    },
    {
        title: 'Combate (Físico/Mágico)',
        fields: [
            { key: 'physical_piercing', label: 'Perfuração Física' },
            { key: 'magic_piercing', label: 'Perfuração Mágica' },
            { key: 'physical_resistance', label: 'Resist. Física' },
            { key: 'magic_resistance', label: 'Resist. Mágica' },
            { key: 'magic_efficiency', label: 'Eficiência Mágica' },
            { key: 'magic_suppression', label: 'Supressão Mágica' },
        ],
    },
    {
        title: 'Status',
        fields: [
            { key: 'accuracy', label: 'Precisão' },
            { key: 'dodge', label: 'Esquiva' },
            { key: 'haste', label: 'Pressa' },
            { key: 'life_steal', label: 'Sanguessuga' },
            { key: 'tenacity', label: 'Tenacidade' },
            { key: 'damage_resistance', label: 'Resist. a Dano' },
            { key: 'perception', label: 'Percepção' },
        ],
    },
    {
        title: 'Especiais',
        fields: [
            { key: 'proficiency', label: 'Competência' },
            { key: 'dispel', label: 'Dissolver' },
            { key: 'erosion', label: 'Erosão' },
            { key: 'aid', label: 'Amparo' },
        ],
    },
    {
        title: 'Dracônico',
        fields: [
            { key: 'draconic_amplification', label: 'Amp. Verbo Dracônico' },
            { key: 'draconic_resistance', label: 'Resist. Verbo Dracônico' },
        ],
    },
    {
        title: 'Modos Específicos',
        fields: [
            { key: 'arena_damage_boost', label: 'Reforço Dano Arena' },
            { key: 'arena_damage_reduction', label: 'Redução Dano Arena' },
            { key: 'trial_damage_boost', label: 'Reforço Dano Provação' },
        ],
    },
];

const ALL_FIELD_KEYS = SECTIONS.flatMap((s) => s.fields.map((f) => f.key));

export function StatsEditModal({ open, onOpenChange, hero, stats, onSaved, endpoint, contextLabel }: Props) {
    const init = (v: number | null | undefined) => formatDecimalInput(v);

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
        const localErrors: Record<string, string> = {};
        for (const key of ALL_FIELD_KEYS) {
            const parsed = parseDecimal(values[key] ?? '');
            const integerMax = INTEGER_MAX[key as StatField['key']];
            if (parsed !== null && Number.isNaN(parsed)) {
                localErrors[key] = 'Número inválido. Use vírgula para decimais (ex: 1234,5).';
            } else if (parsed !== null && parsed < 0) {
                localErrors[key] = 'Não pode ser negativo.';
            } else if (parsed !== null && integerMax !== undefined && !Number.isInteger(parsed)) {
                localErrors[key] = 'Este atributo é inteiro (sem casas decimais).';
            } else if (parsed !== null && parsed > (integerMax ?? DECIMAL_MAX)) {
                localErrors[key] = 'Valor acima do máximo.';
            }
            payload[key] = parsed;
        }
        if (Object.keys(localErrors).length > 0) {
            setErrors(localErrors);
            setSaving(false);
            return;
        }

        const url = endpoint ?? `/admin/heroes/${hero.slug}/stats`;
        try {
            await axios.patch(url, payload);
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
                        <span className="text-muted-foreground flex items-center gap-2 text-[10px] tracking-wider uppercase">
                            <BarChart3 className="size-3" />
                            {contextLabel ?? 'Estatísticas base'}
                        </span>
                        <h2 className="text-xl leading-tight font-bold">{hero.name}</h2>
                    </div>
                </DialogTitle>
                <DialogDescription className="text-center">
                    Cadastre os atributos do herói. Campos vazios não são cadastrados; se TODOS forem deixados em branco, o registro é apagado.
                    <br />
                    Decimais com vírgula (ex: 1234,5). Ponto também funciona, inclusive como separador de milhar (1.234,5). HP, Ataque e Defesa são
                    inteiros.
                </DialogDescription>

                <form onSubmit={submit} className="flex flex-col gap-4 pt-2">
                    {SECTIONS.map((section) => (
                        <fieldset key={section.title} className="flex flex-col gap-2 rounded-lg border p-3">
                            <legend className="text-muted-foreground px-1 text-[10px] font-semibold tracking-wide uppercase">{section.title}</legend>
                            <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                                {section.fields.map(({ key, label, help }) => (
                                    <div key={key} className="flex flex-col gap-1">
                                        <Label htmlFor={`stat-${key}`} className="truncate text-xs" title={label}>
                                            {label}
                                        </Label>
                                        <Input
                                            id={`stat-${key}`}
                                            type="text"
                                            inputMode={INTEGER_MAX[key] !== undefined ? 'numeric' : 'decimal'}
                                            value={values[key] ?? ''}
                                            onChange={(e) => setValues((s) => ({ ...s, [key]: e.target.value }))}
                                            placeholder="—"
                                            className="h-8"
                                        />
                                        {help && <p className="text-muted-foreground text-[10px]">{help}</p>}
                                        {errors[key] && <p className="text-destructive text-[10px]">{errors[key]}</p>}
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
