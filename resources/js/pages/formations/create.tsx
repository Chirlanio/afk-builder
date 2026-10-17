import { PageShell } from '@/components/page-shell';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem } from '@/types';
import type { ChallengeType, FormationMode } from '@/types/afk';
import { Head, useForm } from '@inertiajs/react';
import { Plus, Swords, Target, Users } from 'lucide-react';
import { useMemo } from 'react';

interface PageProps {
    challengeTypes: ChallengeType[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Formações', href: '/formations' },
    { title: 'Nova', href: '/formations/create' },
];

interface FormShape {
    name: string;
    challenge_type_id: string;
    mode: FormationMode;
    teams_count: number | null;
    [key: string]: string | number | null;
}

export default function FormationsCreate({ challengeTypes }: PageProps) {
    const form = useForm<FormShape>({
        name: '',
        challenge_type_id: '',
        mode: 'real',
        teams_count: null,
    });

    const selected = useMemo(
        () => challengeTypes.find((c) => String(c.id) === form.data.challenge_type_id) ?? null,
        [challengeTypes, form.data.challenge_type_id],
    );

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post('/formations');
    };

    const teamsCount = form.data.teams_count ?? selected?.max_teams ?? 1;
    const teamsCountLocked = !!selected && selected.min_teams === selected.max_teams;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Nova formação" />

            <PageShell
                title="Nova formação"
                icon={Plus}
                description="Escolha o tipo de desafio e o modo. Você poderá montar as equipes na próxima tela."
                width="narrow"
            >
                <form onSubmit={submit} className="bg-card grid gap-4 rounded-xl border p-4">
                    <div className="space-y-1.5">
                        <Label htmlFor="name">Nome</Label>
                        <Input
                            id="name"
                            value={form.data.name}
                            onChange={(e) => form.setData('name', e.target.value)}
                            placeholder="Ex: Campeonato de maio, PvP main, etc."
                            required
                            maxLength={100}
                            autoFocus
                        />
                        {form.errors.name && <p className="text-destructive text-xs">{form.errors.name}</p>}
                    </div>

                    <div className="space-y-1.5">
                        <Label htmlFor="challenge_type">Tipo de desafio</Label>
                        <Select
                            value={form.data.challenge_type_id}
                            onValueChange={(v) => {
                                form.setData('challenge_type_id', v);
                                form.setData('teams_count', null);
                            }}
                        >
                            <SelectTrigger id="challenge_type">
                                <SelectValue placeholder="Selecione..." />
                            </SelectTrigger>
                            <SelectContent>
                                {challengeTypes.map((c) => (
                                    <SelectItem key={c.id} value={String(c.id)}>
                                        {c.name_pt}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        {form.errors.challenge_type_id && <p className="text-destructive text-xs">{form.errors.challenge_type_id}</p>}

                        {selected && (
                            <div className="bg-muted/40 text-muted-foreground mt-2 rounded-md border p-3 text-xs">
                                {selected.description && <p className="mb-2">{selected.description}</p>}
                                <ul className="flex flex-wrap gap-3">
                                    <li className="flex items-center gap-1">
                                        <Users className="size-3" />
                                        {selected.min_teams === selected.max_teams
                                            ? `${selected.max_teams} equipe(s)`
                                            : `${selected.min_teams}–${selected.max_teams} equipes`}
                                    </li>
                                    {selected.is_pvp && <li className={cn('font-medium text-rose-600 dark:text-rose-400')}>PvP</li>}
                                    {selected.teams_must_be_unique && (
                                        <li className="font-medium text-amber-600 dark:text-amber-400">Heróis não podem se repetir entre equipes</li>
                                    )}
                                </ul>
                            </div>
                        )}
                    </div>

                    {selected && (
                        <div className="space-y-1.5">
                            <Label htmlFor="teams_count">Quantidade de equipes</Label>
                            <Input
                                id="teams_count"
                                type="number"
                                min={selected.min_teams}
                                max={selected.max_teams}
                                value={teamsCount}
                                readOnly={teamsCountLocked}
                                disabled={teamsCountLocked}
                                onChange={(e) =>
                                    form.setData(
                                        'teams_count',
                                        Math.min(selected.max_teams, Math.max(selected.min_teams, Number(e.target.value) || selected.min_teams)),
                                    )
                                }
                            />
                            <p className="text-muted-foreground text-[11px]">
                                {teamsCountLocked
                                    ? `Este desafio requer exatamente ${selected.max_teams} ${selected.max_teams === 1 ? 'equipe' : 'equipes'}.`
                                    : `Entre ${selected.min_teams} e ${selected.max_teams} equipes. Você pode alterar depois.`}
                            </p>
                            {form.errors.teams_count && <p className="text-destructive text-xs">{form.errors.teams_count}</p>}
                        </div>
                    )}

                    <div className="space-y-1.5">
                        <Label>Modo</Label>
                        <div className="grid grid-cols-2 gap-2">
                            <button
                                type="button"
                                onClick={() => form.setData('mode', 'real')}
                                className={cn(
                                    'flex flex-col items-start gap-1 rounded-md border-2 p-3 text-left transition-colors',
                                    form.data.mode === 'real' ? 'border-primary bg-primary/5' : 'border-border hover:border-foreground/30',
                                )}
                            >
                                <div className="flex items-center gap-2 font-semibold">
                                    <Swords className="size-4" />
                                    Real
                                </div>
                                <span className="text-muted-foreground text-xs">Use só os heróis que você possui no roster.</span>
                            </button>
                            <button
                                type="button"
                                onClick={() => form.setData('mode', 'target')}
                                className={cn(
                                    'flex flex-col items-start gap-1 rounded-md border-2 p-3 text-left transition-colors',
                                    form.data.mode === 'target' ? 'border-primary bg-primary/5' : 'border-border hover:border-foreground/30',
                                )}
                            >
                                <div className="flex items-center gap-2 font-semibold">
                                    <Target className="size-4" />
                                    Alvo
                                </div>
                                <span className="text-muted-foreground text-xs">Planeje com heróis que ainda não tem (guia de progressão).</span>
                            </button>
                        </div>
                    </div>

                    <div className="flex justify-end gap-2 pt-2">
                        <Button type="button" variant="outline" onClick={() => window.history.back()}>
                            Cancelar
                        </Button>
                        <Button type="submit" disabled={form.processing || !form.data.challenge_type_id || !form.data.name}>
                            Criar e montar equipes
                        </Button>
                    </div>
                </form>
            </PageShell>
        </AppLayout>
    );
}
