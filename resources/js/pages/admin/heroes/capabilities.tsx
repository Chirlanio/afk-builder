import { CapabilityList } from '@/components/capability-badges';
import { PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { CAPABILITIES, CAPABILITY_GROUP_CLASS, type CapabilityTag } from '@/lib/capabilities';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import { Head } from '@inertiajs/react';
import axios from 'axios';
import { Loader2, RotateCcw, Search, Sparkles } from 'lucide-react';
import { useMemo, useState } from 'react';

interface HeroRow {
    id: number;
    slug: string;
    name: string;
    portrait_url: string | null;
    role_label: string | null;
    faction: { code: string; name_pt: string } | null;
    hero_class: { code: string; name_pt: string } | null;
    classified: Record<string, number>;
    effective: Record<string, number>;
    has_manual: boolean;
}

interface PageProps {
    heroes: HeroRow[];
    tags: CapabilityTag[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Capacidades dos heróis', href: '/admin/heroes/capabilities' }];

type Filter = 'all' | 'manual' | 'thin';

export default function HeroCapabilitiesAdmin({ heroes: initialHeroes, tags }: PageProps) {
    const [heroes, setHeroes] = useState(initialHeroes);
    const [query, setQuery] = useState('');
    const [filter, setFilter] = useState<Filter>('all');
    const [editing, setEditing] = useState<HeroRow | null>(null);

    const visible = useMemo(() => {
        const q = query.trim().toLowerCase();
        return heroes.filter((hero) => {
            if (q && !hero.name.toLowerCase().includes(q) && !hero.slug.includes(q)) return false;
            if (filter === 'manual') return hero.has_manual;
            if (filter === 'thin') return Object.keys(hero.effective).length <= 2;
            return true;
        });
    }, [heroes, query, filter]);

    const replaceHero = (updated: HeroRow) => setHeroes((list) => list.map((hero) => (hero.id === updated.id ? updated : hero)));

    const counts = {
        all: heroes.length,
        manual: heroes.filter((hero) => hero.has_manual).length,
        thin: heroes.filter((hero) => Object.keys(hero.effective).length <= 2).length,
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Capacidades dos heróis (admin)" />

            <PageShell
                title="Capacidades dos heróis"
                icon={Sparkles}
                description="Perfil lido automaticamente das habilidades e do papel da wiki. Corrija o que o classificador errar — as correções sobrevivem às reclassificações diárias."
            >
                <div className="mb-4 flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="text-muted-foreground absolute top-1/2 left-2.5 size-4 -translate-y-1/2" />
                        <Input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Buscar herói..." className="pl-8" />
                    </div>
                    <div className="flex gap-1">
                        {(
                            [
                                ['all', 'Todos'],
                                ['thin', 'Poucas capacidades'],
                                ['manual', 'Corrigidos'],
                            ] as const
                        ).map(([key, label]) => (
                            <Button key={key} size="sm" variant={filter === key ? 'default' : 'outline'} onClick={() => setFilter(key)}>
                                {label} <span className="ml-1 opacity-60">{counts[key]}</span>
                            </Button>
                        ))}
                    </div>
                </div>

                <div className="grid gap-2 md:grid-cols-2">
                    {visible.map((hero) => (
                        <button
                            key={hero.id}
                            type="button"
                            onClick={() => setEditing(hero)}
                            className="bg-card hover:border-primary/40 flex min-w-0 items-start gap-3 rounded-lg border p-3 text-left transition-colors"
                        >
                            {hero.portrait_url ? (
                                <img src={hero.portrait_url} alt="" className="size-12 shrink-0 rounded-md object-cover" loading="lazy" />
                            ) : (
                                <div className="bg-muted size-12 shrink-0 rounded-md" />
                            )}
                            <div className="min-w-0 flex-1 space-y-1.5">
                                <div className="flex flex-wrap items-center gap-1.5">
                                    <span className="truncate font-semibold">{hero.name}</span>
                                    {hero.role_label && (
                                        <Badge variant="secondary" className="text-[10px]">
                                            {hero.role_label}
                                        </Badge>
                                    )}
                                    {hero.has_manual && (
                                        <Badge variant="outline" className="border-amber-400/40 text-[10px] text-amber-300">
                                            corrigido
                                        </Badge>
                                    )}
                                </div>
                                <CapabilityList profile={hero.effective} />
                                {Object.keys(hero.effective).length === 0 && (
                                    <p className="text-muted-foreground text-xs">Sem capacidades detectadas.</p>
                                )}
                            </div>
                        </button>
                    ))}
                </div>

                {visible.length === 0 && <p className="text-muted-foreground py-10 text-center text-sm">Nenhum herói encontrado.</p>}
            </PageShell>

            {editing && <CapabilityEditor hero={editing} tags={tags} onClose={() => setEditing(null)} onSaved={replaceHero} />}
        </AppLayout>
    );
}

function CapabilityEditor({
    hero,
    tags,
    onClose,
    onSaved,
}: {
    hero: HeroRow;
    tags: CapabilityTag[];
    onClose: () => void;
    onSaved: (hero: HeroRow) => void;
}) {
    const [draft, setDraft] = useState<Record<string, number>>({ ...hero.effective });
    const [saving, setSaving] = useState(false);
    const [error, setError] = useState<string | null>(null);

    const submit = async (request: () => Promise<{ data: { effective: Record<string, number>; has_manual: boolean } }>) => {
        setSaving(true);
        setError(null);
        try {
            const { data } = await request();
            onSaved({ ...hero, effective: data.effective, has_manual: data.has_manual });
            onClose();
        } catch {
            setError('Não foi possível salvar. Tente novamente.');
        } finally {
            setSaving(false);
        }
    };

    const save = () => {
        const payload = Object.fromEntries(tags.map((tag) => [tag, draft[tag] ?? 0]));
        submit(() => axios.patch(`/admin/heroes/${hero.id}/capabilities`, { capabilities: payload }));
    };

    const reset = () => submit(() => axios.delete(`/admin/heroes/${hero.id}/capabilities`));

    return (
        <Dialog open onOpenChange={(open) => !open && onClose()}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                <DialogHeader>
                    <DialogTitle>{hero.name}</DialogTitle>
                    <DialogDescription>
                        Força 1 = o herói tem a capacidade; 2 = é um dos focos do kit. O ponto indica o valor original do classificador.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-1.5 sm:grid-cols-2">
                    {tags.map((tag) => {
                        const meta = CAPABILITIES[tag];
                        const value = draft[tag] ?? 0;
                        const original = hero.classified[tag] ?? 0;
                        return (
                            <div key={tag} className="flex items-center justify-between gap-2 rounded-md border px-2 py-1.5" title={meta.description}>
                                <span className={cn('rounded border px-1.5 py-0.5 text-[11px]', CAPABILITY_GROUP_CLASS[meta.group])}>
                                    {meta.label}
                                </span>
                                <div className="flex gap-0.5">
                                    {[0, 1, 2].map((level) => (
                                        <button
                                            key={level}
                                            type="button"
                                            onClick={() => setDraft((d) => ({ ...d, [tag]: level }))}
                                            className={cn(
                                                'relative size-7 rounded text-xs font-medium',
                                                value === level ? 'bg-primary text-primary-foreground' : 'bg-muted hover:bg-muted/70',
                                            )}
                                        >
                                            {level}
                                            {original === level && <span className="absolute top-0.5 right-0.5 size-1 rounded-full bg-amber-400" />}
                                        </button>
                                    ))}
                                </div>
                            </div>
                        );
                    })}
                </div>

                {error && <p className="text-destructive text-sm">{error}</p>}

                <DialogFooter className="gap-2 sm:justify-between">
                    <Button variant="ghost" onClick={reset} disabled={saving || !hero.has_manual}>
                        <RotateCcw className="size-4" />
                        Voltar ao classificador
                    </Button>
                    <div className="flex gap-2">
                        <Button variant="outline" onClick={onClose} disabled={saving}>
                            Cancelar
                        </Button>
                        <Button onClick={save} disabled={saving}>
                            {saving && <Loader2 className="size-4 animate-spin" />}
                            Salvar
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
