<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

test('guests cannot reach the user management page', function () {
    $this->get(route('users.index'))->assertRedirect(route('login'));
});

test('the page lists the users', function () {
    $user = User::factory()->create(['name' => 'Ana Souza']);

    $this->actingAs($user)
        ->get(route('users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('users/index')
            ->has('users.data', 1)
            ->where('users.data.0.name', 'Ana Souza')
        );
});

test('the list can be filtered by name or email', function () {
    $actor = User::factory()->create(['name' => 'Zeca', 'email' => 'zeca@example.com']);
    User::factory()->create(['name' => 'Ana Souza', 'email' => 'ana@example.com']);

    $this->actingAs($actor)
        ->get(route('users.index', ['search' => 'ana@']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('users.data', 1)
            ->where('users.data.0.email', 'ana@example.com')
        );
});

test('a user can be created', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('users.store'), [
        'name' => 'Ana Souza',
        'email' => 'ana@example.com',
        'password' => 'senha-bem-segura',
        'password_confirmation' => 'senha-bem-segura',
    ])->assertRedirect(route('users.index'));

    $created = User::where('email', 'ana@example.com')->firstOrFail();

    expect($created->name)->toBe('Ana Souza')
        ->and(Hash::check('senha-bem-segura', $created->password))->toBeTrue();
});

test('creating a user requires a unique email', function () {
    $this->actingAs(User::factory()->create(['email' => 'ana@example.com']));

    $this->post(route('users.store'), [
        'name' => 'Outra Ana',
        'email' => 'ana@example.com',
        'password' => 'senha-bem-segura',
        'password_confirmation' => 'senha-bem-segura',
    ])->assertSessionHasErrors('email');

    expect(User::where('email', 'ana@example.com')->count())->toBe(1);
});

test('a user can be updated without touching the password', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create(['name' => 'Ana', 'email' => 'ana@example.com']);
    $originalPassword = $target->password;

    $this->actingAs($actor)
        ->put(route('users.update', $target), [
            'name' => 'Ana Souza',
            'email' => 'ana@example.com',
            'password' => '',
        ])->assertRedirect(route('users.index'));

    $target->refresh();

    expect($target->name)->toBe('Ana Souza')
        ->and($target->password)->toBe($originalPassword);
});

test('updating a user password replaces it', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($actor)
        ->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => $target->email,
            'password' => 'nova-senha-segura',
            'password_confirmation' => 'nova-senha-segura',
        ])->assertRedirect(route('users.index'));

    expect(Hash::check('nova-senha-segura', $target->refresh()->password))->toBeTrue();
});

test('changing the email clears the verification', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create(['email_verified_at' => now()]);

    $this->actingAs($actor)
        ->put(route('users.update', $target), [
            'name' => $target->name,
            'email' => 'novo@example.com',
            'password' => '',
        ]);

    expect($target->refresh()->email_verified_at)->toBeNull();
});

test('a user keeps its own email when updating', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create(['email' => 'ana@example.com']);

    $this->actingAs($actor)
        ->put(route('users.update', $target), [
            'name' => 'Ana Souza',
            'email' => 'ana@example.com',
            'password' => '',
        ])->assertSessionHasNoErrors();
});

test('a user can be deleted', function () {
    $actor = User::factory()->create();
    $target = User::factory()->create();

    $this->actingAs($actor)
        ->delete(route('users.destroy', $target))
        ->assertRedirect(route('users.index'));

    expect(User::find($target->id))->toBeNull();
});

test('the signed in user cannot delete itself from this page', function () {
    $actor = User::factory()->create();

    $this->actingAs($actor)
        ->delete(route('users.destroy', $actor))
        ->assertSessionHasErrors('user');

    expect(User::find($actor->id))->not->toBeNull();
});
