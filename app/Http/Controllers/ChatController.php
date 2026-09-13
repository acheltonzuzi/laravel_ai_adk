<?php

namespace App\Http\Controllers;

use App\Ai\Agents\Jarvis;
use Generator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Responses\StreamableAgentResponse;
use Laravel\Ai\Streaming\Events\Error;
use Laravel\Ai\Streaming\Events\TextDelta;
use Laravel\Ai\Streaming\Events\ToolApprovalRequest;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ChatController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('chat/chat');
    }

    /**
     * Prompt the agent with the user's message and stream its reply back as server-sent events.
     */
    public function sendMessage(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:4000'],
            'conversation_id' => ['nullable', 'string'],
        ]);

        $user = $request->user();

        $agent = isset($validated['conversation_id'])
            ? (new Jarvis)->continue($this->conversationFor($request, $validated['conversation_id'])->id, $user)
            : (new Jarvis)->forUser($user);

        return $this->streamAgentResponse($agent->stream($validated['message']));
    }

    /**
     * Resume a paused turn with the user's approval decisions and stream what follows.
     */
    public function resolveApproval(Request $request): StreamedResponse
    {
        $validated = $request->validate([
            'conversation_id' => ['required', 'string'],
            'decisions' => ['required', 'array', 'min:1'],
            'decisions.*' => ['required', 'string', 'in:approve,reject'],
        ]);

        $user = $request->user();
        $conversation = $this->conversationFor($request, $validated['conversation_id']);

        $decisions = Decisions::from(array_map(
            fn (string $action): Decision => $action === 'approve'
                ? Decision::approve()
                : Decision::reject('The user rejected this action.'),
            $validated['decisions'],
        ));

        $agent = (new Jarvis)->continue($conversation->id, $user);

        return $this->streamAgentResponse($agent->stream($decisions));
    }

    /**
     * Relay an agent stream to the browser as server-sent events.
     */
    private function streamAgentResponse(StreamableAgentResponse $stream): StreamedResponse
    {
        return response()->stream(function () use ($stream): Generator {
            foreach ($stream as $event) {
                if ($event instanceof TextDelta) {
                    yield $this->serverSentEvent([
                        'type' => 'delta',
                        'delta' => $event->delta,
                    ]);
                }

                if ($event instanceof ToolApprovalRequest) {
                    yield $this->serverSentEvent([
                        'type' => 'approval',
                        'approvals' => $event->pendingApprovals->values()->toArray(),
                    ]);
                }

                if ($event instanceof Error) {
                    yield $this->serverSentEvent([
                        'type' => 'error',
                        'message' => $event->message,
                    ]);
                }
            }

            $conversation = $stream->conversationId === null
                ? null
                : Conversation::find($stream->conversationId);

            yield $this->serverSentEvent([
                'type' => 'done',
                'conversation' => $conversation === null
                    ? null
                    : $this->toConversationPayload($conversation),
            ]);
        }, headers: [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /**
     * List the authenticated user's conversations, most recently updated first.
     */
    public function getConversation(Request $request): JsonResponse
    {
        $conversations = $request->user()
            ->conversations()
            ->latest('updated_at')
            ->paginate(20);

        $conversations->through(
            fn (Conversation $conversation): array => $this->toConversationPayload($conversation)
        );

        return response()->json([
            'conversations' => [
                'data' => $conversations->items(),
                'current_page' => $conversations->currentPage(),
                'last_page' => $conversations->lastPage(),
            ],
        ]);
    }

    /**
     * Get the stored messages of one of the authenticated user's conversations.
     */
    public function getConversationMessages(Request $request, string $conversation): JsonResponse
    {
        $conversation = $this->conversationFor($request, $conversation);

        $messages = $conversation->messages()
            ->whereIn('role', ['user', 'assistant'])
            ->where('content', '!=', '')
            ->oldest('created_at')
            ->get()
            ->map(fn ($message): array => [
                'id' => $message->id,
                'role' => $message->role,
                'content' => $message->content,
            ]);

        return response()->json([
            'conversation' => $this->toConversationPayload($conversation),
            'messages' => $messages,
        ]);
    }

    /**
     * Encode a payload as a single server-sent event frame.
     *
     * @param  array<string, mixed>  $payload
     */
    private function serverSentEvent(array $payload): string
    {
        return 'data: '.json_encode($payload)."\n\n";
    }

    /**
     * Resolve a conversation that belongs to the authenticated user.
     */
    private function conversationFor(Request $request, string $conversationId): Conversation
    {
        return $request->user()
            ->conversations()
            ->findOrFail($conversationId);
    }

    /**
     * @return array{id: string, title: string, updated_at: string|null}
     */
    private function toConversationPayload(Conversation $conversation): array
    {
        return [
            'id' => $conversation->id,
            'title' => $conversation->title,
            'updated_at' => $conversation->updated_at?->toIso8601String(),
        ];
    }
}
