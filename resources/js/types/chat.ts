export type Conversation = {
    id: string;
    title: string;
    updated_at: string | null;
};

export type ChatMessage = {
    id: string;
    role: 'user' | 'assistant';
    content: string;
};

export type PendingApproval = {
    id: string;
    tool: string;
    arguments: Record<string, unknown>;
    reason: string | null;
};

export type ApprovalAction = 'approve' | 'reject';
