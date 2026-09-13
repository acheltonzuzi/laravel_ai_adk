<?php

use App\Ai\Tools\AddUser;
use App\Ai\Tools\GetUsers;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\ObjectSchema;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

/**
 * Build the JSON schema the agent actually exposes to the model.
 *
 * @return array<string, mixed>
 */
function exposedSchema(object $tool): array
{
    return (new ObjectSchema($tool->schema(new JsonSchemaTypeFactory)))->toArray();
}

test('the add user tool exposes every argument it validates', function () {
    $schema = exposedSchema(new AddUser);

    expect(array_keys($schema['properties']))->toBe(['name', 'email', 'password'])
        ->and($schema['required'])->toBe(['name', 'email', 'password']);
});

test('the add user tool creates the user', function () {
    $result = (new AddUser)->handle(new Request([
        'name' => 'Ana Souza',
        'email' => 'ana@example.com',
        'password' => 'segredo123',
    ]));

    $user = User::where('email', 'ana@example.com')->firstOrFail();

    expect(json_decode((string) $result, true))
        ->toBe(['id' => $user->id, 'name' => 'Ana Souza', 'email' => 'ana@example.com'])
        ->and(Hash::check('segredo123', $user->password))->toBeTrue();
});

test('the add user tool rejects a duplicated email', function () {
    User::factory()->create(['email' => 'ana@example.com']);

    expect(fn () => (new AddUser)->handle(new Request([
        'name' => 'Ana Souza',
        'email' => 'ana@example.com',
        'password' => 'segredo123',
    ])))->toThrow(ValidationException::class);

    expect(User::where('email', 'ana@example.com')->count())->toBe(1);
});

test('the get users tool takes no arguments', function () {
    $schema = exposedSchema(new GetUsers);

    expect($schema)->not->toHaveKey('required')
        ->and($schema)->not->toHaveKey('properties');
});

test('the get users tool lists the users without leaking credentials', function () {
    $user = User::factory()->create();

    $result = json_decode((string) (new GetUsers)->handle(new Request), true);

    expect($result)->toHaveCount(1)
        ->and($result[0]['id'])->toBe($user->id)
        ->and($result[0]['name'])->toBe($user->name)
        ->and($result[0]['email'])->toBe($user->email)
        ->and($result[0])->not->toHaveKey('password')
        ->and($result[0])->not->toHaveKey('remember_token')
        ->and($result[0])->not->toHaveKey('two_factor_secret')
        ->and($result[0])->not->toHaveKey('two_factor_recovery_codes');
});
