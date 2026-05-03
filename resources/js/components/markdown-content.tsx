import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { cn } from '@/lib/utils';

interface MarkdownContentProps {
    children: string;
    className?: string;
}

/**
 * Renderiza conteúdo Markdown (CommonMark + GFM tables) com estilos
 * compatíveis com o design system do app.
 */
export function MarkdownContent({ children, className }: MarkdownContentProps) {
    return (
        <div className={cn('text-sm leading-relaxed', className)}>
            <ReactMarkdown
                remarkPlugins={[remarkGfm]}
                components={{
                    h1: ({ children }) => (
                        <h1 className="mt-4 mb-2 text-xl font-semibold first:mt-0">{children}</h1>
                    ),
                    h2: ({ children }) => (
                        <h2 className="mt-4 mb-2 text-lg font-semibold first:mt-0">{children}</h2>
                    ),
                    h3: ({ children }) => (
                        <h3 className="mt-3 mb-1.5 text-base font-semibold first:mt-0">{children}</h3>
                    ),
                    h4: ({ children }) => (
                        <h4 className="mt-2 mb-1 text-sm font-semibold uppercase tracking-wide text-muted-foreground first:mt-0">
                            {children}
                        </h4>
                    ),
                    p: ({ children }) => <p className="mb-2 last:mb-0">{children}</p>,
                    ul: ({ children }) => <ul className="mb-2 ml-5 list-disc space-y-1 last:mb-0">{children}</ul>,
                    ol: ({ children }) => <ol className="mb-2 ml-5 list-decimal space-y-1 last:mb-0">{children}</ol>,
                    li: ({ children }) => <li className="leading-snug">{children}</li>,
                    strong: ({ children }) => <strong className="font-semibold">{children}</strong>,
                    em: ({ children }) => <em className="italic">{children}</em>,
                    a: ({ href, children }) => (
                        <a
                            href={href}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="font-medium text-primary underline-offset-2 hover:underline"
                        >
                            {children}
                        </a>
                    ),
                    table: ({ children }) => (
                        <div className="my-3 overflow-x-auto rounded-lg border">
                            <table className="w-full text-xs">{children}</table>
                        </div>
                    ),
                    thead: ({ children }) => <thead className="bg-muted/50">{children}</thead>,
                    th: ({ children }) => (
                        <th className="border-b px-3 py-2 text-left font-semibold">{children}</th>
                    ),
                    td: ({ children }) => (
                        <td className="border-b px-3 py-2 last:border-b-0">{children}</td>
                    ),
                    code: ({ children }) => (
                        <code className="rounded bg-muted px-1 py-0.5 font-mono text-xs">{children}</code>
                    ),
                    blockquote: ({ children }) => (
                        <blockquote className="my-2 border-l-2 border-primary/40 pl-3 italic text-muted-foreground">
                            {children}
                        </blockquote>
                    ),
                    hr: () => <hr className="my-4 border-border" />,
                }}
            >
                {children}
            </ReactMarkdown>
        </div>
    );
}
