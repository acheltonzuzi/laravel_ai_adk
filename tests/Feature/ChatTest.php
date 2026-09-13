<?php

use App\Ai\Agents\Jarvis;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Laravel\Ai\Approvals\PendingApproval;
use Laravel\Ai\Models\Conversation;
use Laravel\Ai\Responses\AgentResponse;

uses(RefreshDatabase::class);

/**
 * Decode the server-sent events of a streamed chat response.
 *
 * @return list<array<string, mixed>>
 */
function streamedEvents(TestResponse $response): array
{
    return collect(explode("\n\n", trim($response->streamedContent())))
        ->filter(fn (string $frame): bool => str_starts_with(trim($frame), 'data: '))
        ->map(fn (string $frame): array => json_decode(substr(trim($frame), 6), true))
        ->values()
        ->all();
}

/**
 * Join the streamed text deltas into the full reply.
 */
function streamedText(TestResponse $response): string
{
    return collect(streamedEvents($response))
        ->where('type', 'delta')
        ->pluck('delta')
        ->join('');
}

test('guests cannot send a message', function () {
    $response = $this->post(route('chat.send'), ['message' => 'Olá']);

    $response->assertRedirect(route('login'));
});

test('the agent reply is streamed back for the submitted message', function () {
    Jarvis::fake(['Olá! Como posso ajudar?']);

    $this->actingAs(User::factory()->create());

    $response = $this->post(route('chat.send'), ['message' => 'Olá']);

    $response->assertOk()->assertHeader('Content-Type', 'text/event-stream; charset=utf-8');

    expect(streamedText($response))->toBe('Olá! Como posso ajudar?');

    Jarvis::assertPrompted('Olá');
});

test('sending a message stores it in a conversation for the user', function () {
    Jarvis::fake(['Olá! Como posso ajudar?']);

    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->post(route('chat.send'), ['message' => 'Olá']);

    $done = collect(streamedEvents($response))->firstWhere('type', 'done');

    expect($done['conversation'])->toHaveKeys(['id', 'title', 'updated_at'])
        ->and($user->conversations()->count())->toBe(1);
});

test('a follow up message continues the same conversation', function () {
    Jarvis::fake(['Primeira', 'Segunda']);

    $user = User::factory()->create();
    $this->actingAs($user);

    $first = $this->post(route('chat.send'), ['message' => 'Oi']);
    $conversationId = collect(streamedEvents($first))
        ->firstWhere('type', 'done')['conversation']['id'];

    $second = $this->post(route('chat.send'), [
        'message' => 'E aí?',
        'conversation_id' => $conversationId,
    ]);

    $second->assertOk();

    expect(collect(streamedEvents($second))->firstWhere('type', 'done')['conversation']['id'])
        ->toBe($conversationId)
        ->and($user->conversations()->count())->toBe(1);
});

test('the conversation list only contains the user own conversations', function () {
    Jarvis::fake(['Resposta']);

    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($owner)->post(route('chat.send'), ['message' => 'Minha conversa'])->streamedContent();
    $this->actingAs($stranger)->post(route('chat.send'), ['message' => 'Conversa alheia'])->streamedContent();

    $response = $this->actingAs($owner)->getJson(route('chat.conversations'));

    $response->assertOk();

    expect($response->json('conversations.data'))->toHaveCount(1);
});

test('the conversation list is paginated so every conversation is reachable', function () {
    $user = User::factory()->create();

    foreach (range(1, 25) as $index) {
        Conversation::create([
            'id' => (string) Str::uuid(),
            'participant_type' => $user->getMorphClass(),
            'participant_id' => $user->id,
            'title' => "Conversa {$index}",
        ]);
    }

    $this->actingAs($user);

    $first = $this->getJson(route('chat.conversations'));

    $first->assertOk()
        ->assertJsonPath('conversations.current_page', 1)
        ->assertJsonPath('conversations.last_page', 2);

    expect($first->json('conversations.data'))->toHaveCount(20);

    $second = $this->getJson(route('chat.conversations', ['page' => 2]));

    $second->assertOk()->assertJsonPath('conversations.current_page', 2);

    expect($second->json('conversations.data'))->toHaveCount(5);
});

