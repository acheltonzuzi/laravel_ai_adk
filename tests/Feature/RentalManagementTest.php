<?php

use App\Enums\CarAvailability;
use App\Enums\RentalStatus;
use App\Models\Car;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Valid rental payload, overridable per test.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function rentalPayload(Car $car, User $user, array $overrides = []): array
{
    return [
        'user_id' => $user->id,
        'car_id' => $car->id,
        'start_date' => '2026-10-10',
        'end_date' => '2026-10-15',
        'daily_rate' => 45000,
        ...$overrides,
    ];
}

test('guests cannot reach the rental list', function () {
    $this->get(route('rentals.index'))->assertRedirect(route('login'));
});

test('the page lists rentals with a car and user summary', function () {
    $rental = Rental::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('rentals.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('rentals/index')
            ->has('rentals.data', 1)
            ->where('rentals.data.0.car.id', $rental->car_id)
            ->where('rentals.data.0.user.id', $rental->user_id)
            ->has('statuses', 5)
        );
});

test('the list can be filtered by status, car and user', function () {
    $car = Car::factory()->create();
    $user = User::factory()->create();

    $target = Rental::factory()->confirmed()->create(['car_id' => $car->id, 'user_id' => $user->id]);
    Rental::factory()->cancelled()->create();

    $this->actingAs($user)
        ->get(route('rentals.index', ['status' => RentalStatus::Confirmado->value]))
        ->assertInertia(fn ($page) => $page->has('rentals.data', 1));

    $this->actingAs($user)
        ->get(route('rentals.index', ['car_id' => $car->id]))
        ->assertInertia(fn ($page) => $page
            ->has('rentals.data', 1)
            ->where('rentals.data.0.id', $target->id)
        );
});

test('the list can be searched by plate and by renter name', function () {
    $car = Car::factory()->create(['license_plate' => 'AB1234CD']);
    $renter = User::factory()->create(['name' => 'Ana Souza']);
    Rental::factory()->create(['car_id' => $car->id, 'user_id' => $renter->id]);

    Rental::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('rentals.index', ['search' => 'AB1234']))
        ->assertInertia(fn ($page) => $page->has('rentals.data', 1));

    $this->actingAs(User::factory()->create())
        ->get(route('rentals.index', ['search' => 'Ana Souza']))
        ->assertInertia(fn ($page) => $page->has('rentals.data', 1));
});

test('booking a rental calculates days and total, and reserves the car', function () {
    $car = Car::factory()->create(['availability' => CarAvailability::Disponivel]);
    $renter = User::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('rentals.store'), rentalPayload($car, $renter))
        ->assertRedirect(route('rentals.index'));

    $rental = Rental::firstOrFail();

    expect($rental->days)->toBe(6)
        ->and((float) $rental->total)->toBe(270000.0)
        ->and($rental->status)->toBe(RentalStatus::Pendente)
        ->and($car->refresh()->availability)->toBe(CarAvailability::Alugado);
});

test('booking a car that is not available is rejected', function () {
    $car = Car::factory()->create(['availability' => CarAvailability::Manutencao]);
    $renter = User::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('rentals.store'), rentalPayload($car, $renter))
        ->assertSessionHasErrors('car_id');

    expect(Rental::count())->toBe(0);
});

