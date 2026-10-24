import { AiExportDialog, type AiExportPrefill } from '@/components/ai-export-dialog';
import { PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Label } from '@/components/ui/label';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { CONTEXT_LABEL, COUNTER_STRENGTH_LABEL } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { AlertTriangle, Crosshair, ExternalLink, Info, Plus, Sparkles, X } from 'lucide-react';
import { useMemo, useState, type ReactNode } from 'react';

interface HeroOption {
    id: number;
    slug: string;
    name: string;
    portrait_url: string | null;
}

interface RankedCounter {
    target: { id: number; name: string };
    strength: number;
    reason: string;
    source_url: string | null;
}

interface RankedHero {
    hero: { id: number; name: string; portrait_url: string | null };
    total_strength: number;
    tier: string | null;
    counters: RankedCounter[];
}

interface ThreateningCounter {
    by: { id: number; name: string };
    strength: number;
    reason: string;
    source_url: string | null;
}

interface ThreatenedHero {
    hero: { id: number; name: string; portrait_url: string | null };
    total_strength: number;
    counters: ThreateningCounter[];
}

interface PageProps {
    heroes: HeroOption[];
    enemy: Array<{ position: number; hero: HeroOption }>;
    contexts: string[];
    context: string;
    planning: Planning;
    ranking: RankedHero[];
    rankingTotal: number;
    threatened: ThreatenedHero[] | null;
    maxEnemy: number;
    frontline: number;
    [key: string]: unknown;
}

type Planning = 'real' | 'target';

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Counters', href: '/counters' }];

