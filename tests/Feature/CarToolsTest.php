<?php

use App\Ai\Tools\AddCar;
use App\Ai\Tools\DeleteCar;
use App\Ai\Tools\EditCar;
use App\Ai\Tools\SearchCar;
use App\Enums\CarAvailability;
use App\Enums\CarCondition;
use App\Models\Car;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Tools\Request;

uses(RefreshDatabase::class);

test('every car tool exposes exactly the arguments it uses', function () {
    expect(array_keys(toolSchema(new SearchCar)['properties']))
        ->toBe(['query', 'limit', 'condition', 'availability'])
        ->and(toolSchema(new SearchCar)['required'])->toBe(['query'])
        ->and(array_keys(toolSchema(new AddCar)['properties']))
        ->toBe(['license_plate', 'brand', 'model', 'year', 'price', 'condition', 'availability', 'location'])
        ->and(toolSchema(new AddCar)['required'])
        ->toBe(['license_plate', 'brand', 'model', 'year', 'condition', 'availability'])
        ->and(array_keys(toolSchema(new EditCar)['properties']))
        ->toBe(['id', 'license_plate', 'brand', 'model', 'year', 'price', 'condition', 'availability', 'location'])
        ->and(toolSchema(new EditCar)['required'])->toBe(['id'])
        ->and(array_keys(toolSchema(new DeleteCar)['properties']))->toBe(['id'])
        ->and(toolSchema(new DeleteCar)['required'])->toBe(['id']);
});

test('the enum arguments advertise their allowed values', function () {
    $properties = toolSchema(new AddCar)['properties'];

    expect($properties['condition']['enum'])->toBe(['novo', 'bom', 'regular', 'danificado'])
        ->and($properties['availability']['enum'])
        ->toBe(['disponivel', 'alugado', 'vendido', 'manutencao']);
});

test('the search tool matches plate, brand and code', function () {
    $target = Car::factory()->create([
        'license_plate' => 'AB1234CD',
        'brand' => 'Nissan',
        'model' => 'Navara',
    ]);
    Car::factory()->create(['brand' => 'Ford', 'model' => 'Focus', 'license_plate' => 'ZZ9999ZZ']);

    expect(runTool(new SearchCar, ['query' => 'Nissan']))->toHaveCount(1)
        ->and(runTool(new SearchCar, ['query' => 'Nissan'])[0]['id'])->toBe($target->id)
        ->and(runTool(new SearchCar, ['query' => 'AB1234']))->toHaveCount(1)
        ->and(runTool(new SearchCar, ['query' => $target->code]))->toHaveCount(1);
});

test('the search tool can filter by availability and honour the limit', function () {
    Car::factory()->available()->count(3)->create(['brand' => 'Toyota']);
    Car::factory()->rented()->create(['brand' => 'Toyota']);

    expect(runTool(new SearchCar, ['query' => 'Toyota']))->toHaveCount(4)
        ->and(runTool(new SearchCar, ['query' => 'Toyota', 'limit' => 2]))->toHaveCount(2)
        ->and(runTool(new SearchCar, [
            'query' => 'Toyota',
            'availability' => CarAvailability::Alugado->value,
        ]))->toHaveCount(1);
});

test('the add tool creates a car and generates its code', function () {
    $result = runTool(new AddCar, [
        'license_plate' => 'ld-45-67',
        'brand' => 'Toyota',
        'model' => 'Hilux',
        'year' => 2021,
        'condition' => CarCondition::Bom->value,
        'availability' => CarAvailability::Disponivel->value,
    ]);

    expect($result['code'])->toBe('CAR-0001')
        ->and($result['license_plate'])->toBe('LD4567')
        ->and(Car::count())->toBe(1);
});

test('the add tool stores the price when given, and leaves it null when not', function () {
    runTool(new AddCar, [
        'license_plate' => 'AB1234',
        'brand' => 'Toyota',
        'model' => 'Hilux',
        'year' => 2021,
        'price' => 15500000.5,
        'condition' => CarCondition::Bom->value,
        'availability' => CarAvailability::Disponivel->value,
    ]);

    runTool(new AddCar, [
        'license_plate' => 'CD5678',
        'brand' => 'Ford',
        'model' => 'Ranger',
        'year' => 2020,
        'condition' => CarCondition::Bom->value,
        'availability' => CarAvailability::Disponivel->value,
    ]);

    expect(Car::where('license_plate', 'AB1234')->value('price'))->toBe('15500000.50')
        ->and(Car::where('license_plate', 'CD5678')->value('price'))->toBeNull();
});

