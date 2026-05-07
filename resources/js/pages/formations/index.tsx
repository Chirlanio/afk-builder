import { Head, Link, router } from '@inertiajs/react';
import { GitCompareArrows, Plus, Swords, Target, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { useConfirm } from '@/components/ui/confirm-dialog';
import AppLayout from '@/layouts/app-layout';
import { cn } from '@/lib/utils';
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
    const [selected, setSelected] = useState<number[]>([]);
    const { confirm, dialog: confirmDialog } = useConfirm();

    const remove = async (formation: FormationListItem) => {
        const ok = await confirm({
            title: `Remover "${formation.name}"?`,
            description: 'Esta ação não pode ser desfeita. Todas as equipes e versões da formação serão apagadas.',
            confirmLabel: 'Remover',
            variant: 'destructive',
        });
        if (!ok) return;
        router.delete(`/formations/${formation.id}`, { preserveScroll: true });
    };

    const toggleSelected = (id: number) => {
        setSelected((prev) => {
            if (prev.includes(id)) return prev.filter((x) => x !== id);
            if (prev.length >= 2) return [prev[1], id];
            return [...prev, id];
        });
    };

    const compareNow = () => {
        if (selected.length !== 2) return;
        router.visit(`/formations/compare/${selected[0]}/${selected[1]}`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Formações" />

            <PageShell
                title="Formações"
                icon={Swords}
                description={
                    formations.length === 0
                        ? 'Você ainda não criou nenhuma formação.'
                        : `${formations.length} ${formations.length === 1 ? 'formação' : 'formações'}.`
                }
                actions={
                    <>
                        {selected.length === 2 && (
                            <Button variant="outline" onClick={compareNow}>
                                <GitCompareArrows className="size-4" />
                                Comparar selecionadas
                            </Button>
                        )}
                        <Button asChild>
                            <Link href="/formations/create">
                                <Plus className="size-4" />
                                Nova formação
                            </Link>
                        </Button>
                    </>
                }
            >
                {selected.length > 0 && (
                    <p className="text-xs text-muted-foreground">
                        {selected.length === 1
                            ? '1 formação selecionada — selecione outra para comparar.'
                            : `${selected.length} formações selecionadas. Clique "Comparar selecionadas".`}
                    </p>
                )}

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
                        {formations.map((formation) => {
                            const isSelected = selected.includes(formation.id);
                            return (
                            <div
                                key={formation.id}
                                className={cn(
                                    'group flex flex-col gap-2 rounded-xl border bg-card p-4 transition-colors',
                                    isSelected ? 'border-primary ring-1 ring-primary' : 'hover:border-foreground/20',
                                )}
                            >
                                <div className="flex items-start justify-between gap-2">
                                    <div className="flex flex-1 items-start gap-2">
                                        <Checkbox
                                            checked={isSelected}
                                            onCheckedChange={() => toggleSelected(formation.id)}
                                            aria-label={`Selecionar ${formation.name} para comparar`}
                                            className="mt-0.5"
                                        />
                                        <Link
                                            href={`/formations/${formation.id}/edit`}
                                            className="flex-1 truncate text-base font-semibold hover:underline"
                                        >
                                            {formation.name}
                                        </Link>
                                    </div>
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
                            );
                        })}
                    </div>
                )}
            </PageShell>
            {confirmDialog}
        </AppLayout>
    );
}
