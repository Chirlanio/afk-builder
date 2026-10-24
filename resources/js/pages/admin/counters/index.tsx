import { PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { CONTEXT_LABEL, COUNTER_STRENGTH_LABEL } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { Crosshair, ExternalLink, Loader2, Pencil, Search, Trash2 } from 'lucide-react';
import { useMemo, useState } from 'react';

interface HeroOption {
    id: number;
    slug: string;
    name: string;
    portrait_url: string | null;
}

interface CounterRow {
    id: number;
    hero: { id: number; name: string } | null;
    target: { id: number; name: string } | null;
    context: string;
    strength: number;
    reason: string;
    source_url: string | null;
}

interface PageProps {
    heroes: HeroOption[];
    contexts: string[];
    hero: HeroOption | null;
    counters: CounterRow[];
    counteredBy: CounterRow[];
}

const STRENGTHS = Object.keys(COUNTER_STRENGTH_LABEL).map(Number);

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Counters', href: '/admin/counters' }];

export default function AdminCountersIndex({ heroes, contexts, hero, counters, counteredBy }: PageProps) {
    const [query, setQuery] = useState('');
    const visible = useMemo(() => {
        const q = query.trim().toLowerCase();
        return q ? heroes.filter((h) => h.name.toLowerCase().includes(q) || h.slug.includes(q)) : heroes;
    }, [heroes, query]);

    const selectHero = (id: number) => router.get('/admin/counters', { hero: id }, { preserveState: true, preserveScroll: true });
    const reload = () => router.reload({ only: ['counters', 'counteredBy'] });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Counters (admin)" />

            <PageShell
                title="Counters"
                icon={Crosshair}
                description="Base curada de Counters: o Herói é forte contra o alvo num Contexto. Vale para o Herói, não para o Personagem. Informativo: não entra na Pontuação."
            >
                <div className="grid gap-4 lg:grid-cols-[280px_1fr]">
                    <div className="rounded-xl border p-3">
                        <div className="relative mb-2">
                            <Search className="text-muted-foreground absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                            <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Buscar herói..." className="pl-8" />
                        </div>
                        <ul className="max-h-[60vh] overflow-y-auto">
                            {visible.map((h) => (
                                <li key={h.id}>
                                    <button
                                        type="button"
                                        onClick={() => selectHero(h.id)}
                                        className={cn(
                                            'hover:bg-muted w-full rounded-md px-2 py-1.5 text-left text-sm',
                                            hero?.id === h.id && 'bg-muted font-medium',
                                        )}
                                    >
                                        {h.name}
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </div>

                    {hero ? (
                        <div className="space-y-4">
                            <NewCounterForm hero={hero} heroes={heroes} contexts={contexts} onCreated={reload} />
                            <CounterList
                                title={`${hero.name} countera`}
                                empty="Nenhum Counter cadastrado para este herói."
                                rows={counters}
                                other={(row) => row.target?.name ?? '?'}
                                onChanged={reload}
                            />
                            <CounterList
                                title={`Quem countera ${hero.name}`}
                                empty="Ninguém countera este herói na base."
                                rows={counteredBy}
                                other={(row) => row.hero?.name ?? '?'}
                                onChanged={reload}
                            />
                        </div>
                    ) : (
                        <p className="text-muted-foreground rounded-xl border p-6 text-sm">Escolha um herói para ver e cadastrar os Counters dele.</p>
                    )}
                </div>
            </PageShell>
        </AppLayout>
    );
}

function NewCounterForm({
    hero,
    heroes,
    contexts,
    onCreated,
}: {
    hero: HeroOption;
    heroes: HeroOption[];
    contexts: string[];
    onCreated: () => void;
}) {
    const [targetId, setTargetId] = useState('');
    const [context, setContext] = useState('pvp');
    const [strength, setStrength] = useState('2');
    const [reason, setReason] = useState('');
    const [sourceUrl, setSourceUrl] = useState('');
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const submit = async () => {
        setSaving(true);
        setError(null);
        try {
            await axios.post('/admin/counters', {
                hero_id: hero.id,
                target_hero_id: Number(targetId),
                context,
                strength: Number(strength),
                reason,
                source_url: sourceUrl || null,
            });
            setTargetId('');
            setReason('');
            setSourceUrl('');
            onCreated();
        } catch (err) {
            const errors = axios.isAxiosError(err) ? (err.response?.data?.errors as Record<string, string[]> | undefined) : undefined;
            setError(errors ? Object.values(errors).flat().join(' ') : 'Não foi possível salvar o Counter.');
        } finally {
            setSaving(false);
        }
    };

    return (
        <div className="space-y-3 rounded-xl border p-4">
            <h3 className="text-sm font-semibold">Novo Counter: {hero.name} é forte contra...</h3>
            <div className="grid gap-3 sm:grid-cols-3">
                <div className="space-y-1.5">
                    <Label htmlFor="counter_target">Alvo</Label>
                    <Select value={targetId} onValueChange={setTargetId}>
                        <SelectTrigger id="counter_target">
                            <SelectValue placeholder="Escolha o herói..." />
                        </SelectTrigger>
                        <SelectContent>
                            {heroes
                                .filter((h) => h.id !== hero.id)
                                .map((h) => (
                                    <SelectItem key={h.id} value={String(h.id)}>
                                        {h.name}
                                    </SelectItem>
                                ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="space-y-1.5">
                    <Label htmlFor="counter_context">Contexto</Label>
                    <Select value={context} onValueChange={setContext}>
                        <SelectTrigger id="counter_context">
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
                <div className="space-y-1.5">
                    <Label htmlFor="counter_strength">Força</Label>
                    <Select value={strength} onValueChange={setStrength}>
                        <SelectTrigger id="counter_strength">
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {STRENGTHS.map((s) => (
                                <SelectItem key={s} value={String(s)}>
                                    {s} · {COUNTER_STRENGTH_LABEL[s]}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <div className="space-y-1.5">
                <Label htmlFor="counter_reason">Motivo</Label>
                <Input id="counter_reason" value={reason} onChange={(e) => setReason(e.target.value)} placeholder="ex.: dissipa o escudo do alvo" />
            </div>
            <div className="space-y-1.5">
                <Label htmlFor="counter_source">Fonte (opcional)</Label>
                <Input id="counter_source" value={sourceUrl} onChange={(e) => setSourceUrl(e.target.value)} placeholder="https://..." />
            </div>
            {error && <p className="text-destructive text-sm">{error}</p>}
            <Button onClick={submit} disabled={saving || !targetId || !reason.trim()}>
                {saving && <Loader2 className="mr-2 size-4 animate-spin" />}
                Cadastrar Counter
            </Button>
        </div>
    );
}

function CounterList({
    title,
    empty,
    rows,
    other,
    onChanged,
}: {
    title: string;
    empty: string;
    rows: CounterRow[];
    other: (row: CounterRow) => string;
    onChanged: () => void;
}) {
    const [busyId, setBusyId] = useState<number | null>(null);
    const [editing, setEditing] = useState<{ id: number; reason: string; sourceUrl: string } | null>(null);

    const run = async (id: number, request: () => Promise<unknown>) => {
        setBusyId(id);
        try {
            await request();
            onChanged();
        } finally {
            setBusyId(null);
        }
    };

    const saveEdit = () => {
        if (!editing) return;
        const { id, reason, sourceUrl } = editing;
        run(id, () => axios.patch(`/admin/counters/${id}`, { reason, source_url: sourceUrl || null })).then(() => setEditing(null));
    };

    return (
        <div className="rounded-xl border p-4">
            <h3 className="mb-3 text-sm font-semibold">
                {title} <span className="text-muted-foreground font-normal">({rows.length})</span>
            </h3>
            {rows.length === 0 ? (
                <p className="text-muted-foreground text-sm">{empty}</p>
            ) : (
                <ul className="divide-y">
                    {rows.map((row) => (
                        <li key={row.id} className="flex flex-wrap items-center gap-2 py-2 text-sm">
                            <span className="font-medium">{other(row)}</span>
                            <Badge variant="outline">{CONTEXT_LABEL[row.context] ?? row.context}</Badge>
                            <Select
                                value={String(row.strength)}
                                onValueChange={(value) => run(row.id, () => axios.patch(`/admin/counters/${row.id}`, { strength: Number(value) }))}
                            >
                                <SelectTrigger className="h-7 w-[150px]">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {STRENGTHS.map((s) => (
                                        <SelectItem key={s} value={String(s)}>
                                            {s} · {COUNTER_STRENGTH_LABEL[s]}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {editing?.id === row.id ? (
                                <div className="flex min-w-0 flex-1 flex-wrap gap-2">
                                    <Input
                                        aria-label="Motivo"
                                        value={editing.reason}
                                        onChange={(e) => setEditing({ ...editing, reason: e.target.value })}
                                        className="h-7 min-w-[200px] flex-1"
                                    />
                                    <Input
                                        aria-label="Fonte"
                                        value={editing.sourceUrl}
                                        onChange={(e) => setEditing({ ...editing, sourceUrl: e.target.value })}
                                        placeholder="https://..."
                                        className="h-7 min-w-[160px] flex-1"
                                    />
                                    <Button size="sm" className="h-7" onClick={saveEdit} disabled={!editing.reason.trim() || busyId === row.id}>
                                        Salvar
                                    </Button>
                                    <Button size="sm" variant="ghost" className="h-7" onClick={() => setEditing(null)}>
                                        Cancelar
                                    </Button>
                                </div>
                            ) : (
                                <>
                                    <span className="text-muted-foreground min-w-0 flex-1">{row.reason}</span>
                                    {row.source_url && (
                                        <a
                                            href={row.source_url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="text-muted-foreground hover:text-foreground"
                                            aria-label="Abrir fonte"
                                        >
                                            <ExternalLink className="size-4" />
                                        </a>
                                    )}
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="size-7"
                                        aria-label="Editar motivo e fonte"
                                        onClick={() => setEditing({ id: row.id, reason: row.reason, sourceUrl: row.source_url ?? '' })}
                                    >
                                        <Pencil className="size-4" />
                                    </Button>
                                </>
                            )}
                            <Button
                                variant="ghost"
                                size="icon"
                                className="size-7"
                                aria-label="Remover Counter"
                                disabled={busyId === row.id}
                                onClick={() => run(row.id, () => axios.delete(`/admin/counters/${row.id}`))}
                            >
                                {busyId === row.id ? <Loader2 className="size-4 animate-spin" /> : <Trash2 className="size-4" />}
                            </Button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
