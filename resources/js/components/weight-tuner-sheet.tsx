import { RotateCcw, Sliders } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { cn } from '@/lib/utils';

export interface WeightOverrides {
    faction_bonus_weight: number;
    class_balance_weight: number;
    synergy_weight: number;
    power_weight: number;
    tier_rating_weight: number;
    feature_weight: number;
    furniture_effect_weight: number;
}

interface SliderMeta {
    key: keyof WeightOverrides;
    label: string;
    description: string;
}

const SLIDERS: SliderMeta[] = [
    { key: 'power_weight', label: 'Poder', description: 'Quanto o power dos heróis pesa.' },
    { key: 'synergy_weight', label: 'Sinergias', description: 'Pares conhecidos no jogo.' },
    { key: 'faction_bonus_weight', label: 'Facção', description: 'Bônus 5-stack / 3+2.' },
    { key: 'class_balance_weight', label: 'Classes', description: 'Tank + Support + variedade.' },
    { key: 'tier_rating_weight', label: 'Tier rating', description: 'Rating S+/A/B etc do afk.guide.' },
    { key: 'feature_weight', label: 'Destaques', description: 'PvP Top, Early Carry, etc.' },
    { key: 'furniture_effect_weight', label: 'Móveis F3/F9', description: 'Effects das habilidades de móveis.' },
];

interface Props {
    formationId: number;
    defaults: WeightOverrides;
    onChange: (weights: WeightOverrides, isDefault: boolean) => void;
}

const SLIDER_MIN = 0;
const SLIDER_MAX = 3;
const SLIDER_STEP = 0.1;

function storageKey(formationId: number): string {
    return `afk:weight-tuner:${formationId}`;
}

function approximatelyEqual(a: number, b: number): boolean {
    return Math.abs(a - b) < 0.001;
}

function isDefaultWeights(weights: WeightOverrides, defaults: WeightOverrides): boolean {
    return SLIDERS.every(({ key }) => approximatelyEqual(weights[key], defaults[key]));
}

export function WeightTunerSheet({ formationId, defaults, onChange }: Props) {
    const [weights, setWeights] = useState<WeightOverrides>(() => {
        try {
            const stored = localStorage.getItem(storageKey(formationId));
            if (stored) {
                const parsed = JSON.parse(stored) as Partial<WeightOverrides>;
                return { ...defaults, ...parsed };
            }
        } catch {
            // localStorage indisponível, usa defaults
        }
        return defaults;
    });
    const [open, setOpen] = useState(false);

    const isDefault = isDefaultWeights(weights, defaults);

    useEffect(() => {
        onChange(weights, isDefault);
        try {
            if (isDefault) {
                localStorage.removeItem(storageKey(formationId));
            } else {
                localStorage.setItem(storageKey(formationId), JSON.stringify(weights));
            }
        } catch {
            // ignora falha de localStorage
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [weights, isDefault, formationId]);

    const updateWeight = (key: keyof WeightOverrides, value: number) => {
        setWeights((prev) => ({ ...prev, [key]: value }));
    };

    const resetAll = () => {
        setWeights(defaults);
    };

    return (
        <Sheet open={open} onOpenChange={setOpen}>
            <SheetTrigger asChild>
                <Button variant={isDefault ? 'outline' : 'default'} size="sm" className="gap-1.5">
                    <Sliders className="size-3.5" />
                    Pesos
                    {!isDefault && <span className="ml-1 rounded-full bg-amber-400/20 px-1.5 text-[10px] text-amber-200">custom</span>}
                </Button>
            </SheetTrigger>
            <SheetContent className="w-full overflow-y-auto sm:max-w-md">
                <SheetHeader>
                    <SheetTitle>Ajustar pesos do scoring</SheetTitle>
                    <SheetDescription>
                        Cada componente do score é multiplicado pelo peso correspondente. Defaults vêm do tipo de desafio. Mudanças são salvas localmente
                        e usadas só pra visualizar — a sugestão automática continua usando os defaults.
                    </SheetDescription>
                </SheetHeader>

                <div className="mt-6 flex flex-col gap-5">
                    {SLIDERS.map(({ key, label, description }) => {
                        const value = weights[key];
                        const defaultValue = defaults[key];
                        const isChanged = !approximatelyEqual(value, defaultValue);
                        return (
                            <div key={key} className="flex flex-col gap-1.5">
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium">{label}</span>
                                    <div className="flex items-center gap-2">
                                        {isChanged && (
                                            <button
                                                type="button"
                                                onClick={() => updateWeight(key, defaultValue)}
                                                className="text-[10px] text-muted-foreground hover:text-foreground"
                                                title={`Reset para ${defaultValue.toFixed(1)}`}
                                            >
                                                <RotateCcw className="size-3" />
                                            </button>
                                        )}
                                        <span className={cn('font-mono text-xs tabular-nums', isChanged && 'font-semibold text-amber-400')}>
                                            {value.toFixed(1)}
                                            {isChanged && <span className="ml-1 text-muted-foreground">({defaultValue.toFixed(1)})</span>}
                                        </span>
                                    </div>
                                </div>
                                <input
                                    type="range"
                                    min={SLIDER_MIN}
                                    max={SLIDER_MAX}
                                    step={SLIDER_STEP}
                                    value={value}
                                    onChange={(e) => updateWeight(key, Number(e.target.value))}
                                    className={cn(
                                        'h-1.5 w-full cursor-pointer appearance-none rounded-full bg-muted accent-primary',
                                        isChanged && 'accent-amber-500',
                                    )}
                                />
                                <p className="text-[11px] text-muted-foreground">{description}</p>
                            </div>
                        );
                    })}
                </div>

                <SheetFooter className="mt-6 flex-row justify-between gap-2 sm:justify-between">
                    <Button variant="ghost" size="sm" onClick={resetAll} disabled={isDefault}>
                        <RotateCcw className="size-3.5" />
                        Resetar tudo
                    </Button>
                    <SheetClose asChild>
                        <Button size="sm">Fechar</Button>
                    </SheetClose>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
