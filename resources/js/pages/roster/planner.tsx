import { AiExportDialog, type AiExportPrefill } from '@/components/ai-export-dialog';
import { ResourceEditorDialog, type UserResources } from '@/components/resource-editor-dialog';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { CLASS_LABEL, FACTION_THEME, TIER_THEME } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { ClassCode, FactionCode, TierCode } from '@/types/afk';
import { Head, Link, router } from '@inertiajs/react';
import {
    CheckCircle2,
    ChevronRight,
    Compass,
    Edit3,
    Flame,
    Lightbulb,
    Settings2,
    Sparkles,
    Target,
    TrendingUp,
    Users,
    XCircle,
    Zap,
} from 'lucide-react';
import { useCallback, useState } from 'react';

type UpgradeKind = 'signature_item' | 'furniture' | 'engraving' | 'ascension';

interface Suggestion {
    hero_id: number;
    slug: string;
    name: string;
    portrait_url: string | null;
    faction_code: FactionCode;
    class_code: ClassCode;
    tier_code: TierCode;
    kind: UpgradeKind;
    from: number;
    to: number;
    power_gain: number;
    cost: number;
    score: number;
    reasons: string[];
    current: {
        level: number;
        ascension_value: number;
        signature_item: number;
        furniture: number;
        engraving: number;
    };
    resource_cost: Partial<Record<keyof UserResources, number>> | null;
    affordable: boolean | null;
}

interface FormationSummary {
    id: number;
    name: string;
    teams_count: number;
}

interface PageProps {
    suggestions: Suggestion[];
    formations: FormationSummary[];
    selectedFormationId: number | null;
    rosterSize: number;
    topN: number;
    resources: UserResources;
}

const RESOURCE_LABEL: Record<keyof UserResources, string> = {
    primordial_emblems: 'Primordiais',
    amplifying_emblems: 'Amplificantes',
    faction_emblems: 'Facção',
    mythic_furniture: 'Móveis',
    engraving_scrolls: 'Pergaminhos',
    revigorating_essences: 'Ess. Revig.',
    gold: 'Ouro',
    hero_exp: 'EXP',
    hero_essence: 'Essência',
};

// O Planner é sobre investimento: o Export para IA abre em "Em quem investir a seguir".
const PLANNER_AI_EXPORT: AiExportPrefill = { objective: 'next_investment', planning: 'real', params: {} };

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Roster', href: '/heroes' },
    { title: 'Planner', href: '/roster/planner' },
];

const KIND_LABEL: Record<UpgradeKind, string> = {
    signature_item: 'Assinatura',
    furniture: 'Móveis',
    engraving: 'Inscrições',
    ascension: 'Ascensão',
};

const KIND_ICON: Record<UpgradeKind, React.ComponentType<{ className?: string }>> = {
    signature_item: Sparkles,
    furniture: Target,
    engraving: Flame,
    ascension: TrendingUp,
};

const KIND_BADGE: Record<UpgradeKind, string> = {
    signature_item: 'border-amber-500/50 bg-amber-500/15 text-amber-300',
    furniture: 'border-sky-500/50 bg-sky-500/15 text-sky-300',
    engraving: 'border-rose-500/50 bg-rose-500/15 text-rose-300',
    ascension: 'border-emerald-500/50 bg-emerald-500/15 text-emerald-300',
};

const REASON_LABEL: Record<string, { label: string; className: string }> = {
    faction_gap: { label: 'Facção sub-investida', className: 'border-amber-500/40 text-amber-300' },
    tier_ascended: { label: 'Tier Ascended', className: 'border-violet-500/40 text-violet-300' },
    awakened: { label: 'Desperto', className: 'border-rose-500/40 text-rose-300' },
    in_formation: { label: 'Na formação', className: 'border-sky-500/40 text-sky-300' },
    in_pentagram: { label: 'No pentagrama', className: 'border-amber-400/40 text-amber-200' },
};

function formatStep(kind: UpgradeKind, from: number, to: number): string {
    switch (kind) {
        case 'signature_item':
            return `AS ${from} → ${to}`;
        case 'furniture':
            return `F${from} → F${to}`;
        case 'engraving':
            return `E${from} → E${to}`;
        case 'ascension':
            return `Asc ${from} → ${to}`;
    }
}