test('the search tool reports the price as a number', function () {
    $priced = Car::factory()->create(['brand' => 'Toyota', 'price' => 9800000]);
    $unpriced = Car::factory()->create(['brand' => 'Toyota', 'price' => null]);

    $results = collect(runTool(new SearchCar, ['query' => 'Toyota']))->keyBy('id');

    expect($results[$priced->id]['price'])->toEqual(9800000)
        ->and($results[$priced->id]['price'])->toBeNumeric()
        ->and($results[$unpriced->id]['price'])->toBeNull();
});

test('the edit tool updates the price without touching other fields', function () {
    $car = Car::factory()->create(['brand' => 'Ford', 'price' => 1000000]);

    runTool(new EditCar, ['id' => $car->id, 'price' => 2500000.75]);

    expect($car->refresh()->price)->toBe('2500000.75')
        ->and($car->brand)->toBe('Ford');
});

test('the add tool rejects a negative price', function () {
    expect(fn () => runTool(new AddCar, [
        'license_plate' => 'AB1234',
        'brand' => 'Toyota',
        'model' => 'Hilux',
        'year' => 2021,
        'price' => -1,
        'condition' => CarCondition::Bom->value,
        'availability' => CarAvailability::Disponivel->value,
    ]))->toThrow(ValidationException::class);

    expect(Car::count())->toBe(0);
});

test('the add tool rejects an invalid enum value', function () {
    expect(fn () => runTool(new AddCar, [
        'license_plate' => 'AB1234',
        'brand' => 'Toyota',
        'model' => 'Hilux',
        'year' => 2021,
        'condition' => 'quebrado',
        'availability' => CarAvailability::Disponivel->value,
    ]))->toThrow(ValidationException::class);

    expect(Car::count())->toBe(0);
});

test('the edit tool only changes the fields it receives', function () {
    $car = Car::factory()->create([
        'brand' => 'Ford',
        'model' => 'Focus',
        'availability' => CarAvailability::Disponivel,
    ]);

    runTool(new EditCar, [
        'id' => $car->id,
        'availability' => CarAvailability::Alugado->value,
    ]);

    $car->refresh();

    expect($car->availability)->toBe(CarAvailability::Alugado)
        ->and($car->brand)->toBe('Ford')
        ->and($car->model)->toBe('Focus');
});

test('the edit tool accepts the car keeping its own plate', function () {
    $car = Car::factory()->create(['license_plate' => 'AB1234CD']);

    runTool(new EditCar, [
        'id' => $car->id,
        'license_plate' => 'AB1234CD',
        'brand' => 'Hyundai',
    ]);

    expect($car->refresh()->brand)->toBe('Hyundai');
});

test('the edit tool rejects a plate taken by another car', function () {
    $car = Car::factory()->create(['license_plate' => 'AB1234CD']);
    Car::factory()->create(['license_plate' => 'ZZ9999ZZ']);

    expect(fn () => runTool(new EditCar, [
        'id' => $car->id,
        'license_plate' => 'ZZ9999ZZ',
    ]))->toThrow(ValidationException::class);

    expect($car->refresh()->license_plate)->toBe('AB1234CD');
});

test('the delete tool removes the car', function () {
    $car = Car::factory()->create();

    $result = runTool(new DeleteCar, ['id' => $car->id]);

    expect($result['deleted']['code'])->toBe($car->code)
        ->and(Car::find($car->id))->toBeNull();
});

test('the car tools report an unknown id instead of failing', function () {
    expect(runTool(new EditCar, ['id' => 999, 'brand' => 'Fantasma']))->toHaveKey('error')
        ->and(runTool(new DeleteCar, ['id' => 999]))->toHaveKey('error');
});

test('the mutating car tools require approval', function () {
    $car = Car::factory()->create(['brand' => 'Toyota', 'model' => 'Hilux']);

    $add = (new AddCar)->shouldRequestApproval(new Request([
        'brand' => 'Ford', 'model' => 'Ranger', 'license_plate' => 'AB1234',
    ]));
    $edit = (new EditCar)->shouldRequestApproval(new Request([
        'id' => $car->id, 'availability' => CarAvailability::Vendido->value,
    ]));
    $delete = (new DeleteCar)->shouldRequestApproval(new Request(['id' => $car->id]));

    expect($add)->not->toBeNull()
        ->and($add->reason)->toContain('Ranger')
        ->and($edit)->not->toBeNull()
        ->and($edit->reason)->toContain('availability')
        ->and($delete)->not->toBeNull()
        ->and($delete->reason)->toContain($car->code);
});

test('the search tool needs no approval', function () {
    expect(new SearchCar)->not->toBeInstanceOf(Approvable::class);
});
