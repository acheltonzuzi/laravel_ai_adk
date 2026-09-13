<?php

use App\Ai\Tools\DeleteUser;
use App\Ai\Tools\EditUser;
use App\Ai\Tools\SearchUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

test('every user tool exposes exactly the arguments it uses', function () {
    expect(array_keys(toolSchema(new SearchUser)['properties']))->toBe(['query', 'limit'])
        ->and(toolSchema(new SearchUser)['required'])->toBe(['query'])
        ->and(array_keys(toolSchema(new EditUser)['properties']))->toBe(['id', 'name', 'email', 'password'])
        ->and(toolSchema(new EditUser)['required'])->toBe(['id'])
        ->and(array_keys(toolSchema(new DeleteUser)['properties']))->toBe(['id'])
        ->and(toolSchema(new DeleteUser)['required'])->toBe(['id']);
});

test('the search tool matches name and email', function () {
    $ana = User::factory()->create(['name' => 'Ana Souza', 'email' => 'ana@example.com']);
    User::factory()->create(['name' => 'Bruno Lima', 'email' => 'bruno@example.com']);

    expect(runTool(new SearchUser, ['query' => 'Souza']))->toHaveCount(1)
        ->and(runTool(new SearchUser, ['query' => 'Souza'])[0]['id'])->toBe($ana->id)
        ->and(runTool(new SearchUser, ['query' => 'bruno@']))->toHaveCount(1)
        ->and(runTool(new SearchUser, ['query' => 'example.com']))->toHaveCount(2);
});

test('the search tool honours the limit', function () {
    User::factory()->count(5)->create(['name' => 'Equipe']);

    expect(runTool(new SearchUser, ['query' => 'Equipe']))->toHaveCount(5)
        ->and(runTool(new SearchUser, ['query' => 'Equipe', 'limit' => 2]))->toHaveCount(2);
});

test('the search tool rejects a limit above the cap', function () {
    expect(fn () => runTool(new SearchUser, ['query' => 'Ana', 'limit' => 500]))
        ->toThrow(ValidationException::class);
});

test('the edit tool only changes the fields it receives', function () {
    $user = User::factory()->create(['name' => 'Ana', 'email' => 'ana@example.com']);
    $originalPassword = $user->password;

    $result = runTool(new EditUser, ['id' => $user->id, 'name' => 'Ana Souza']);

    $user->refresh();

    expect($result['name'])->toBe('Ana Souza')
        ->and($user->name)->toBe('Ana Souza')
        ->and($user->email)->toBe('ana@example.com')
        ->and($user->password)->toBe($originalPassword);
});

test('the edit tool replaces the password and clears verification on email change', function () {
    $user = User::factory()->create(['email_verified_at' => now()]);

    runTool(new EditUser, [
        'id' => $user->id,
        'email' => 'novo@example.com',
        'password' => 'senha-bem-segura',
    ]);

    $user->refresh();

    expect($user->email)->toBe('novo@example.com')
        ->and($user->email_verified_at)->toBeNull()
        ->and(Hash::check('senha-bem-segura', $user->password))->toBeTrue();
});

test('the edit tool rejects an email already taken by someone else', function () {
    $ana = User::factory()->create(['email' => 'ana@example.com']);
    User::factory()->create(['email' => 'bruno@example.com']);

    expect(fn () => runTool(new EditUser, ['id' => $ana->id, 'email' => 'bruno@example.com']))
        ->toThrow(ValidationException::class);

    expect($ana->refresh()->email)->toBe('ana@example.com');
});

test('the edit tool accepts the user keeping its own email', function () {
    $ana = User::factory()->create(['email' => 'ana@example.com']);

    runTool(new EditUser, ['id' => $ana->id, 'name' => 'Ana Souza', 'email' => 'ana@example.com']);

    expect($ana->refresh()->name)->toBe('Ana Souza');
});

test('the edit tool reports an unknown user instead of failing', function () {
    expect(runTool(new EditUser, ['id' => 999, 'name' => 'Fantasma']))
        ->toHaveKey('error');
});

test('the delete tool removes the user', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($actor);

    $result = runTool(new DeleteUser, ['id' => $target->id]);

    expect($result['deleted']['id'])->toBe($target->id)
        ->and(User::find($target->id))->toBeNull();
});

test('the delete tool refuses to delete the signed in user', function () {
    $actor = User::factory()->create();

    $this->actingAs($actor);

    expect(runTool(new DeleteUser, ['id' => $actor->id]))->toHaveKey('error')
        ->and(User::find($actor->id))->not->toBeNull();
});

test('the delete tool reports an unknown user instead of failing', function () {
    expect(runTool(new DeleteUser, ['id' => 999]))->toHaveKey('error');
});

test('the mutating user tools require approval', function () {
    $user = User::factory()->create();

    $edit = (new EditUser)->shouldRequestApproval(new Request(['id' => $user->id, 'name' => 'Nova']));
    $delete = (new DeleteUser)->shouldRequestApproval(new Request(['id' => $user->id]));

    expect($edit)->not->toBeNull()
        ->and($edit->reason)->toContain('name')
        ->and($delete)->not->toBeNull()
        ->and($delete->reason)->toContain($user->email);
});
