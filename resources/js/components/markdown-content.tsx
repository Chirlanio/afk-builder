import { cn } from '@/lib/utils';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';

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
        <div className={cn('min-w-0 text-sm leading-relaxed break-words [&_*]:max-w-full [&_pre]:overflow-x-auto', className)}>
            <ReactMarkdown
                remarkPlugins={[remarkGfm]}
                components={{
                    h1: ({ children }) => <h1 className="mt-4 mb-2 text-xl font-semibold first:mt-0">{children}</h1>,
                    h2: ({ children }) => <h2 className="mt-4 mb-2 text-lg font-semibold first:mt-0">{children}</h2>,
                    h3: ({ children }) => <h3 className="mt-3 mb-1.5 text-base font-semibold first:mt-0">{children}</h3>,
                    h4: ({ children }) => (
                        <h4 className="text-muted-foreground mt-2 mb-1 text-sm font-semibold tracking-wide uppercase first:mt-0">{children}</h4>
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
                            className="text-primary font-medium underline-offset-2 hover:underline"
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
                    th: ({ children }) => <th className="border-b px-3 py-2 text-left font-semibold">{children}</th>,
                    td: ({ children }) => <td className="border-b px-3 py-2 last:border-b-0">{children}</td>,
                    code: ({ children }) => <code className="bg-muted rounded px-1 py-0.5 font-mono text-xs">{children}</code>,
                    blockquote: ({ children }) => (
                        <blockquote className="border-primary/40 text-muted-foreground my-2 border-l-2 pl-3 italic">{children}</blockquote>
                    ),
                    hr: () => <hr className="border-border my-4" />,
                }}
            >
                {children}
            </ReactMarkdown>
        </div>
    );
}
