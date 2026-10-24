import { PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from '@/components/ui/command';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import AppLayout from '@/layouts/app-layout';
import { COUNTER_STRENGTH_LABEL } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import { Head, router, usePage } from '@inertiajs/react';
import { AlertTriangle, Crosshair, ExternalLink, Info, Plus, X } from 'lucide-react';
import { useState } from 'react';

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

interface PageProps {
    heroes: HeroOption[];
    enemy: Array<{ position: number; hero: HeroOption }>;
    context: string;
    ranking: RankedHero[];
    [key: string]: unknown;
}

const POSITIONS = [1, 2, 3, 4, 5];

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Counters', href: '/counters' }];

export default function CountersIndex({ heroes, enemy, ranking }: PageProps) {
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const enemyByPosition = Object.fromEntries(enemy.map((e) => [e.position, e.hero])) as Record<number, HeroOption | undefined>;

    // A Equipe inimiga vive na URL (posição => herói), para voltar ou compartilhar.
    const setEnemy = (next: Record<number, HeroOption | undefined>) => {
        const query = Object.fromEntries(Object.entries(next).flatMap(([pos, hero]) => (hero ? [[pos, hero.id]] : [])));
        router.get('/counters', Object.keys(query).length ? { enemy: query } : {}, { preserveState: true, preserveScroll: true });
    };

    const usedIds = new Set(enemy.map((e) => e.hero.id));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Counters" />

            <PageShell title="Counters" icon={Crosshair} description="Monte a Equipe inimiga e veja quais heróis do seu Roster a counteram no PvP.">
                <div className="flex items-start gap-2 rounded-md border border-sky-500/30 bg-sky-500/10 p-3 text-xs text-sky-800 dark:text-sky-200">
                    <Info className="mt-0.5 size-4 shrink-0" />
                    <p>
                        A base de Counters é curada e está em construção: um herói sem Counters aqui não quer dizer que ninguém o countera. Os
                        Counters são informativos e não mudam a pontuação das formações.
                    </p>
                </div>

                <section className="rounded-xl border p-4">
                    <h2 className="mb-3 text-sm font-semibold">Equipe inimiga</h2>
                    <div className="grid grid-cols-2 gap-2 sm:grid-cols-5">
                        {POSITIONS.map((position) => (
                            <EnemySlot
                                key={position}
                                position={position}
                                hero={enemyByPosition[position]}
                                heroes={heroes.filter((h) => !usedIds.has(h.id) || enemyByPosition[position]?.id === h.id)}
                                onPick={(hero) => setEnemy({ ...enemyByPosition, [position]: hero })}
                                onClear={() => setEnemy({ ...enemyByPosition, [position]: undefined })}
                            />
                        ))}
                    </div>
                    <p className="text-muted-foreground mt-2 text-xs">Posições 1 e 2: linha de frente · 3, 4 e 5: linha de trás.</p>
                    {errors.enemy && (
                        <p className="text-destructive mt-2 flex items-center gap-1.5 text-sm">
                            <AlertTriangle className="size-4" />
                            {errors.enemy}
                        </p>
                    )}
                </section>

                {enemy.length > 0 && (
                    <section className="space-y-2">
                        <h2 className="text-sm font-semibold">Quem do seu Roster countera esta Equipe inimiga</h2>
                        {ranking.length === 0 ? (
                            <p className="text-muted-foreground rounded-xl border p-4 text-sm">
                                Nenhum herói do seu Roster tem Counter cadastrado contra esta Equipe inimiga.
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
                                                <li key={counter.target.id} className="flex flex-wrap items-center gap-1.5">
                                                    <span>
                                                        contra <strong>{counter.target.name}</strong>
                                                    </span>
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
                                            ))}
                                        </ul>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </section>
                )}
            </PageShell>
        </AppLayout>
    );
}

function EnemySlot({
    position,
    hero,
    heroes,
    onPick,
    onClear,
}: {
    position: number;
    hero: HeroOption | undefined;
    heroes: HeroOption[];
    onPick: (hero: HeroOption) => void;
    onClear: () => void;
}) {
    const [open, setOpen] = useState(false);

    return (
        <div className={cn('relative rounded-lg border p-2', position <= 2 ? 'border-amber-500/40' : 'border-sky-500/30')}>
            <div className="text-muted-foreground mb-1 text-[10px] uppercase">
                {position} · {position <= 2 ? 'frente' : 'trás'}
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
