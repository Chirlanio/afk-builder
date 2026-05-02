import { Head, Link, router } from '@inertiajs/react';
import { Plus, Swords, Target, Trash2 } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { ChallengeType, Formation } from '@/types/afk';

interface FormationListItem extends Formation {
    challenge_type: ChallengeType;
}

interface PageProps {
    formations: FormationListItem[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Formações', href: '/formations' }];

export default function FormationsIndex({ formations }: PageProps) {
    const remove = (formation: FormationListItem) => {
        if (!confirm(`Remover a formação "${formation.name}"?`)) return;
        router.delete(`/formations/${formation.id}`, { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Formações" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold">Formações</h1>
                        <p className="text-sm text-muted-foreground">
                            {formations.length === 0
                                ? 'Você ainda não criou nenhuma formação.'
                                : `${formations.length} ${formations.length === 1 ? 'formação' : 'formações'}.`}
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/formations/create">
                            <Plus className="size-4" />
                            Nova formação
                        </Link>
                    </Button>
                </div>

                {formations.length === 0 ? (
                    <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed py-16 text-center">
                        <Swords className="size-10 text-muted-foreground" />
                        <p className="text-sm text-muted-foreground">
                            Crie sua primeira formação para começar a montar equipes.
                        </p>
                        <Button asChild>
                            <Link href="/formations/create">
                                <Plus className="size-4" />
                                Criar formação
                            </Link>
                        </Button>
                    </div>
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        {formations.map((formation) => (
                            <div
                                key={formation.id}
                                className="group flex flex-col gap-2 rounded-xl border bg-card p-4 transition-colors hover:border-foreground/20"
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <Link
                                        href={`/formations/${formation.id}/edit`}
                                        className="flex-1 truncate text-base font-semibold hover:underline"
                                    >
                                        {formation.name}
                                    </Link>
                                    <Button
                                        size="icon"
                                        variant="ghost"
                                        onClick={() => remove(formation)}
                                        className="size-7 opacity-0 transition-opacity group-hover:opacity-100"
                                        aria-label="Remover"
                                    >
                                        <Trash2 className="size-3.5" />
                                    </Button>
                                </div>

                                <div className="flex flex-wrap items-center gap-1.5">
                                    <Badge variant="secondary" className="gap-1">
                                        <Swords className="size-3" />
                                        {formation.challenge_type.name_pt}
                                    </Badge>
                                    {formation.mode === 'target' && (
                                        <Badge variant="outline" className="gap-1">
                                            <Target className="size-3" />
                                            Alvo
                                        </Badge>
                                    )}
                                    <Badge variant="outline">
                                        {formation.teams_count} {formation.teams_count === 1 ? 'equipe' : 'equipes'}
                                    </Badge>
                                    {formation.challenge_type.is_pvp && (
                                        <Badge variant="outline" className="border-rose-500/40 text-rose-600 dark:text-rose-400">
                                            PvP
                                        </Badge>
                                    )}
                                </div>

                                <p className="text-xs text-muted-foreground">
                                    Atualizada {new Date(formation.updated_at).toLocaleDateString('pt-BR')}
                                </p>
                            </div>
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
