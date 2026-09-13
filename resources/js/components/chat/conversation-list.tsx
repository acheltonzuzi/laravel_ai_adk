import { MessageSquare, PenSquare } from 'lucide-react';
import { useEffect, useRef } from 'react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { cn } from '@/lib/utils';
import type { Conversation } from '@/types/chat';

type Props = {
    conversations: Conversation[];
    activeId: string | null;
    loading: boolean;
    hasMore: boolean;
    onSelect: (conversation: Conversation) => void;
    onNewConversation: () => void;
    onLoadMore: () => void;
};

export function ConversationList({
    conversations,
    activeId,
    loading,
    hasMore,
    onSelect,
    onNewConversation,
    onLoadMore,
}: Props) {
    const sentinelRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        const sentinel = sentinelRef.current;

        if (!sentinel || !hasMore) {
            return;
        }

        const observer = new IntersectionObserver((entries) => {
            if (entries[0].isIntersecting) {
                onLoadMore();
            }
        });

        observer.observe(sentinel);

        return () => observer.disconnect();
    }, [hasMore, onLoadMore]);

    return (
        <div className="flex h-full min-h-0 flex-1 flex-col gap-2">
            <div className="px-2 pt-2">
                <Button
                    variant="outline"
                    className="w-full justify-start"
                    onClick={onNewConversation}
                >
                    <PenSquare />
                    Nova conversa
                </Button>
            </div>

            <div className="min-h-0 flex-1 overflow-y-auto px-2 pb-2">
                {loading && conversations.length === 0 ? (
                    <div className="flex flex-col gap-1">
                        {[0, 1, 2, 3, 4].map((index) => (
                            <Skeleton key={index} className="h-9 w-full" />
                        ))}
                    </div>
                ) : conversations.length === 0 ? (
                    <p className="text-muted-foreground px-2 py-6 text-center text-xs">
                        Nenhuma conversa ainda.
                    </p>
                ) : (
                    <>
                        <ul className="flex flex-col gap-0.5">
                            {conversations.map((conversation) => (
                                <li key={conversation.id}>
                                    <button
                                        type="button"
                                        onClick={() => onSelect(conversation)}
                                        title={conversation.title}
                                        className={cn(
                                            'hover:bg-accent hover:text-accent-foreground flex w-full items-start gap-2 rounded-lg px-2 py-2 text-left text-sm transition-colors',
                                            conversation.id === activeId
                                                ? 'bg-accent text-accent-foreground font-medium'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        <MessageSquare className="mt-0.5 size-4 shrink-0" />
                                        <span className="line-clamp-2 min-w-0 wrap-break-word">
                                            {conversation.title}
                                        </span>
                                    </button>
                                </li>
                            ))}
                        </ul>

                        {hasMore && (
                            <div
                                ref={sentinelRef}
                                className="flex flex-col gap-1 pt-1"
                            >
                                <Skeleton className="h-9 w-full" />
                                <Skeleton className="h-9 w-full" />
                            </div>
                        )}
                    </>
                )}
            </div>
        </div>
    );
}
