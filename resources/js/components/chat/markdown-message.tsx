import { Check, Copy } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import ReactMarkdown, { type Components } from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { cn } from '@/lib/utils';

/**
 * Flatten the text nodes a fenced code block is made of.
 */
function toText(node: ReactNode): string {
    if (typeof node === 'string') {
        return node;
    }

    if (Array.isArray(node)) {
        return node.map(toText).join('');
    }

    return '';
}

const components: Components = {
    p: ({ children }) => <p className="not-first:mt-4">{children}</p>,
    h1: ({ children }) => (
        <h1 className="mt-6 mb-2 text-lg font-semibold first:mt-0">
            {children}
        </h1>
    ),
    h2: ({ children }) => (
        <h2 className="mt-6 mb-2 text-base font-semibold first:mt-0">
            {children}
        </h2>
    ),
    h3: ({ children }) => (
        <h3 className="mt-5 mb-2 text-sm font-semibold first:mt-0">
            {children}
        </h3>
    ),
    ul: ({ children }) => (
        <ul className="mt-3 ml-5 list-disc space-y-1 first:mt-0">{children}</ul>
    ),
    ol: ({ children }) => (
        <ol className="mt-3 ml-5 list-decimal space-y-1 first:mt-0">
            {children}
        </ol>
    ),
    li: ({ children }) => <li className="pl-1">{children}</li>,
    a: ({ children, href }) => (
        <a
            href={href}
            target="_blank"
            rel="noopener noreferrer"
            className="font-medium underline underline-offset-4"
        >
            {children}
        </a>
    ),
    blockquote: ({ children }) => (
        <blockquote className="border-border text-muted-foreground mt-4 border-l-2 pl-4 italic first:mt-0">
            {children}
        </blockquote>
    ),
    hr: () => <hr className="border-border my-6" />,
    strong: ({ children }) => (
        <strong className="font-semibold">{children}</strong>
    ),
    table: ({ children }) => (
        <div className="mt-4 overflow-x-auto first:mt-0">
            <table className="w-full border-collapse text-left text-xs">
                {children}
            </table>
        </div>
    ),
    th: ({ children }) => (
        <th className="border-border border-b px-3 py-2 font-semibold">
            {children}
        </th>
    ),
    td: ({ children }) => (
        <td className="border-border/60 border-b px-3 py-2 align-top">
            {children}
        </td>
    ),
    code: ({ className, children }) => {
        const language = /language-(\w+)/.exec(className ?? '')?.[1];
        const content = toText(children).replace(/\n$/, '');

        if (!language && !content.includes('\n')) {
            return (
                <code className="bg-muted rounded px-1.5 py-0.5 font-mono text-[0.85em]">
                    {children}
                </code>
            );
        }

        return <CodeBlock language={language} content={content} />;
    },
    pre: ({ children }) => <>{children}</>,
};

function CodeBlock({
    language,
    content,
}: {
    language?: string;
    content: string;
}) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        await navigator.clipboard.writeText(content);
        setCopied(true);
        setTimeout(() => setCopied(false), 2000);
    };

    return (
        <div className="border-border bg-muted/50 mt-4 overflow-hidden rounded-xl border first:mt-0">
            <div className="border-border/60 text-muted-foreground flex items-center justify-between border-b px-3 py-1.5 text-xs">
                <span className="font-mono">{language ?? 'texto'}</span>
                <button
                    type="button"
                    onClick={() => void copy()}
                    className="hover:text-foreground flex items-center gap-1 transition-colors"
                    aria-label="Copiar código"
                >
                    {copied ? (
                        <Check className="size-3.5" />
                    ) : (
                        <Copy className="size-3.5" />
                    )}
                    {copied ? 'Copiado' : 'Copiar'}
                </button>
            </div>

            <pre className="overflow-x-auto p-3">
                <code className="font-mono text-xs">{content}</code>
            </pre>
        </div>
    );
}

export function MarkdownMessage({
    content,
    className,
}: {
    content: string;
    className?: string;
}) {
    return (
        <div className={cn('text-sm leading-relaxed', className)}>
            <ReactMarkdown remarkPlugins={[remarkGfm]} components={components}>
                {content}
            </ReactMarkdown>
        </div>
    );
}
