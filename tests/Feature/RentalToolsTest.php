<?php

use App\Ai\Tools\AddRental;
use App\Ai\Tools\DeleteRental;
use App\Ai\Tools\EditRental;
use App\Ai\Tools\SearchRental;
use App\Enums\CarAvailability;
use App\Enums\RentalStatus;
use App\Models\Car;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

test('every rental tool exposes exactly the arguments it uses', function () {
    expect(array_keys(toolSchema(new SearchRental)['properties']))
        ->toBe(['car_id', 'user_id', 'status', 'limit'])
        ->and(toolSchema(new SearchRental)['required'] ?? [])->toBe([])
        ->and(array_keys(toolSchema(new AddRental)['properties']))
        ->toBe(['user_id', 'car_id', 'start_date', 'end_date', 'daily_rate'])
        ->and(toolSchema(new AddRental)['required'])
        ->toBe(['user_id', 'car_id', 'start_date', 'end_date'])
        ->and(array_keys(toolSchema(new EditRental)['properties']))
        ->toBe(['id', 'start_date', 'end_date', 'daily_rate', 'status'])
        ->and(toolSchema(new EditRental)['required'])->toBe(['id'])
        ->and(array_keys(toolSchema(new DeleteRental)['properties']))->toBe(['id'])
        ->and(toolSchema(new DeleteRental)['required'])->toBe(['id']);
});

test('the search tool has no approval requirement', function () {
    expect(new SearchRental)->not->toBeInstanceOf(Approvable::class);
});

test('the add tool books an available car and marks it rented', function () {
    $car = Car::factory()->create(['availability' => CarAvailability::Disponivel, 'price' => null]);
    $user = User::factory()->create();

    $result = runTool(new AddRental, [
        'user_id' => $user->id,
        'car_id' => $car->id,
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-05',
        'daily_rate' => 40000,
    ]);

    expect($result['days'])->toBe(5)
        ->and($result['total'])->toEqual(200000)
        ->and($result['status'])->toBe('pendente')
        ->and($car->refresh()->availability)->toBe(CarAvailability::Alugado);
});

test('the add tool falls back to the car price when daily_rate is omitted', function () {
    $car = Car::factory()->create(['availability' => CarAvailability::Disponivel, 'price' => 55000]);
    $user = User::factory()->create();

    $result = runTool(new AddRental, [
        'user_id' => $user->id,
        'car_id' => $car->id,
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-01',
    ]);

    expect(Rental::firstOrFail()->daily_rate)->toBe('55000.00')
        ->and($result['days'])->toBe(1);
});

test('the add tool errors instead of booking with no rate at all', function () {
    $car = Car::factory()->create(['availability' => CarAvailability::Disponivel, 'price' => null]);
    $user = User::factory()->create();

    $result = runTool(new AddRental, [
        'user_id' => $user->id,
        'car_id' => $car->id,
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-05',
    ]);

    expect($result)->toHaveKey('error')
        ->and(Rental::count())->toBe(0);
});

test('the add tool refuses a car that is not available', function () {
    $car = Car::factory()->create(['availability' => CarAvailability::Manutencao]);
    $user = User::factory()->create();

    $result = runTool(new AddRental, [
        'user_id' => $user->id,
        'car_id' => $car->id,
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-05',
        'daily_rate' => 40000,
    ]);

    expect($result)->toHaveKey('error')
        ->and(Rental::count())->toBe(0);
});

test('the add tool refuses an overlapping period', function () {
    $car = Car::factory()->create(['availability' => CarAvailability::Disponivel]);
    $user = User::factory()->create();

    Rental::factory()->create([
        'car_id' => $car->id,
        'start_date' => '2026-12-01',
        'end_date' => '2026-12-10',
        'status' => RentalStatus::Confirmado,
    ]);

    $result = runTool(new AddRental, [
        'user_id' => $user->id,
        'car_id' => $car->id,
        'start_date' => '2026-12-05',
        'end_date' => '2026-12-15',
        'daily_rate' => 40000,
    ]);

    expect($result)->toHaveKey('error')
        ->and(Rental::count())->toBe(1);
});

