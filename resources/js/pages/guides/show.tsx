import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, BookOpen, ExternalLink } from 'lucide-react';
import { MarkdownContent } from '@/components/markdown-content';
import { Badge } from '@/components/ui/badge';
import AppLayout from '@/layouts/app-layout';
import { localized } from '@/lib/afk';
import type { BreadcrumbItem, Locale, SharedData } from '@/types';

interface Guide {
    id: number;
    slug: string;
    title: string;
    title_pt: string | null;
    category: string;
    summary: string | null;
    summary_pt: string | null;
    content: string | null;
    content_pt: string | null;
    source_url: string | null;
    updated_at: string;
}

interface PageProps {
    guide: Guide;
    categoryLabel: string;
}

export default function GuideShow({ guide, categoryLabel }: PageProps) {
    const { auth } = usePage<SharedData>().props;
    const locale = (auth.user?.locale ?? 'en') as Locale;
    const title = localized(guide, 'title', locale) ?? guide.title;
    const summary = localized(guide, 'summary', locale);
    const content = localized(guide, 'content', locale);

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Guias', href: '/guias' },
        { title, href: `/guias/${guide.slug}` },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={title} />

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
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    {summary && <p className="text-sm text-muted-foreground">{summary}</p>}
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

                {content ? (
                    <article className="rounded-xl border bg-card p-5">
                        <MarkdownContent>{content}</MarkdownContent>
                    </article>
                ) : (
                    <div className="rounded-xl border border-dashed p-8 text-center text-sm text-muted-foreground">
                        {locale === 'pt' && guide.content
                            ? 'Tradução ainda não disponível para este guia.'
                            : 'Conteúdo ainda não importado para este guia.'}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
