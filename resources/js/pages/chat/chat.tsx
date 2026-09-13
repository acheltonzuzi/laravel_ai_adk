import { Head, useHttp, usePage } from '@inertiajs/react';
import { ArrowUp, PanelLeft, Sparkles, Square } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import {
    getConversation,
    getConversationMessages,
    resolveApproval as resolveApprovalRoute,
    sendMessage as sendMessageRoute,
} from '@/actions/App/Http/Controllers/ChatController';
import { ApprovalRequest } from '@/components/chat/approval-request';
import { ConversationList } from '@/components/chat/conversation-list';
import { MarkdownMessage } from '@/components/chat/markdown-message';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { streamChatMessage } from '@/lib/chat-stream';
import { cn } from '@/lib/utils';
import type {
    ApprovalAction,
    ChatMessage,
    Conversation,
    PendingApproval,
} from '@/types/chat';

const suggestions = [
    'Resuma este texto em 3 tópicos',
    'Escreva um e-mail de follow-up',
    'Explique um conceito de forma simples',
    'Gere ideias para um post',
    'Me conte uma história',
];

const failureMessage = 'Não consegui responder agora. Tente novamente.';

export default function Chat() {
    const { auth } = usePage().props;
    const [conversations, setConversations] = useState<Conversation[]>([]);
    const [nextPage, setNextPage] = useState<number | null>(null);
    const [activeId, setActiveId] = useState<string | null>(null);
    const [messages, setMessages] = useState<ChatMessage[]>([]);
    const [draft, setDraft] = useState('');
    const [approvals, setApprovals] = useState<PendingApproval[]>([]);
    const [approvalDecision, setApprovalDecision] =
        useState<ApprovalAction | null>(null);
    /** Id of the paused assistant turn the approval card belongs to. */
    const [approvalAnchorId, setApprovalAnchorId] = useState<string | null>(
        null,
    );
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const [streaming, setStreaming] = useState(false);
    const textareaRef = useRef<HTMLTextAreaElement>(null);
    const bottomRef = useRef<HTMLDivElement>(null);
    const abortRef = useRef<AbortController | null>(null);
    const loadingPageRef = useRef<number | null>(null);

    const listRequest = useHttp<
        Record<string, never>,
        {
            conversations: {
                data: Conversation[];
                current_page: number;
                last_page: number;
            };
        }
    >({});

    const historyRequest = useHttp<
        Record<string, never>,
        { conversation: Conversation; messages: ChatMessage[] }
    >({});

    /**
     * Append one page of conversations, keeping track of whether more remain.
     */
    const loadConversations = useCallback(
        (page: number) => {
            if (loadingPageRef.current === page) {
                return;
            }

            loadingPageRef.current = page;

            void listRequest
                .get(getConversation.url({ query: { page } }))
                .then(({ conversations: loaded }) => {
                    setConversations((current) => {
                        const seen = new Set(current.map((item) => item.id));

                        return page === 1
                            ? loaded.data
                            : [
                                  ...current,
                                  ...loaded.data.filter(
                                      (item) => !seen.has(item.id),
                                  ),
                              ];
                    });

                    setNextPage(
                        loaded.current_page < loaded.last_page
                            ? loaded.current_page + 1
                            : null,
                    );
                })
                .catch(() => {
                    setNextPage(null);
                })
                .finally(() => {
                    loadingPageRef.current = null;
                });
        },
        // eslint-disable-next-line react-hooks/exhaustive-deps
        [],
    );

    useEffect(() => {
        loadConversations(1);
    }, [loadConversations]);

    const loadMoreConversations = useCallback(() => {
        if (nextPage !== null) {
            loadConversations(nextPage);
        }
    }, [nextPage, loadConversations]);

    useEffect(() => {
        bottomRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, streaming]);

    const resizeTextarea = () => {
        const textarea = textareaRef.current;

        if (!textarea) {
            return;
        }

        textarea.style.height = 'auto';
        textarea.style.height = `${Math.min(textarea.scrollHeight, 200)}px`;
    };

    const appendMessage = (role: ChatMessage['role'], content: string) => {
        const id = crypto.randomUUID();

        setMessages((current) => [...current, { id, role, content }]);

        return id;
    };

    const updateMessage = (id: string, update: (content: string) => string) => {
        setMessages((current) =>
            current.map((message) =>
                message.id === id
                    ? { ...message, content: update(message.content) }
                    : message,
            ),
        );
    };

    const stopStreaming = () => {
        abortRef.current?.abort();
        abortRef.current = null;
        setStreaming(false);
    };

    /**
     * Move the conversation to the top of the list, adding it when it is new.
     */
    const upsertConversation = (conversation: Conversation) => {
        setConversations((current) => [
            conversation,
            ...current.filter((item) => item.id !== conversation.id),
        ]);
    };

    const clearApprovals = () => {
        setApprovals([]);
        setApprovalDecision(null);
        setApprovalAnchorId(null);
    };

    const startNewConversation = () => {
        stopStreaming();
        historyRequest.cancel();
        setActiveId(null);
        setMessages([]);
        setDraft('');
        clearApprovals();
        setSidebarOpen(false);
    };

    const openConversation = (conversation: Conversation) => {
        stopStreaming();
        setActiveId(conversation.id);
        setMessages([]);
        clearApprovals();
        setSidebarOpen(false);

        void historyRequest
            .get(getConversationMessages.url({ conversation: conversation.id }))
            .then((response) => {
                setMessages(response.messages);
            })
            .catch(() => {
                setMessages([]);
            });
    };

    /**
     * Stream one agent turn into a fresh assistant bubble.
     */
    const runStream = async (url: string, body: Record<string, unknown>) => {
        const replyId = appendMessage('assistant', '');
        const controller = new AbortController();

        abortRef.current = controller;
        setStreaming(true);

        try {
            await streamChatMessage({
                url,
                body,
                signal: controller.signal,
                onDelta: (delta) => {
                    updateMessage(replyId, (current) => current + delta);
                },
                onApproval: (pending) => {
                    setApprovals(pending);
                    setApprovalDecision(null);
                    setApprovalAnchorId(replyId);
                },
                onDone: (conversation) => {
                    if (conversation) {
                        setActiveId(conversation.id);
                        upsertConversation(conversation);
                    }
                },
            });
        } catch (error) {
            if (!controller.signal.aborted) {
                updateMessage(replyId, (current) =>
                    current === '' ? failureMessage : current,
                );

                console.error(error);
            }
        } finally {
            if (abortRef.current === controller) {
                abortRef.current = null;
                setStreaming(false);
            }
        }
    };

    const sendMessage = async (content: string) => {
        const trimmed = content.trim();

        if (trimmed === '' || streaming) {
            return;
        }

        appendMessage('user', trimmed);
        setDraft('');
        clearApprovals();
        requestAnimationFrame(resizeTextarea);

        await runStream(sendMessageRoute.url(), {
            message: trimmed,
            conversation_id: activeId,
        });
    };

    /**
     * Send the user's decision for every pending tool call and stream what follows.
     */
    const decideApproval = async (action: ApprovalAction) => {
        if (
            approvals.length === 0 ||
            approvalDecision !== null ||
            activeId === null ||
            streaming
        ) {
            return;
        }

        const decisions = Object.fromEntries(
            approvals.map((approval) => [approval.id, action]),
        );

        setApprovalDecision(action);

        await runStream(resolveApprovalRoute.url(), {
            conversation_id: activeId,
            decisions,
        });
    };

    const showsApproval = (message: ChatMessage) =>
        approvals.length > 0 && message.id === approvalAnchorId;

    const visibleMessages = messages.filter(
        (message) => message.content !== '' || showsApproval(message),
    );
    const awaitingReply =
        historyRequest.processing ||
        (streaming && messages.at(-1)?.content === '');

    const conversationList = (
        <ConversationList
            conversations={conversations}
            activeId={activeId}
            loading={listRequest.processing}
            hasMore={nextPage !== null}
            onSelect={openConversation}
            onNewConversation={startNewConversation}
            onLoadMore={loadMoreConversations}
        />
    );

    return (
        <>
            <Head title="Chat" />

            <div className="flex min-h-0 flex-1 overflow-hidden">
                <aside className="border-sidebar-border/70 hidden w-72 shrink-0 overflow-hidden border-r md:flex md:flex-col">
                    {conversationList}
                </aside>

                <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                    <div className="flex shrink-0 items-center gap-2 px-4 pt-3 md:hidden">
                        <Sheet open={sidebarOpen} onOpenChange={setSidebarOpen}>
                            <SheetTrigger asChild>
                                <Button variant="outline" size="sm">
                                    <PanelLeft />
                                    Conversas
                                </Button>
                            </SheetTrigger>
                            <SheetContent side="left" className="w-72 p-0">
                                <SheetTitle className="px-4 pt-4 text-sm">
                                    Conversas
                                </SheetTitle>
                                {conversationList}
                            </SheetContent>
                        </Sheet>
                    </div>

                    <div className="min-h-0 flex-1 overflow-y-auto">
                        <div className="mx-auto w-full max-w-3xl px-4 py-6">
                            {visibleMessages.length === 0 &&
                            approvals.length === 0 &&
                            !awaitingReply ? (
                                <EmptyState
                                    onPick={(value) => void sendMessage(value)}
                                />
                            ) : (
                                <div className="flex flex-col gap-6">
                                    {visibleMessages.map((message) =>
                                        showsApproval(message) ? (
                                            <ApprovalRequest
                                                key={message.id}
                                                approvals={approvals}
                                                processing={streaming}
                                                resolved={approvalDecision}
                                                onDecide={(action) =>
                                                    void decideApproval(action)
                                                }
                                            />
                                        ) : (
                                            <MessageBubble
                                                key={message.id}
                                                message={message}
                                                userName={auth.user.name}
                                                userAvatar={auth.user.avatar}
                                            />
                                        ),
                                    )}

                                    {awaitingReply && <TypingIndicator />}
                                </div>
                            )}

                            <div ref={bottomRef} />
                        </div>
                    </div>

                    <div className="bg-background shrink-0 px-4 pt-2 pb-4">
                        <form
                            onSubmit={(event) => {
                                event.preventDefault();
                                void sendMessage(draft);
                            }}
                            className="mx-auto w-full max-w-3xl"
                        >
                            <div className="border-input bg-card focus-within:border-ring flex items-end gap-2 rounded-2xl border p-2 shadow-xs transition-colors">
                                <textarea
                                    ref={textareaRef}
                                    value={draft}
                                    rows={1}
                                    placeholder="Envie uma mensagem..."
                                    onChange={(event) => {
                                        setDraft(event.target.value);
                                        resizeTextarea();
                                    }}
                                    onKeyDown={(event) => {
                                        if (
                                            event.key === 'Enter' &&
                                            !event.shiftKey
                                        ) {
                                            event.preventDefault();
                                            void sendMessage(draft);
                                        }
                                    }}
                                    className="placeholder:text-muted-foreground max-h-50 flex-1 resize-none bg-transparent px-2 py-2 text-sm outline-none"
                                />

                                <Button
                                    type={streaming ? 'button' : 'submit'}
                                    size="icon"
                                    className="size-9 rounded-xl"
                                    disabled={!streaming && draft.trim() === ''}
                                    onClick={() => {
                                        if (streaming) {
                                            stopStreaming();
                                        }
                                    }}
                                    aria-label={
                                        streaming
                                            ? 'Parar resposta'
                                            : 'Enviar mensagem'
                                    }
                                >
                                    {streaming ? (
                                        <Square className="fill-current" />
                                    ) : (
                                        <ArrowUp />
                                    )}
                                </Button>
                            </div>

                            <p className="text-muted-foreground mt-2 text-center text-xs">
                                Enter para enviar &middot; Shift + Enter para
                                quebrar linha
                            </p>
                        </form>
                    </div>
                </div>
            </div>
        </>
    );
}