function costLabel(cost: number): string {
    if (cost < 500) return 'Baixo';
    if (cost < 1500) return 'Médio';
    if (cost < 3000) return 'Alto';
    return 'Muito alto';
}

function costColor(cost: number): string {
    if (cost < 500) return 'text-emerald-300';
    if (cost < 1500) return 'text-amber-300';
    if (cost < 3000) return 'text-orange-300';
    return 'text-rose-300';
}

export default function RosterPlanner({ suggestions, formations, selectedFormationId, rosterSize, topN, resources }: PageProps) {
    const [formationId, setFormationId] = useState<number | ''>(selectedFormationId ?? '');
    const [currentResources, setCurrentResources] = useState<UserResources>(resources);
    const [resourceDialogOpen, setResourceDialogOpen] = useState(false);
    const [aiExportOpen, setAiExportOpen] = useState(false);
    const [onlyAffordable, setOnlyAffordable] = useState(false);

    const applyFilter = useCallback(
        (nextFormationId: number | '') => {
            setFormationId(nextFormationId);
            router.get('/roster/planner', nextFormationId === '' ? { top: topN } : { top: topN, formation_id: nextFormationId }, {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        },
        [topN],
    );

    const onResourcesSaved = useCallback((updated: UserResources) => {
        setCurrentResources(updated);
        // Recarrega só os props (mantém scroll/state)
        router.reload({ only: ['suggestions', 'resources'] });
    }, []);

    const hasAnyResources = Object.values(currentResources).some((v) => v > 0);
    const visibleSuggestions = onlyAffordable ? suggestions.filter((s) => s.affordable === true) : suggestions;

    if (rosterSize === 0) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Planner de investimento" />
                <div className="flex flex-col items-center justify-center gap-3 rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-12 text-center">
                    <Users className="size-12 text-amber-100/30" />
                    <h2 className="text-xl font-semibold text-amber-100">Roster vazio</h2>
                    <p className="max-w-md text-sm text-amber-100/60">Adicione heróis ao seu roster pra receber sugestões de investimento.</p>
                    <Link
                        href="/heroes"
                        className="mt-2 inline-flex items-center gap-1 rounded-md bg-amber-500/15 px-3 py-1.5 text-xs font-medium text-amber-200 hover:bg-amber-500/25"
                    >
                        Ir para o catálogo
                        <ChevronRight className="size-3" />
                    </Link>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Planner de investimento" />

            <div className="flex flex-col gap-4 p-1">
                <header className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-5">
                    <div className="flex items-start gap-3">
                        <Compass className="mt-0.5 size-5 text-amber-300" />
                        <div className="flex-1">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <h1 className="text-lg font-semibold text-amber-100">Próximos investimentos</h1>
                                <Button variant="outline" size="sm" onClick={() => setAiExportOpen(true)}>
                                    <Sparkles className="mr-2 size-4" />
                                    Exportar para IA
                                </Button>
                            </div>
                            <p className="mt-1 text-xs text-amber-100/60">
                                Sugestões priorizadas por <strong>ROI</strong> (ganho de power ÷ esforço), ajustadas por contexto: facção
                                sub-investida, tier Ascended, herói Desperto, presença na formação selecionada.
                            </p>
                        </div>
                    </div>

                    <div className="mt-4 flex flex-wrap items-center gap-2">
                        <label className="flex items-center gap-2 text-[11px] text-amber-100/70">
                            <span>Escopo:</span>
                            <select
                                value={formationId === '' ? '' : String(formationId)}
                                onChange={(e) => applyFilter(e.target.value === '' ? '' : Number(e.target.value))}
                                className="rounded-md border border-amber-400/20 bg-black/30 px-2 py-1 text-xs text-amber-100 focus:border-amber-400/50 focus:outline-none"
                            >
                                <option value="">Roster inteiro</option>
                                {formations.map((f) => (
                                    <option key={f.id} value={f.id}>
                                        {f.name} ({f.teams_count} equipe{f.teams_count === 1 ? '' : 's'})
                                    </option>
                                ))}
                            </select>
                        </label>
                        {selectedFormationId && (
                            <span className="rounded-full bg-sky-500/15 px-2 py-0.5 text-[10px] text-sky-300">
                                Heróis dessa formação têm prioridade
                            </span>
                        )}
                    </div>
                </header>

                {/* Card de recursos */}
                <section className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                            {(Object.keys(RESOURCE_LABEL) as Array<keyof UserResources>).map((key) => (
                                <span key={key} className="flex items-center gap-1 text-xs">
                                    <span className="text-amber-100/50">{RESOURCE_LABEL[key]}:</span>
                                    <span className="font-mono font-semibold text-amber-100 tabular-nums">
                                        {currentResources[key].toLocaleString('pt-BR')}
                                    </span>
                                </span>
                            ))}
                        </div>
                        <div className="flex items-center gap-2">
                            {hasAnyResources && (
                                <label className="flex cursor-pointer items-center gap-1.5 text-[11px] text-amber-100/70">
                                    <input
                                        type="checkbox"
                                        checked={onlyAffordable}
                                        onChange={(e) => setOnlyAffordable(e.target.checked)}
                                        className="size-3 accent-amber-400"
                                    />
                                    Só sugestões que cabem no orçamento
                                </label>
                            )}
                            <Button variant="outline" size="sm" onClick={() => setResourceDialogOpen(true)} className="gap-1.5">
                                <Settings2 className="size-3.5" />
                                Editar recursos
                            </Button>
                        </div>
                    </div>
                    {!hasAnyResources && (
                        <p className="mt-2 text-[10px] text-amber-100/40">
                            Informe seus recursos pra destacar quais sugestões cabem no seu orçamento atual.
                        </p>
                    )}
                </section>

                {visibleSuggestions.length === 0 ? (
                    <div className="rounded-2xl border border-amber-400/15 bg-zinc-950/60 p-8 text-center">
                        <Lightbulb className="mx-auto mb-3 size-10 text-amber-100/30" />
                        <p className="text-sm font-semibold text-amber-100">
                            {onlyAffordable && suggestions.length > 0 ? 'Nenhuma sugestão cabe no orçamento atual' : 'Nada a sugerir agora'}
                        </p>
                        <p className="mt-1 text-xs text-amber-100/50">
                            {onlyAffordable && suggestions.length > 0
                                ? 'Desmarque o filtro pra ver as próximas opções, ou atualize os recursos.'
                                : 'Todo o roster está no max de SI/F/E/Ascensão (ou no scope selecionado).'}
                        </p>
                    </div>
                ) : (
                    <ol className="flex flex-col gap-2">
                        {visibleSuggestions.map((sug, idx) => {
                            const KindIcon = KIND_ICON[sug.kind];
                            return (
                                <li
                                    key={`${sug.hero_id}-${sug.kind}`}
                                    className="flex flex-col gap-2 rounded-xl border border-amber-400/10 bg-zinc-950/60 p-3 sm:flex-row sm:items-center sm:gap-3"
                                >
                                    <span className="grid size-6 flex-shrink-0 place-items-center rounded-full bg-amber-400/15 font-mono text-[10px] font-bold text-amber-200">
                                        {idx + 1}
                                    </span>

                                    <Link href={`/heroes/${sug.slug}`} className="flex flex-1 items-center gap-2 hover:bg-amber-500/5">
                                        {sug.portrait_url ? (
                                            <img
                                                src={sug.portrait_url}
                                                alt={sug.name}
                                                loading="lazy"
                                                className={cn(
                                                    'size-10 rounded-md object-cover object-top',
                                                    FACTION_THEME[sug.faction_code]?.cardGradient,
                                                )}
                                            />
                                        ) : (
                                            <div className="size-10 rounded-md bg-zinc-800" />
                                        )}
                                        <div className="flex min-w-0 flex-1 flex-col">
                                            <span className="truncate text-sm font-medium text-amber-100">{sug.name}</span>
                                            <span className="truncate text-[10px] text-amber-100/50">
                                                Nv {sug.current.level} · {CLASS_LABEL[sug.class_code]}
                                                {sug.tier_code === 'ascended' && (
                                                    <span className={cn('ml-1 rounded px-1 py-0.5 text-[9px]', TIER_THEME.ascended?.badge)}>
                                                        Ascended
                                                    </span>
                                                )}
                                            </span>
                                        </div>
                                    </Link>

                                    <div className="flex flex-wrap items-center gap-2">
                                        <span
                                            className={cn(
                                                'inline-flex items-center gap-1 rounded-md border px-2 py-1 text-[11px] font-semibold',
                                                KIND_BADGE[sug.kind],
                                            )}
                                        >
                                            <KindIcon className="size-3" />
                                            {KIND_LABEL[sug.kind]}: {formatStep(sug.kind, sug.from, sug.to)}
                                        </span>

                                        <span className="inline-flex items-center gap-1 rounded-md border border-emerald-500/40 bg-emerald-500/10 px-2 py-1 text-[11px] font-semibold text-emerald-300">
                                            <Zap className="size-3" />+{sug.power_gain.toLocaleString('pt-BR')} power
                                        </span>

                                        <span className={cn('text-[10px] font-medium', costColor(sug.cost))}>Custo: {costLabel(sug.cost)}</span>

                                        {hasAnyResources && sug.affordable === true && (
                                            <span className="inline-flex items-center gap-1 rounded-md border border-emerald-500/40 bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-medium text-emerald-300">
                                                <CheckCircle2 className="size-3" />
                                                Cabe agora
                                            </span>
                                        )}
                                        {hasAnyResources && sug.affordable === false && sug.resource_cost && (
                                            <span
                                                className="inline-flex items-center gap-1 rounded-md border border-rose-500/40 bg-rose-500/10 px-1.5 py-0.5 text-[10px] font-medium text-rose-300"
                                                title={Object.entries(sug.resource_cost)
                                                    .map(([k, v]) => {
                                                        const key = k as keyof UserResources;
                                                        const have = currentResources[key];
                                                        const need = v as number;
                                                        return have < need ? `${RESOURCE_LABEL[key]}: ${have}/${need}` : null;
                                                    })
                                                    .filter(Boolean)
                                                    .join(' · ')}
                                            >
                                                <XCircle className="size-3" />
                                                Falta recursos
                                            </span>
                                        )}
                                    </div>

                                    {sug.reasons.length > 0 && (
                                        <div className="flex flex-wrap gap-1">
                                            {sug.reasons.map((r) => {
                                                const meta = REASON_LABEL[r];
                                                if (!meta) return null;
                                                return (
                                                    <span
                                                        key={r}
                                                        className={cn(
                                                            'inline-flex items-center rounded-md border bg-black/20 px-1.5 py-0.5 text-[9px] tracking-wide uppercase',
                                                            meta.className,
                                                        )}
                                                    >
                                                        {meta.label}
                                                    </span>
                                                );
                                            })}
                                        </div>
                                    )}

                                    <Link
                                        href={`/heroes?edit=${sug.slug}`}
                                        className="ml-auto inline-flex items-center gap-1 rounded-md border border-amber-400/30 px-2 py-1 text-[11px] text-amber-200 hover:bg-amber-500/15"
                                        title="Editar herói no roster"
                                    >
                                        <Edit3 className="size-3" />
                                        Editar
                                    </Link>
                                </li>
                            );
                        })}
                    </ol>
                )}

                <p className="text-center text-[10px] text-amber-100/40">
                    Score = (ganho de power ÷ custo aproximado) × multiplicadores contextuais. Custos são unidades abstratas comparáveis entre tipos
                    de upgrade.
                </p>
            </div>

            <ResourceEditorDialog
                open={resourceDialogOpen}
                onOpenChange={setResourceDialogOpen}
                initial={currentResources}
                onSaved={onResourcesSaved}
            />

            <AiExportDialog open={aiExportOpen} onOpenChange={setAiExportOpen} prefill={PLANNER_AI_EXPORT} />
        </AppLayout>
    );
}
