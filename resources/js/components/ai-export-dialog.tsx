import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Dialog, DialogContent, DialogDescription, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import axios from 'axios';
import { AlertTriangle, Check, Copy, Download, Loader2, Sparkles } from 'lucide-react';
import { useEffect, useState } from 'react';

// Campo de Objetivo: lista de opções, ou texto livre validado por `pattern`.
type ObjectiveParam =
    | { name: string; label: string; options: Array<{ value: string; label: string }>; default?: string }
    | { name: string; label: string; pattern: RegExp; placeholder: string };

interface Objective {
    code: string;
    label: string;
    // false no Objetivo sem Formação (Em quem investir a seguir).
    formation: boolean;
    params: ObjectiveParam[];
}

const range = (from: number, to: number) => Array.from({ length: to - from + 1 }, (_, i) => String(from + i));

// Espelha o catálogo de Objetivos do backend (AiExportObjectives).
const OBJECTIVES: Objective[] = [
    {
        code: 'campaign',
        label: 'Campanha',
        formation: true,
        params: [
            { name: 'stage', label: 'Fase', pattern: /^\d{1,3}-\d{1,2}$/, placeholder: 'ex.: 45-60' },
            {
                name: 'teams',
                label: 'Equipes (fase de várias Equipes)',
                options: range(1, 5).map((n) => ({ value: n, label: n })),
                default: '1',
            },
        ],
    },
    {
        code: 'dragon_trial',
        label: 'Provas da Forja Dracônica',
        formation: true,
        params: [
            {
                name: 'stage',
                label: 'Fase',
                options: range(3, 12).map((n) => ({ value: n, label: `${n} (${Number(n) <= 6 ? 2 : 3} equipes)` })),
            },
        ],
    },
    {
        code: 'kings_tower',
        label: 'Torre do Rei / torres de facção',
        formation: true,
        params: [
            {
                name: 'tower',
                label: 'Torre',
                options: [
                    { value: 'kings_tower', label: 'Torre do Rei' },
                    { value: 'tower_of_light', label: 'Tower of Light (Lightbearers)' },
                    { value: 'brutal_citadel', label: 'Brutal Citadel (Maulers)' },
                    { value: 'world_tree', label: 'World Tree (Wilders)' },
                    { value: 'forsaken_necropolis', label: 'Forsaken Necropolis (Graveborns)' },
                    { value: 'celestial_sanctum', label: 'Celestial Sanctum (Celestials)' },
                    { value: 'infernal_fortress', label: 'Infernal Fortress (Hypogeans)' },
                ],
            },
        ],
    },
    {
        code: 'dream_realm',
        label: 'Chefe do Reino dos Sonhos',
        formation: true,
        params: [],
    },
    {
        code: 'next_investment',
        label: 'Em quem investir a seguir',
        formation: false,
        params: [],
    },
    {
        code: 'arena_of_heroes',
        label: 'Arena dos Heróis',
        formation: true,
        params: [
            {
                name: 'side',
                label: 'Lado',
                options: [
                    { value: 'defense', label: 'Defesa' },
                    { value: 'attack', label: 'Ataque' },
                ],
            },
        ],
    },
];

type Planning = 'real' | 'target';

function defaultParams(objective: Objective): Record<string, string> {
    return Object.fromEntries(objective.params.flatMap((p) => ('default' in p && p.default ? [[p.name, p.default]] : [])));
}

interface ExportResult {
    markdown: string;
    warning: string | null;
}

/** O que a tela que abre o modal já sabe (ex.: a Formação). Objetivo null = em branco. */
export interface AiExportPrefill {
    objective: string | null;
    planning: Planning;
    params: Record<string, string>;
}

interface Props {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    prefill?: AiExportPrefill;
}

