import axios from 'axios';
import { BookmarkPlus, Check, ChevronDown, Loader2, RotateCcw, Save, Sliders, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuSeparator, DropdownMenuTrigger } from '@/components/ui/dropdown-menu';
import { Sheet, SheetClose, SheetContent, SheetDescription, SheetFooter, SheetHeader, SheetTitle, SheetTrigger } from '@/components/ui/sheet';
import { cn } from '@/lib/utils';

export interface WeightPreset {
    id: number;
    name: string;
    challenge_type_id: number | null;
    weights: Partial<WeightOverrides>;
}

export interface WeightOverrides {
    faction_bonus_weight: number;
    class_balance_weight: number;
    synergy_weight: number;
    power_weight: number;
    tier_rating_weight: number;
    feature_weight: number;
    furniture_effect_weight: number;
    engraving_weight: number;
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
    { key: 'engraving_weight', label: 'Inscrições', description: 'Bônus por milestones E30/E60/E80 do roster.' },
];

interface Props {
    formationId: number;
    challengeTypeId: number;
    defaults: WeightOverrides;
    savedOverrides?: Partial<WeightOverrides> | null;
    presets?: WeightPreset[];
    onChange: (weights: WeightOverrides, isDefault: boolean) => void;
    onSave?: (weights: WeightOverrides, isDefault: boolean) => Promise<void> | void;
    onPresetsChange?: (presets: WeightPreset[]) => void;
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

function weightsMatch(a: WeightOverrides, b: WeightOverrides): boolean {
    return SLIDERS.every(({ key }) => approximatelyEqual(a[key], b[key]));
}

function mergeWithDefaults(partial: Partial<WeightOverrides> | null | undefined, defaults: WeightOverrides): WeightOverrides {
    return { ...defaults, ...(partial ?? {}) };
}

export function WeightTunerSheet({
    formationId,
    challengeTypeId,
    defaults,
    savedOverrides,
    presets = [],
    onChange,
    onSave,
    onPresetsChange,
}: Props) {
    // Estado "persistido": prioriza saved no DB; se ausente, defaults.
    const persistedWeights: WeightOverrides = mergeWithDefaults(savedOverrides, defaults);

    const [weights, setWeights] = useState<WeightOverrides>(() => {
        // Inicial: saved no DB > localStorage (compat 26.5) > defaults
        if (savedOverrides && Object.keys(savedOverrides).length > 0) {
            return mergeWithDefaults(savedOverrides, defaults);
        }
        try {
            const stored = localStorage.getItem(storageKey(formationId));
            if (stored) {
                return { ...defaults, ...(JSON.parse(stored) as Partial<WeightOverrides>) };
            }
        } catch {
            // ignora
        }
        return defaults;
    });
    const [open, setOpen] = useState(false);
    const [isSaving, setIsSaving] = useState(false);
    const [presetMenuOpen, setPresetMenuOpen] = useState(false);
    const [savePresetMode, setSavePresetMode] = useState(false);
    const [newPresetName, setNewPresetName] = useState('');
    const [newPresetScope, setNewPresetScope] = useState<'global' | 'challenge'>('global');
    const [presetError, setPresetError] = useState<string | null>(null);
    const [isPresetSaving, setIsPresetSaving] = useState(false);

    const isDefault = weightsMatch(weights, defaults);
    const isSaved = weightsMatch(weights, persistedWeights);
    const hasUnsavedChanges = !isSaved;

    const applyPreset = (preset: WeightPreset) => {
        setWeights(mergeWithDefaults(preset.weights, defaults));
        setPresetMenuOpen(false);
    };

    const handleSavePreset = async () => {
        const trimmed = newPresetName.trim();
        if (!trimmed) {
            setPresetError('Informe um nome.');
            return;
        }
        setIsPresetSaving(true);
        setPresetError(null);
        try {
            const response = await axios.post<{ preset: WeightPreset }>('/scoring-weight-presets', {
                name: trimmed,
                challenge_type_id: newPresetScope === 'challenge' ? challengeTypeId : null,
                weights,
            });
            onPresetsChange?.([...presets, response.data.preset].sort((a, b) => a.name.localeCompare(b.name)));
            setSavePresetMode(false);
            setNewPresetName('');
            setNewPresetScope('global');
        } catch (err: unknown) {
            const error = err as { response?: { data?: { errors?: { name?: string[] }; message?: string } } };
            const message = error.response?.data?.errors?.name?.[0]
                ?? error.response?.data?.message
                ?? 'Falha ao salvar preset.';
            setPresetError(message);
        } finally {
            setIsPresetSaving(false);
        }
    };

    const deletePreset = async (preset: WeightPreset) => {
        if (!confirm(`Excluir preset "${preset.name}"?`)) return;
        try {
            await axios.delete(`/scoring-weight-presets/${preset.id}`);
            onPresetsChange?.(presets.filter((p) => p.id !== preset.id));
        } catch (error) {
            console.error('Falha ao excluir preset:', error);
        }
    };

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

    const save = async () => {
        if (!onSave) return;
        setIsSaving(true);
        try {
            await onSave(weights, isDefault);
        } finally {
            setIsSaving(false);
        }
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
                        Cada componente do score é multiplicado pelo peso correspondente. Defaults vêm do tipo de desafio. Use{' '}
                        <strong>Salvar como padrão</strong> para que a sugestão automática também use estes pesos.
                    </SheetDescription>
                </SheetHeader>

                <div className="mt-4 flex flex-wrap items-center gap-2 rounded-lg border bg-muted/30 p-2">
                    <DropdownMenu open={presetMenuOpen} onOpenChange={setPresetMenuOpen}>
                        <DropdownMenuTrigger asChild>
                            <Button variant="outline" size="sm" className="gap-1.5">
                                <ChevronDown className="size-3.5" />
                                Presets
                                {presets.length > 0 && (
                                    <span className="ml-1 rounded bg-muted px-1.5 text-[10px]">{presets.length}</span>
                                )}
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="start" className="w-64">
                            {presets.length === 0 ? (
                                <DropdownMenuItem disabled className="text-xs">
                                    Nenhum preset salvo ainda.
                                </DropdownMenuItem>
                            ) : (
                                presets.map((preset) => (
                                    <DropdownMenuItem
                                        key={preset.id}
                                        onSelect={(e) => {
                                            e.preventDefault();
                                            applyPreset(preset);
                                        }}
                                        className="flex items-center justify-between gap-2"
                                    >
                                        <div className="flex min-w-0 flex-col">
                                            <span className="truncate text-sm">{preset.name}</span>
                                            <span className="text-[10px] text-muted-foreground">
                                                {preset.challenge_type_id === null ? 'Global' : 'Específico do desafio'}
                                            </span>
                                        </div>
                                        <button
                                            type="button"
                                            onClick={(e) => {
                                                e.stopPropagation();
                                                e.preventDefault();
                                                deletePreset(preset);
                                            }}
                                            className="rounded p-1 text-muted-foreground hover:bg-destructive hover:text-destructive-foreground"
                                            aria-label={`Excluir ${preset.name}`}
                                        >
                                            <Trash2 className="size-3" />
                                        </button>
                                    </DropdownMenuItem>
                                ))
                            )}
                            <DropdownMenuSeparator />
                            <DropdownMenuItem
                                onSelect={(e) => {
                                    e.preventDefault();
                                    setPresetMenuOpen(false);
                                    setSavePresetMode(true);
                                    setNewPresetName('');
                                    setPresetError(null);
                                }}
                                className="gap-2 text-sm"
                            >
                                <BookmarkPlus className="size-3.5" />
                                Salvar pesos atuais como preset…
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                    <span className="text-[11px] text-muted-foreground">
                        Reutiliza pesos custom em outras formações.
                    </span>
                </div>

                {savePresetMode && (
                    <div className="mt-3 flex flex-col gap-2 rounded-lg border border-amber-500/40 bg-amber-500/5 p-3">
                        <label className="text-xs font-medium">Nome do preset</label>
                        <input
                            type="text"
                            value={newPresetName}
                            onChange={(e) => setNewPresetName(e.target.value)}
                            placeholder='ex: "PvP agressivo"'
                            maxLength={60}
                            autoFocus
                            className="h-9 rounded-md border border-input bg-background px-3 text-sm focus:outline-none focus:ring-2 focus:ring-ring"
                        />

                        <div className="flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={() => setNewPresetScope('global')}
                                className={cn(
                                    'rounded-md border px-2 py-1 text-[11px] transition-colors',
                                    newPresetScope === 'global'
                                        ? 'border-primary bg-primary/15 text-primary'
                                        : 'border-border text-muted-foreground hover:text-foreground',
                                )}
                            >
                                Global (todos os desafios)
                            </button>
                            <button
                                type="button"
                                onClick={() => setNewPresetScope('challenge')}
                                className={cn(
                                    'rounded-md border px-2 py-1 text-[11px] transition-colors',
                                    newPresetScope === 'challenge'
                                        ? 'border-primary bg-primary/15 text-primary'
                                        : 'border-border text-muted-foreground hover:text-foreground',
                                )}
                            >
                                Só para este desafio
                            </button>
                        </div>

                        {presetError && <p className="text-[11px] text-destructive">{presetError}</p>}

                        <div className="flex items-center justify-end gap-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => {
                                    setSavePresetMode(false);
                                    setPresetError(null);
                                }}
                                disabled={isPresetSaving}
                            >
                                Cancelar
                            </Button>
                            <Button size="sm" onClick={handleSavePreset} disabled={isPresetSaving} className="gap-1.5">
                                {isPresetSaving ? <Loader2 className="size-3.5 animate-spin" /> : <Save className="size-3.5" />}
                                Salvar
                            </Button>
                        </div>
                    </div>
                )}

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

                <SheetFooter className="mt-6 flex-row flex-wrap justify-between gap-2 sm:justify-between">
                    <Button variant="ghost" size="sm" onClick={resetAll} disabled={isDefault}>
                        <RotateCcw className="size-3.5" />
                        Resetar tudo
                    </Button>
                    <div className="flex items-center gap-2">
                        {onSave && (
                            <Button size="sm" onClick={save} disabled={isSaving || !hasUnsavedChanges} className="gap-1.5">
                                {isSaving ? <Loader2 className="size-3.5 animate-spin" /> : isSaved ? <Check className="size-3.5" /> : <Save className="size-3.5" />}
                                {isSaved ? 'Salvo' : 'Salvar como padrão'}
                            </Button>
                        )}
                        <SheetClose asChild>
                            <Button variant="ghost" size="sm">Fechar</Button>
                        </SheetClose>
                    </div>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
