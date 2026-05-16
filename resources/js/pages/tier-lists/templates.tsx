import { Head, Link, router } from '@inertiajs/react';
import { Copy, LayoutTemplate, ListChecks, Sparkles } from 'lucide-react';
import { PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import type { UserTierList } from '@/types/afk';

type Template = UserTierList & {
    rows_count: number;
    items_count: number;
    user: { id: number; name: string };
};

interface PageProps {
    templates: Template[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Tier Lists', href: '/tier-lists' },
    { title: 'Templates', href: '/tier-lists/templates' },
];

export default function TierListTemplates({ templates }: PageProps) {
    const handleClone = (template: Template) => {
        router.post(`/tier-lists/templates/${template.id}/clone`);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Templates de Tier List" />

            <PageShell
                title="Templates de Tier List"
                icon={LayoutTemplate}
                description="Templates publicados por admins. Clone qualquer um pra editar sua versão pessoal."
                actions={
                    <Button size="sm" variant="outline" asChild>
                        <Link href="/tier-lists">
                            <ListChecks className="size-4" />
                            Minhas listas
                        </Link>
                    </Button>
                }
            >
                {templates.length === 0 ? (
                    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed py-16 text-center">
                        <Sparkles className="size-10 text-muted-foreground" />
                        <p className="mt-2 text-sm text-muted-foreground">Nenhum template disponível ainda.</p>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-3">
                        {templates.map((template) => (
                            <div key={template.id} className="flex flex-col gap-2 rounded-xl border bg-card p-4">
                                <div className="flex items-start justify-between gap-2">
                                    <div className="min-w-0 flex-1">
                                        <h3 className="truncate text-base font-semibold">{template.name}</h3>
                                        <p className="mt-1 text-xs text-muted-foreground">por {template.user.name}</p>
                                        <div className="mt-1 flex flex-wrap items-center gap-1">
                                            <Badge variant="outline" className="text-[10px]">{template.rows_count} tiers</Badge>
                                            <Badge variant="outline" className="text-[10px]">{template.items_count} heróis</Badge>
                                        </div>
                                    </div>
                                </div>
                                {template.description && (
                                    <p className="line-clamp-2 text-xs text-muted-foreground">{template.description}</p>
                                )}
                                <div className="mt-auto flex justify-end gap-1 border-t pt-2">
                                    <Button size="sm" onClick={() => handleClone(template)}>
                                        <Copy className="size-3" />
                                        Clonar
                                    </Button>
                                </div>
                            </div>
                        ))}
                    </div>
                )}
            </PageShell>
        </AppLayout>
    );
}
