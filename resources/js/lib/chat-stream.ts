import type { Conversation, PendingApproval } from '@/types/chat';

type StreamEvent =
    | { type: 'delta'; delta: string }
    | { type: 'approval'; approvals: PendingApproval[] }
    | { type: 'error'; message: string }
    | { type: 'done'; conversation: Conversation | null };

type StreamOptions = {
    url: string;
    body: Record<string, unknown>;
    signal: AbortSignal;
    onDelta: (delta: string) => void;
    onApproval: (approvals: PendingApproval[]) => void;
    onDone: (conversation: Conversation | null) => void;
};

/**
 * Read the CSRF token Laravel sets on every response.
 */
function csrfToken(): string {
    const cookie = document.cookie
        .split('; ')
        .find((entry) => entry.startsWith('XSRF-TOKEN='));

    return cookie ? decodeURIComponent(cookie.split('=')[1]) : '';
}

/**
 * Post to a chat endpoint and consume the agent reply as server-sent events.
 */
export async function streamChatMessage({
    url,
    body,
    signal,
    onDelta,
    onApproval,
    onDone,
}: StreamOptions): Promise<void> {
    const response = await fetch(url, {
        method: 'POST',
        signal,
        headers: {
            'Content-Type': 'application/json',
            Accept: 'text/event-stream',
            'X-Requested-With': 'XMLHttpRequest',
            'X-XSRF-TOKEN': csrfToken(),
        },
        body: JSON.stringify(body),
    });

    if (!response.ok || !response.body) {
        throw new Error(`A requisição falhou com o status ${response.status}.`);
    }

    const reader = response.body.getReader();
    const decoder = new TextDecoder();
    let buffer = '';

    for (;;) {
        const { done, value } = await reader.read();

        if (done) {
            break;
        }

        buffer += decoder.decode(value, { stream: true });

        const frames = buffer.split('\n\n');
        buffer = frames.pop() ?? '';

        for (const frame of frames) {
            const line = frame.trim();

            if (!line.startsWith('data: ')) {
                continue;
            }

            const event = JSON.parse(line.slice(6)) as StreamEvent;

            if (event.type === 'delta') {
                onDelta(event.delta);
            }

            if (event.type === 'approval') {
                onApproval(event.approvals);
            }

            if (event.type === 'error') {
                throw new Error(event.message);
            }

            if (event.type === 'done') {
                onDone(event.conversation);
            }
        }
    }
}