function EmptyState({ onPick }: { onPick: (value: string) => void }) {
    return (
        <div className="flex flex-col items-center gap-6 py-16 text-center">
            <div className="border-border bg-card flex size-12 items-center justify-center rounded-2xl border">
                <Sparkles className="text-muted-foreground size-5" />
            </div>

            <div className="space-y-1">
                <h1 className="text-2xl font-semibold tracking-tight">
                    Como posso ajudar hoje?
                </h1>
                <p className="text-muted-foreground text-sm">
                    Escolha uma sugestão ou escreva sua própria pergunta.
                </p>
            </div>

            <div className="grid w-full gap-2 sm:grid-cols-2">
                {suggestions.map((suggestion) => (
                    <button
                        key={suggestion}
                        type="button"
                        onClick={() => onPick(suggestion)}
                        className="border-border bg-card text-muted-foreground hover:bg-accent hover:text-accent-foreground rounded-xl border px-4 py-3 text-left text-sm transition-colors"
                    >
                        {suggestion}
                    </button>
                ))}
            </div>
        </div>
    );
}

function MessageBubble({
    message,
    userName,
    userAvatar,
}: {
    message: ChatMessage;
    userName: string;
    userAvatar?: string;
}) {
    const isUser = message.role === 'user';

    return (
        <div
            className={cn(
                'flex gap-3',
                isUser ? 'justify-end' : 'justify-start',
            )}
        >
            {!isUser && <AssistantAvatar />}

            {isUser ? (
                <div className="bg-primary text-primary-foreground max-w-[80%] rounded-2xl rounded-br-sm px-4 py-2.5 text-sm leading-relaxed whitespace-pre-wrap">
                    {message.content}
                </div>
            ) : (
                <MarkdownMessage
                    content={message.content}
                    className="max-w-[80%] min-w-0"
                />
            )}

            {isUser && (
                <Avatar className="mt-0.5 size-8 shrink-0">
                    <AvatarImage src={userAvatar} alt={userName} />
                    <AvatarFallback className="text-xs">
                        {userName.charAt(0).toUpperCase()}
                    </AvatarFallback>
                </Avatar>
            )}
        </div>
    );
}

function AssistantAvatar() {
    return (
        <div className="border-border bg-card mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full border">
            <Sparkles className="text-muted-foreground size-4" />
        </div>
    );
}

function TypingIndicator() {
    return (
        <div className="flex gap-3">
            <AssistantAvatar />
            <div className="flex items-center gap-1 py-3">
                {[0, 150, 300].map((delay) => (
                    <span
                        key={delay}
                        className="bg-muted-foreground/60 size-1.5 animate-bounce rounded-full"
                        style={{ animationDelay: `${delay}ms` }}
                    />
                ))}
            </div>
        </div>
    );
}

Chat.layout = {
    fullHeight: true,
    breadcrumbs: [
        {
            title: 'Chat',
            href: '/chat',
        },
    ],
};
