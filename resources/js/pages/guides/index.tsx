import { PageShell } from '@/components/page-shell';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { localized } from '@/lib/afk';
import { cn } from '@/lib/utils';
import type { BreadcrumbItem, Locale, SharedData } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { BookOpen, ChevronRight, Cog, Map, Sparkles } from 'lucide-react';

interface GuideListItem {
    id: number;
    slug: string;
    title: string;
    title_pt: string | null;
    category: string;
    summary: string | null;
    summary_pt: string | null;
}

interface CategoryGroup {
    code: string;
    label: string;
    guides: GuideListItem[];
}

interface PageProps {
    categories: CategoryGroup[];
}

const breadcrumbs: BreadcrumbItem[] = [{ title: 'Guias', href: '/guides' }];

const CATEGORY_ICON: Record<string, typeof Cog> = {
    mecanicas: Cog,
    modos: Map,
    eventos: Sparkles,
};

export default function GuidesIndex({ categories }: PageProps) {
    const { auth } = usePage<SharedData>().props;
    const locale = (auth.user?.locale ?? 'en') as Locale;
    const totalGuides = categories.reduce((acc, c) => acc + c.guides.length, 0);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Guias" />

            <PageShell
                title="Guias do jogo"
                icon={BookOpen}
                description={
                    <>
                        {totalGuides === 0 ? 'Sem guias publicados ainda.' : `${totalGuides} guias disponíveis.`} Conteúdo extraído da Fandom Wiki —
                        sempre confira o link da fonte para a versão mais atualizada.
                    </>
                }
            >
                {categories.map((category) => {
                    const Icon = CATEGORY_ICON[category.code] ?? BookOpen;
                    if (category.guides.length === 0) return null;

                    return (
                        <section key={category.code} className="flex flex-col gap-2">
                            <h2 className="flex items-center gap-2 text-lg font-semibold">
                                <Icon className="text-muted-foreground size-5" />
                                {category.label}
                                <Badge variant="secondary" className="text-[10px]">
                                    {category.guides.length}
                                </Badge>
                            </h2>

                            <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                {category.guides.map((guide) => {
                                    const title = localized(guide, 'title', locale) ?? guide.title;
                                    const summary = localized(guide, 'summary', locale);
                                    return (
                                        <Link
                                            key={guide.id}
                                            href={`/guides/${guide.slug}`}
                                            className={cn(
                                                'group bg-card flex flex-col gap-1.5 rounded-xl border p-3 transition-colors',
                                                'hover:border-primary/40 hover:bg-card/80',
                                            )}
                                        >
                                            <div className="flex items-start justify-between gap-2">
                                                <h3 className="text-sm leading-tight font-semibold">{title}</h3>
                                                <ChevronRight className="text-muted-foreground size-4 shrink-0 transition-transform group-hover:translate-x-0.5" />
                                            </div>
                                            {summary && <p className="text-muted-foreground line-clamp-3 text-xs">{summary}</p>}
                                        </Link>
                                    );
                                })}
                            </div>
                        </section>
                    );
                })}

                {totalGuides === 0 && (
                    <div className="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed py-16 text-center">
                        <BookOpen className="text-muted-foreground size-10" />
                        <p className="text-muted-foreground text-sm">
                            Sem guias publicados. Rode{' '}
                            <code className="bg-muted rounded px-1 py-0.5 text-xs">php artisan db:seed --class=GuideSeeder</code> para puxar conteúdo
                            da wiki.
                        </p>
                    </div>
                )}
            </PageShell>
        </AppLayout>
    );
}