test('the edit tool only changes the fields it receives', function () {
    $rental = Rental::factory()->create(['daily_rate' => 30000, 'status' => RentalStatus::Confirmado]);
    $originalDates = [$rental->start_date, $rental->end_date];

    runTool(new EditRental, ['id' => $rental->id, 'daily_rate' => 50000]);

    $rental->refresh();

    expect((float) $rental->daily_rate)->toBe(50000.0)
        ->and($rental->start_date->equalTo($originalDates[0]))->toBeTrue()
        ->and($rental->end_date->equalTo($originalDates[1]))->toBeTrue();
});

test('the edit tool validates end_date against the persisted start_date when only end_date is sent', function () {
    $rental = Rental::factory()->create([
        'start_date' => '2026-12-10',
        'end_date' => '2026-12-15',
        'status' => RentalStatus::Confirmado,
    ]);

    $result = runTool(new EditRental, ['id' => $rental->id, 'end_date' => '2026-12-01']);

    expect($result)->toHaveKey('error');

    expect($rental->refresh()->end_date->toDateString())->toBe('2026-12-15');
});

test('concluding a rental through the tool frees the car', function () {
    $car = Car::factory()->create(['availability' => CarAvailability::Alugado]);
    $rental = Rental::factory()->create(['car_id' => $car->id, 'status' => RentalStatus::EmAndamento]);

    runTool(new EditRental, ['id' => $rental->id, 'status' => 'concluido']);

    expect($car->refresh()->availability)->toBe(CarAvailability::Disponivel);
});

test('reopening a rental through the tool requires the car to be available', function () {
    $car = Car::factory()->create(['availability' => CarAvailability::Vendido]);
    $rental = Rental::factory()->create(['car_id' => $car->id, 'status' => RentalStatus::Cancelado]);

    $result = runTool(new EditRental, ['id' => $rental->id, 'status' => 'confirmado']);

    expect($result)->toHaveKey('error')
        ->and($rental->refresh()->status)->toBe(RentalStatus::Cancelado);
});

test('the delete tool frees the car when the rental was active', function () {
    $car = Car::factory()->create(['availability' => CarAvailability::Alugado]);
    $rental = Rental::factory()->create(['car_id' => $car->id, 'status' => RentalStatus::Confirmado]);

    $result = runTool(new DeleteRental, ['id' => $rental->id]);

    expect($result['deleted']['id'])->toBe($rental->id)
        ->and(Rental::find($rental->id))->toBeNull()
        ->and($car->refresh()->availability)->toBe(CarAvailability::Disponivel);
});

test('the rental tools report an unknown id instead of failing', function () {
    expect(runTool(new EditRental, ['id' => 999, 'daily_rate' => 1000]))->toHaveKey('error')
        ->and(runTool(new DeleteRental, ['id' => 999]))->toHaveKey('error');
});

test('the mutating rental tools require approval', function () {
    $car = Car::factory()->create(['availability' => CarAvailability::Disponivel]);
    $user = User::factory()->create(['name' => 'Ana Souza']);
    $rental = Rental::factory()->create(['car_id' => $car->id]);

    $add = (new AddRental)->shouldRequestApproval(new Request([
        'user_id' => $user->id, 'car_id' => $car->id,
        'start_date' => '2026-12-01', 'end_date' => '2026-12-05',
    ]));
    $edit = (new EditRental)->shouldRequestApproval(new Request([
        'id' => $rental->id, 'status' => 'cancelado',
    ]));
    $delete = (new DeleteRental)->shouldRequestApproval(new Request(['id' => $rental->id]));

    expect($add)->not->toBeNull()
        ->and($add->reason)->toContain('Ana Souza')
        ->and($edit)->not->toBeNull()
        ->and($edit->reason)->toContain('status')
        ->and($delete)->not->toBeNull()
        ->and($delete->reason)->toContain((string) $rental->id);
});

test('the add tool validates that end_date is on or after start_date', function () {
    $car = Car::factory()->create(['availability' => CarAvailability::Disponivel]);
    $user = User::factory()->create();

    expect(fn () => runTool(new AddRental, [
        'user_id' => $user->id,
        'car_id' => $car->id,
        'start_date' => '2026-12-10',
        'end_date' => '2026-12-01',
        'daily_rate' => 1000,
    ]))->toThrow(ValidationException::class);

    expect(Rental::count())->toBe(0);
});