test('the stored messages of a conversation can be listed', function () {
    Jarvis::fake(['Claro que sim!']);

    $user = User::factory()->create();
    $this->actingAs($user);

    $sent = $this->post(route('chat.send'), ['message' => 'Você me ouve?']);
    $conversationId = collect(streamedEvents($sent))
        ->firstWhere('type', 'done')['conversation']['id'];

    $response = $this->getJson(route('chat.conversation.messages', $conversationId));

    $response->assertOk();

    expect($response->json('messages'))->toHaveCount(2)
        ->and($response->json('messages.0.role'))->toBe('user')
        ->and($response->json('messages.0.content'))->toBe('Você me ouve?')
        ->and($response->json('messages.1.role'))->toBe('assistant')
        ->and($response->json('messages.1.content'))->toBe('Claro que sim!');
});

test('another user conversation cannot be read', function () {
    Jarvis::fake(['Resposta']);

    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $sent = $this->actingAs($owner)->post(route('chat.send'), ['message' => 'Segredo']);
    $conversationId = collect(streamedEvents($sent))
        ->firstWhere('type', 'done')['conversation']['id'];

    $this->actingAs($stranger)
        ->getJson(route('chat.conversation.messages', $conversationId))
        ->assertNotFound();

    $this->actingAs($stranger)
        ->postJson(route('chat.send'), [
            'message' => 'Invadindo',
            'conversation_id' => $conversationId,
        ])
        ->assertNotFound();
});

test('a tool that needs approval pauses the turn and is surfaced to the client', function () {
    Jarvis::fake([
        AgentResponse::fakeWithPendingApprovals([
            new PendingApproval('call_1', 'add_user', ['email' => 'ana@example.com'], 'Vai criar um usuário.'),
        ]),
    ]);

    $this->actingAs(User::factory()->create());

    $response = $this->post(route('chat.send'), ['message' => 'cria a usuária Ana']);

    $approval = collect(streamedEvents($response))->firstWhere('type', 'approval');

    expect($approval['approvals'])->toHaveCount(1)
        ->and($approval['approvals'][0]['id'])->toBe('call_1')
        ->and($approval['approvals'][0]['tool'])->toBe('add_user')
        ->and($approval['approvals'][0]['reason'])->toBe('Vai criar um usuário.')
        ->and($approval['approvals'][0]['arguments'])->toBe(['email' => 'ana@example.com']);
});

test('approving a pending tool call resumes the conversation', function () {
    Jarvis::fake([
        AgentResponse::fakeWithPendingApprovals([
            new PendingApproval('call_1', 'add_user', [], null),
        ]),
        'Pronto, usuária criada!',
    ]);

    $this->actingAs(User::factory()->create());

    $paused = $this->post(route('chat.send'), ['message' => 'cria a usuária Ana']);
    $conversationId = collect(streamedEvents($paused))
        ->firstWhere('type', 'done')['conversation']['id'];

    $resumed = $this->post(route('chat.approvals'), [
        'conversation_id' => $conversationId,
        'decisions' => ['call_1' => 'approve'],
    ]);

    $resumed->assertOk();

    expect(streamedText($resumed))->not->toBe('')
        ->and(collect(streamedEvents($resumed))->firstWhere('type', 'done')['conversation']['id'])
        ->toBe($conversationId);
});

test('an approval decision is rejected for another user conversation', function () {
    Jarvis::fake([
        AgentResponse::fakeWithPendingApprovals([
            new PendingApproval('call_1', 'add_user', [], null),
        ]),
    ]);

    $owner = User::factory()->create();
    $stranger = User::factory()->create();

    $paused = $this->actingAs($owner)->post(route('chat.send'), ['message' => 'cria a usuária Ana']);
    $conversationId = collect(streamedEvents($paused))
        ->firstWhere('type', 'done')['conversation']['id'];

    $this->actingAs($stranger)
        ->postJson(route('chat.approvals'), [
            'conversation_id' => $conversationId,
            'decisions' => ['call_1' => 'approve'],
        ])
        ->assertNotFound();
});

test('only approve and reject decisions are accepted', function () {
    $this->actingAs(User::factory()->create());

    $this->postJson(route('chat.approvals'), [
        'conversation_id' => 'whatever',
        'decisions' => ['call_1' => 'delete-everything'],
    ])->assertUnprocessable()->assertJsonValidationErrors('decisions.call_1');
});

test('the message is required', function () {
    Jarvis::fake();

    $this->actingAs(User::factory()->create());

    $response = $this->postJson(route('chat.send'), ['message' => '']);

    $response->assertUnprocessable()->assertJsonValidationErrors('message');

    Jarvis::assertNotPrompted(fn () => true);
});
