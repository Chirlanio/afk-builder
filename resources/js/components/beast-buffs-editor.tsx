import { Input } from '@/components/ui/input';
import { formatDecimalInput, parseDecimal } from '@/lib/decimal';
import { cn } from '@/lib/utils';

export type BuffsMap = Record<string, number | string>;

/**
 * Stats canônicos com labels PT-BR. O JSON aceita qualquer chave, mas o form
 * expõe apenas estes — admin que precisar de stat extra pode adicionar via API.
 */
export const STAT_FIELDS: Array<{ key: string; label: string; hint?: string }> = [
    { key: 'hp', label: 'PV' },
    { key: 'attack', label: 'ATQ' },
    { key: 'defense', label: 'DEF' },
    { key: 'crit_rate', label: 'Crítico' },
    { key: 'crit_amp', label: 'Ampl. Crítico' },
    { key: 'dodge', label: 'Esquiva' },
    { key: 'vitality', label: 'Vitalidade' },
    { key: 'perception', label: 'Percepção' },
    { key: 'proficiency', label: 'Competência' },
    { key: 'magic_resistance', label: 'Resist. Mágica' },
    { key: 'crit_block_rate', label: 'Bloq. Crítico' },
];

interface Props {
    buffs: BuffsMap;
    onChange: (next: BuffsMap) => void;
    accent?: 'rose' | 'sky' | 'emerald' | 'amber';
}

const ACCENT_CLASSES = {
    rose: 'border-rose-500/30 bg-rose-500/5',
    sky: 'border-sky-500/30 bg-sky-500/5',
    emerald: 'border-emerald-500/30 bg-emerald-500/5',
    amber: 'border-amber-500/30 bg-amber-500/5',
} as const;

export function BeastBuffsEditor({ buffs, onChange, accent = 'amber' }: Props) {
    const set = (key: string, raw: string) => {
        const next = { ...buffs };
        // Guarda o texto como digitado (aceita vírgula — "12,5"); a conversão é
        // feita em normalizeBuffs. Campo vazio remove o buff.
        if (raw.trim() === '') {
            delete next[key];
        } else {
            next[key] = raw;
        }
        onChange(next);
    };

    return (
        <div className={cn('grid grid-cols-2 gap-2 rounded-md border p-3 sm:grid-cols-3', ACCENT_CLASSES[accent])}>
            {STAT_FIELDS.map((field) => {
                const raw = buffs[field.key];
                const display = raw === undefined || raw === null ? '' : typeof raw === 'number' ? formatDecimalInput(raw) : raw;
                const parsed = parseDecimal(display);
                const invalid = parsed !== null && (Number.isNaN(parsed) || parsed < 0 || parsed > 999.99);
                return (
                    <div key={field.key} className="flex flex-col gap-1">
                        <label className="text-muted-foreground text-[10px] font-medium" htmlFor={`buff-${field.key}`}>
                            {field.label}
                        </label>
                        <div className="flex items-center gap-1">
                            <Input
                                id={`buff-${field.key}`}
                                type="text"
                                inputMode="decimal"
                                value={display}
                                onChange={(e) => set(field.key, e.target.value)}
                                placeholder="0"
                                aria-invalid={invalid}
                                title={invalid ? 'Número inválido (0 a 999,99). Use vírgula para decimais.' : undefined}
                                className={cn('h-7 text-xs', invalid && 'border-destructive')}
                            />
                            <span className="text-muted-foreground text-[10px]">%</span>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

/**
 * Normaliza um BuffsMap pra envio: strings vazias viram 0, descarta zeros,
 * retorna um objeto com numbers. Backend recebe {} se nada preenchido.
 */
export function normalizeBuffs(buffs: BuffsMap): Record<string, number> {
    const out: Record<string, number> = {};
    for (const [key, value] of Object.entries(buffs)) {
        const num = parseDecimal(value);
        if (num !== null && Number.isFinite(num) && num > 0) {
            out[key] = Number(num.toFixed(2));
        }
    }
    return out;
}

/** Algum buff digitado não é um número válido (0 a 999,99)? */
export function hasInvalidBuffs(buffs: BuffsMap): boolean {
    return Object.values(buffs).some((value) => {
        const num = parseDecimal(value);
        return num !== null && (Number.isNaN(num) || num < 0 || num > 999.99);
    });
}