test('an overlapping period on the same car is rejected', function () {
    $car = Car::factory()->create(['availability' => CarAvailability::Disponivel]);
    $renter = User::factory()->create();

    Rental::factory()->create([
        'car_id' => $car->id,
        'start_date' => '2026-10-10',
        'end_date' => '2026-10-15',
        'status' => RentalStatus::Confirmado,
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('rentals.store'), rentalPayload($car, $renter, [
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-20',
        ]))
        ->assertSessionHasErrors('car_id');

    expect(Rental::count())->toBe(1);
});

test('an overlapping period on a cancelled rental is accepted', function () {
    $car = Car::factory()->create(['availability' => CarAvailability::Disponivel]);
    $renter = User::factory()->create();

    Rental::factory()->cancelled()->create([
        'car_id' => $car->id,
        'start_date' => '2026-10-10',
        'end_date' => '2026-10-15',
    ]);

    $this->actingAs(User::factory()->create())
        ->post(route('rentals.store'), rentalPayload($car, $renter, [
            'start_date' => '2026-10-12',
            'end_date' => '2026-10-20',
        ]))
        ->assertSessionHasNoErrors();

    expect(Rental::where('status', RentalStatus::Pendente)->count())->toBe(1);
});

test('end_date before start_date is rejected', function () {
    $car = Car::factory()->create(['availability' => CarAvailability::Disponivel]);
    $renter = User::factory()->create();

    $this->actingAs(User::factory()->create())
        ->post(route('rentals.store'), rentalPayload($car, $renter, [
            'start_date' => '2026-10-15',
            'end_date' => '2026-10-10',
        ]))
        ->assertSessionHasErrors('end_date');

    expect(Rental::count())->toBe(0);
});

test('the car of a rental cannot be changed on update', function () {
    $originalCar = Car::factory()->create(['availability' => CarAvailability::Alugado]);
    $otherCar = Car::factory()->create(['availability' => CarAvailability::Disponivel]);
    $rental = Rental::factory()->create(['car_id' => $originalCar->id, 'status' => RentalStatus::Confirmado]);

    $this->actingAs(User::factory()->create())
        ->put(route('rentals.update', $rental), [
            'user_id' => $rental->user_id,
            'car_id' => $otherCar->id,
            'start_date' => $rental->start_date->toDateString(),
            'end_date' => $rental->end_date->toDateString(),
            'daily_rate' => $rental->daily_rate,
            'status' => RentalStatus::Confirmado->value,
        ]);

    expect($rental->refresh()->car_id)->toBe($originalCar->id);
});

test('concluding a rental frees the car', function () {
    $car = Car::factory()->create(['availability' => CarAvailability::Alugado]);
    $rental = Rental::factory()->create(['car_id' => $car->id, 'status' => RentalStatus::EmAndamento]);

    $this->actingAs(User::factory()->create())
        ->put(route('rentals.update', $rental), [
            'user_id' => $rental->user_id,
            'start_date' => $rental->start_date->toDateString(),
            'end_date' => $rental->end_date->toDateString(),
            'daily_rate' => $rental->daily_rate,
            'status' => RentalStatus::Concluido->value,
        ]);

    expect($car->refresh()->availability)->toBe(CarAvailability::Disponivel);
});

test('cancelling an active rental frees the car', function () {
    $car = Car::factory()->create(['availability' => CarAvailability::Alugado]);
    $rental = Rental::factory()->create(['car_id' => $car->id, 'status' => RentalStatus::Confirmado]);

    $this->actingAs(User::factory()->create())
        ->put(route('rentals.update', $rental), [
            'user_id' => $rental->user_id,
            'start_date' => $rental->start_date->toDateString(),
            'end_date' => $rental->end_date->toDateString(),
            'daily_rate' => $rental->daily_rate,
            'status' => RentalStatus::Cancelado->value,
        ]);

    expect($car->refresh()->availability)->toBe(CarAvailability::Disponivel);
});

test('reopening a rental requires the car to be available again', function () {
    $car = Car::factory()->create(['availability' => CarAvailability::Vendido]);
    $rental = Rental::factory()->create(['car_id' => $car->id, 'status' => RentalStatus::Cancelado]);

    $this->actingAs(User::factory()->create())
        ->put(route('rentals.update', $rental), [
            'user_id' => $rental->user_id,
            'start_date' => $rental->start_date->toDateString(),
            'end_date' => $rental->end_date->toDateString(),
            'daily_rate' => $rental->daily_rate,
            'status' => RentalStatus::Confirmado->value,
        ])->assertSessionHasErrors('status');

    expect($rental->refresh()->status)->toBe(RentalStatus::Cancelado);
});

test('editing without changing the dates keeps the same total', function () {
    $rental = Rental::factory()->create([
        'start_date' => '2026-10-10',
        'end_date' => '2026-10-15',
        'daily_rate' => 45000,
        'status' => RentalStatus::Confirmado,
    ]);
    $originalTotal = $rental->total;

    $this->actingAs(User::factory()->create())
        ->put(route('rentals.update', $rental), [
            'user_id' => $rental->user_id,
            'start_date' => $rental->start_date->toDateString(),
            'end_date' => $rental->end_date->toDateString(),
            'daily_rate' => $rental->daily_rate,
            'status' => RentalStatus::EmAndamento->value,
        ]);

    expect($rental->refresh()->total)->toBe($originalTotal);
});

test('deleting an active rental frees the car', function () {
    $car = Car::factory()->create(['availability' => CarAvailability::Alugado]);
    $rental = Rental::factory()->create(['car_id' => $car->id, 'status' => RentalStatus::Confirmado]);

    $this->actingAs(User::factory()->create())
        ->delete(route('rentals.destroy', $rental))
        ->assertRedirect(route('rentals.index'));

    expect($car->refresh()->availability)->toBe(CarAvailability::Disponivel);
});

test('deleting a concluded rental does not touch availability', function () {
    $car = Car::factory()->create(['availability' => CarAvailability::Disponivel]);
    $rental = Rental::factory()->concluded()->create(['car_id' => $car->id]);

    $this->actingAs(User::factory()->create())
        ->delete(route('rentals.destroy', $rental));

    expect($car->refresh()->availability)->toBe(CarAvailability::Disponivel);
});