export default function CountersIndex({
    heroes,
    enemy,
    contexts,
    context,
    planning,
    ranking,
    rankingTotal,
    threatened,
    maxEnemy,
    frontline,
}: PageProps) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const enemyByPosition = Object.fromEntries(enemy.map((e) => [e.position, e.hero])) as Record<number, HeroOption | undefined>;

    // Equipe inimiga (posição => herói), Contexto e Planejamento vivem na URL, para voltar ou compartilhar.
    const navigate = (changes: { enemy?: Record<number, HeroOption | undefined>; context?: string; planning?: Planning; all?: boolean }) => {
        const nextEnemy = changes.enemy ?? enemyByPosition;
        const enemyQuery = Object.fromEntries(Object.entries(nextEnemy).flatMap(([pos, hero]) => (hero ? [[pos, hero.id]] : [])));
        router.get(
            '/counters',
            {
                ...(Object.keys(enemyQuery).length ? { enemy: enemyQuery } : {}),
                context: changes.context ?? context,
                planning: changes.planning ?? planning,
                ...(changes.all ? { all: 1 } : {}),
            },
            { preserveState: true, preserveScroll: true },
        );
    };
    const setEnemy = (next: Record<number, HeroOption | undefined>) => navigate({ enemy: next });

    // Export para IA: Arena (ataque) com a Equipe inimiga e os Counters conhecidos no prompt.
    // Memorizados para o modal não reaplicar o pré-preenchimento a cada render.
    const [aiExportOpen, setAiExportOpen] = useState(false);
    const aiExportPrefill = useMemo<AiExportPrefill>(() => ({ objective: 'arena_of_heroes', planning, params: { side: 'attack' } }), [planning]);
    const aiExportParams = useMemo(() => ({ enemy: Object.fromEntries(enemy.map((e) => [e.position, e.hero.id])) }), [enemy]);
    const isTarget = planning === 'target';

    const usedIds = new Set(enemy.map((e) => e.hero.id));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Counters" />

            <PageShell
                title="Counters"
                icon={Crosshair}
                description="Monte a Equipe inimiga e veja quais heróis a counteram."
                actions={
                    <Button variant="outline" size="sm" onClick={() => setAiExportOpen(true)} disabled={enemy.length === 0}>
                        <Sparkles className="mr-2 size-4" />
                        Exportar para IA
                    </Button>
                }
            >
                <div className="flex items-start gap-2 rounded-md border border-sky-500/30 bg-sky-500/10 p-3 text-xs text-sky-800 dark:text-sky-200">
                    <Info className="mt-0.5 size-4 shrink-0" />
                    <p>
                        A base de Counters é curada e está em construção: um herói sem Counters aqui não quer dizer que ninguém o countera. Os
                        Counters são informativos e não mudam a Pontuação das Equipes.
                    </p>
                </div>

                <div className="flex flex-wrap gap-3">
                    <div className="space-y-1.5">
                        <Label htmlFor="counters_planning">Planejamento</Label>
                        <Select value={planning} onValueChange={(value) => navigate({ planning: value as Planning })}>
                            <SelectTrigger id="counters_planning" className="w-[230px]">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="real">Real (meu Roster)</SelectItem>
                                <SelectItem value="target">Alvo (qualquer herói do jogo)</SelectItem>
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="space-y-1.5">
                        <Label htmlFor="counters_context">Contexto</Label>
                        <Select value={context} onValueChange={(value) => navigate({ context: value })}>
                            <SelectTrigger id="counters_context" className="w-[180px]">
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {contexts.map((c) => (
                                    <SelectItem key={c} value={c}>
                                        {CONTEXT_LABEL[c] ?? c}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                </div>

                <section className="rounded-xl border p-4">
                    <h2 className="mb-3 text-sm font-semibold">Equipe inimiga</h2>
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-5">
                        {Array.from({ length: maxEnemy }, (_, i) => i + 1).map((position) => (
                            <EnemySlot
                                key={position}
                                position={position}
                                frontline={frontline}
                                hero={enemyByPosition[position]}
                                heroes={heroes.filter((h) => !usedIds.has(h.id) || enemyByPosition[position]?.id === h.id)}
                                onPick={(hero) => setEnemy({ ...enemyByPosition, [position]: hero })}
                                onClear={() => setEnemy({ ...enemyByPosition, [position]: undefined })}
                            />
                        ))}
                    </div>
                    <p className="text-muted-foreground mt-2 text-xs">
                        Posições 1 a {frontline}: linha de frente · {frontline + 1} a {maxEnemy}: linha de trás.
                    </p>
                    {errors.enemy && (
                        <p className="text-destructive mt-2 flex items-center gap-1.5 text-sm">
                            <AlertTriangle className="size-4" />
                            {errors.enemy}
                        </p>
                    )}
                </section>

                {enemy.length > 0 && (
                    <section className="space-y-2">
                        <h2 className="text-sm font-semibold">
                            {isTarget ? 'Quem do jogo countera esta Equipe inimiga' : 'Quem do seu Roster countera esta Equipe inimiga'}
                        </h2>
                        {ranking.length === 0 ? (
                            <p className="text-muted-foreground rounded-xl border p-4 text-sm">
                                {isTarget
                                    ? 'Nenhum herói tem Counter cadastrado contra esta Equipe inimiga neste Contexto.'
                                    : 'Nenhum herói do seu Roster tem Counter cadastrado contra esta Equipe inimiga neste Contexto.'}
                            </p>
                        ) : (
                            <ol className="space-y-2">
                                {ranking.map((entry, index) => (
                                    <li key={entry.hero.id} className="rounded-xl border p-3">
                                        <div className="flex items-center gap-2">
                                            <span className="text-muted-foreground w-5 text-sm">{index + 1}.</span>
                                            <span className="font-medium">{entry.hero.name}</span>
                                            <Badge variant="secondary">força {entry.total_strength}</Badge>
                                            {entry.tier && <Badge variant="outline">tier {entry.tier}</Badge>}
                                        </div>
                                        <ul className="mt-2 space-y-1 pl-7 text-sm">
                                            {entry.counters.map((counter) => (
                                                <CounterLine key={counter.target.id} counter={counter}>
                                                    contra <strong>{counter.target.name}</strong>
                                                </CounterLine>
                                            ))}
                                        </ul>
                                    </li>
                                ))}
                            </ol>
                        )}
                        {isTarget && ranking.length < rankingTotal && (
                            <Button variant="outline" size="sm" onClick={() => navigate({ all: true })}>
                                Ver mais ({rankingTotal - ranking.length})
                            </Button>
                        )}
                    </section>
                )}

                {enemy.length > 0 && threatened !== null && (
                    <section className="space-y-2">
                        <h2 className="text-sm font-semibold">Heróis do seu Roster que esta Equipe inimiga countera</h2>
                        {threatened.length === 0 ? (
                            <p className="text-muted-foreground rounded-xl border p-4 text-sm">
                                Nenhum herói do seu Roster é counterado por esta Equipe inimiga neste Contexto.
                            </p>
                        ) : (
                            <ul className="space-y-2">
                                {threatened.map((entry) => (
                                    <li key={entry.hero.id} className="rounded-xl border border-rose-500/30 p-3">
                                        <div className="flex items-center gap-2">
                                            <span className="font-medium">{entry.hero.name}</span>
                                            <Badge variant="destructive">força {entry.total_strength}</Badge>
                                        </div>
                                        <ul className="mt-2 space-y-1 text-sm">
                                            {entry.counters.map((counter) => (
                                                <CounterLine key={counter.by.id} counter={counter}>
                                                    <strong>{counter.by.name}</strong> countera
                                                </CounterLine>
                                            ))}
                                        </ul>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </section>
                )}
            </PageShell>

            <AiExportDialog open={aiExportOpen} onOpenChange={setAiExportOpen} prefill={aiExportPrefill} extraParams={aiExportParams} />
        </AppLayout>
    );
}

function EnemySlot({
    position,
    frontline,
    hero,
    heroes,
    onPick,
    onClear,
}: {
    position: number;
    frontline: number;
    hero: HeroOption | undefined;
    heroes: HeroOption[];
    onPick: (hero: HeroOption) => void;
    onClear: () => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <div className={cn('relative rounded-lg border p-2', position <= frontline ? 'border-amber-500/40' : 'border-sky-500/30')}>
            <div className="text-muted-foreground mb-1 text-[10px] uppercase">
                {position} · {position <= frontline ? 'frente' : 'trás'}
            </div>
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button variant="outline" size="sm" className="w-full justify-start truncate">
                        {hero ? (
                            hero.name
                        ) : (
                            <span className="text-muted-foreground flex items-center gap-1">
                                <Plus className="size-3.5" /> Herói
                            </span>
                        )}
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-[220px] p-0" align="start">
                    <Command>
                        <CommandInput placeholder="Buscar herói..." />
                        <CommandList>
                            <CommandEmpty>Não encontrado.</CommandEmpty>
                            <CommandGroup>
                                {heroes.map((option) => (
                                    <CommandItem
                                        key={option.id}
                                        value={option.name + ' ' + option.slug}
                                        onSelect={() => {
                                            onPick(option);
                                            setOpen(false);
                                        }}
                                    >
                                        {option.name}
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
            {hero && (
                <button
                    type="button"
                    onClick={onClear}
                    aria-label={`Tirar ${hero.name}`}
                    className="text-muted-foreground hover:text-foreground absolute top-1.5 right-1.5"
                >
                    <X className="size-3.5" />
                </button>
            )}
        </div>
    );
}

/** Uma linha de Counter: quem / contra quem (children), força, motivo e fonte. */
function CounterLine({ counter, children }: { counter: { strength: number; reason: string; source_url: string | null }; children: ReactNode }) {
    return (
        <li className="flex flex-wrap items-center gap-1.5">
            <span>{children}</span>
            <Badge variant={counter.strength === 2 ? 'default' : 'outline'} className="text-[10px]">
                {COUNTER_STRENGTH_LABEL[counter.strength] ?? counter.strength}
            </Badge>
            <span className="text-muted-foreground">{counter.reason}</span>
            {counter.source_url && (
                <a
                    href={counter.source_url}
                    target="_blank"
                    rel="noreferrer"
                    className="text-muted-foreground hover:text-foreground"
                    aria-label="Abrir fonte"
                >
                    <ExternalLink className="size-3.5" />
                </a>
            )}
        </li>
    );
}
