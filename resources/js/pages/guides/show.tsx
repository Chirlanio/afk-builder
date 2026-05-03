import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, BookOpen, ExternalLink } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Guide {
    id: number;
    slug: string;
    title: string;
    category: string;
    summary: string | null;
    content: string | null;
    source_url: string | null;
    updated_at: string;
}

interface PageProps {
    guide: Guide;
    categoryLabel: string;
}

export default function GuideShow({ guide, categoryLabel }: PageProps) {
    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Guias', href: '/guias' },
        { title: guide.title, href: `/guias/${guide.slug}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={guide.title} />

            <div className="mx-auto flex h-full w-full max-w-3xl flex-col gap-4 p-4">
                <div className="flex items-center gap-2">
                    <Link href="/guias" className="text-muted-foreground hover:text-foreground">
                        <ArrowLeft className="size-4" />
                    </Link>
                    <span className="text-xs text-muted-foreground">Guias</span>
                </div>

                <header className="flex flex-col gap-2 rounded-xl border bg-card p-4">
                    <div className="flex flex-wrap items-center gap-2">
                        <Badge variant="secondary" className="gap-1">
                            <BookOpen className="size-3" />
                            {categoryLabel}
                        </Badge>
                    </div>
                    <h1 className="text-2xl font-semibold">{guide.title}</h1>
                    {guide.summary && <p className="text-sm text-muted-foreground">{guide.summary}</p>}
                    {guide.source_url && (
                        <a
                            href={guide.source_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-flex items-center gap-1 text-xs text-muted-foreground hover:text-foreground"
                        >
                            <ExternalLink className="size-3" />
                            Fonte: Fandom Wiki
                        </a>
                    )}
                </header>

                {guide.content ? (
                    <article className="rounded-xl border bg-card p-5 text-sm leading-relaxed whitespace-pre-wrap">
                        {guide.content}
                    </article>
                ) : (
                    <div className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        Conteúdo ainda não importado para este guia.
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