export function AiExportDialog({ open, onOpenChange, prefill }: Props) {
    const [objectiveCode, setObjectiveCode] = useState<string | null>(OBJECTIVES[0].code);
    const [params, setParams] = useState<Record<string, string>>(() => defaultParams(OBJECTIVES[0]));
    const [planning, setPlanning] = useState<Planning>('real');
    // Desmarcado por padrão: a sugestão do app não deve puxar a resposta do modelo.
    const [includeSuggestion, setIncludeSuggestion] = useState(false);
    const [result, setResult] = useState<ExportResult | null>(null);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [copied, setCopied] = useState(false);

    const objective = OBJECTIVES.find((o) => o.code === objectiveCode) ?? null;
    const paramsComplete =
        objective !== null &&
        objective.params.every((p) => {
            const value = params[p.name];
            return Boolean(value) && ('pattern' in p ? p.pattern.test(value) : true);
        });

    // A cada abertura, o que a tela de origem já define vale por cima dos padrões.
    useEffect(() => {
        if (!open || !prefill) return;
        const prefilled = OBJECTIVES.find((o) => o.code === prefill.objective) ?? null;
        setObjectiveCode(prefilled?.code ?? null);
        setParams({ ...(prefilled ? defaultParams(prefilled) : {}), ...prefill.params });
        setPlanning(prefill.planning);
    }, [open, prefill]);

    useEffect(() => {
        if (!open || !paramsComplete) {
            setResult(null);
            return;
        }

        let cancelled = false;
        setLoading(true);
        setError(null);
        axios
            .get<ExportResult>(route('roster.ai-export'), {
                params: { objective: objectiveCode, planning, include_suggestion: objective?.formation && includeSuggestion ? 1 : 0, ...params },
            })
            .then((res) => {
                if (!cancelled) setResult(res.data);
            })
            .catch(() => {
                if (!cancelled) setError('Não foi possível gerar o texto. Tente de novo.');
            })
            .finally(() => {
                if (!cancelled) setLoading(false);
            });

        return () => {
            cancelled = true;
        };
    }, [open, objectiveCode, objective?.formation, planning, includeSuggestion, params, paramsComplete]);

    const changeObjective = (code: string) => {
        setObjectiveCode(code);
        const chosen = OBJECTIVES.find((o) => o.code === code);
        setParams(chosen ? defaultParams(chosen) : {});
    };

    // Sem heróis o backend devolve texto vazio (só o aviso): nada a exportar.
    const markdown = result?.markdown ?? '';

    const copy = async () => {
        if (!markdown) return;
        try {
            await navigator.clipboard.writeText(markdown);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
        } catch (err) {
            console.error('Falha ao copiar:', err);
        }
    };

    const download = () => {
        if (!markdown) return;
        const blob = new Blob([markdown], { type: 'text/markdown;charset=utf-8' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        link.download = `afk-builder-${objectiveCode}.md`;
        link.click();
        URL.revokeObjectURL(url);
    };

    // ~4 caracteres por token é a estimativa usual para texto misto.
    const approxTokens = Math.ceil(markdown.length / 4);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle className="flex items-center gap-2">
                        <Sparkles className="size-4" />
                        Exportar para IA
                    </DialogTitle>
                    <DialogDescription>
                        Gera um prompt com os seus heróis (Planejamento real) ou com os melhores heróis do jogo no investimento máximo (Planejamento
                        alvo), para colar no modelo de IA que você usa (ChatGPT, Claude, Gemini...).
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-3 sm:grid-cols-2">
                    <div className="space-y-1.5">
                        <Label htmlFor="ai_export_planning">Planejamento</Label>
                        <Select value={planning} onValueChange={(value) => setPlanning(value as Planning)}>
                            <SelectTrigger id="ai_export_planning">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="real">Real (meu Roster)</SelectItem>
                                <SelectItem value="target">Alvo (investimento máximo)</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="ai_export_objective">Objetivo</Label>
                        <Select value={objectiveCode ?? ''} onValueChange={changeObjective}>
                            <SelectTrigger id="ai_export_objective">
                                <SelectValue placeholder="Escolha o Objetivo..." />
                            </SelectTrigger>
                            <SelectContent>
                                {OBJECTIVES.map((o) => (
                                    <SelectItem key={o.code} value={o.code}>
                                        {o.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    {objective?.params.map((param) => (
                        <div key={param.name} className="space-y-1.5">
                            <Label htmlFor={`ai_export_${param.name}`}>{param.label}</Label>
                            {'pattern' in param ? (
                                <Input
                                    id={`ai_export_${param.name}`}
                                    placeholder={param.placeholder}
                                    value={params[param.name] ?? ''}
                                    onChange={(e) => setParams((p) => ({ ...p, [param.name]: e.target.value.trim() }))}
                                />
                            ) : (
                                <Select value={params[param.name] ?? ''} onValueChange={(value) => setParams((p) => ({ ...p, [param.name]: value }))}>
                                    <SelectTrigger id={`ai_export_${param.name}`}>
                                        <SelectValue placeholder="Escolha..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {param.options.map((opt) => (
                                            <SelectItem key={opt.value} value={opt.value}>
                                                {opt.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </div>
                    ))}
                </div>

                {objective?.formation && (
                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="ai_export_include_suggestion"
                            checked={includeSuggestion}
                            onCheckedChange={(checked) => setIncludeSuggestion(checked === true)}
                        />
                        <Label htmlFor="ai_export_include_suggestion" className="cursor-pointer text-sm font-normal">
                            Incluir a sugestão do AFK Builder para revisão
                        </Label>
                    </div>
                )}

                {result?.warning && (
                    <div className="flex items-start gap-2 rounded-md border border-amber-500/40 bg-amber-500/10 p-2 text-xs text-amber-700 dark:text-amber-300">
                        <AlertTriangle className="size-4 shrink-0" />
                        <div>{result.warning}</div>
                    </div>
                )}
                {error && <p className="text-destructive text-sm">{error}</p>}

                <div className="relative">
                    {loading && (
                        <div className="bg-background/60 absolute inset-0 flex items-center justify-center rounded-md">
                            <Loader2 className="size-5 animate-spin" />
                        </div>
                    )}
                    <pre className="bg-muted/50 max-h-80 min-h-32 overflow-auto rounded-md border p-3 text-xs whitespace-pre-wrap">
                        {markdown || (paramsComplete ? '' : 'Preencha os campos acima para gerar a prévia.')}
                    </pre>
                </div>

                <div className="flex flex-wrap items-center justify-between gap-2">
                    <span className="text-muted-foreground text-xs">
                        {markdown ? `${markdown.length.toLocaleString('pt-BR')} caracteres · ~${approxTokens.toLocaleString('pt-BR')} tokens` : ''}
                    </span>
                    <div className="flex gap-2">
                        <Button variant="outline" size="sm" onClick={download} disabled={!markdown}>
                            <Download className="mr-2 size-4" />
                            Baixar .md
                        </Button>
                        <Button size="sm" onClick={copy} disabled={!markdown}>
                            {copied ? <Check className="mr-2 size-4" /> : <Copy className="mr-2 size-4" />}
                            {copied ? 'Copiado' : 'Copiar'}
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    );
}
