<?php

use App\Enums\CarAvailability;
use App\Enums\CarCondition;
use App\Models\Car;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('public');
});

/**
 * Valid car payload, overridable per test.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function carPayload(array $overrides = []): array
{
    return [
        'license_plate' => 'LD4567AB',
        'brand' => 'Toyota',
        'model' => 'Corolla',
        'year' => 2022,
        'price' => 12500000,
        'condition' => CarCondition::Bom->value,
        'location' => 'Pátio Central',
        'availability' => CarAvailability::Disponivel->value,
        ...$overrides,
    ];
}

test('guests cannot reach the car inventory', function () {
    $this->get(route('cars.index'))->assertRedirect(route('login'));
});

test('the page lists cars and exposes the enum options', function () {
    Car::factory()->create(['brand' => 'Toyota', 'model' => 'Hilux']);

    $this->actingAs(User::factory()->create())
        ->get(route('cars.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('cars/index')
            ->has('cars.data', 1)
            ->where('cars.data.0.brand', 'Toyota')
            ->where('cars.data.0.condition_label', fn ($label) => filled($label))
            ->has('conditions', 4)
            ->has('availabilities', 4)
        );
});

test('the inventory can be searched by code, plate, brand and model', function () {
    $this->actingAs(User::factory()->create());

    $target = Car::factory()->create([
        'license_plate' => 'AB1234CD',
        'brand' => 'Nissan',
        'model' => 'Navara',
    ]);
    Car::factory()->create(['brand' => 'Ford', 'model' => 'Focus', 'license_plate' => 'ZZ9999ZZ']);

    foreach ([$target->code, 'AB1234', 'Nissan', 'Navara'] as $term) {
        $response = $this->get(route('cars.index', ['search' => $term]));

        expect($response->viewData('page')['props']['cars']['data'])
            ->toHaveCount(1, "a busca por [{$term}] devia achar só um carro");
    }
});

test('the inventory can be filtered by availability', function () {
    $this->actingAs(User::factory()->create());

    Car::factory()->available()->count(2)->create();
    Car::factory()->rented()->create();

    $this->get(route('cars.index', ['availability' => CarAvailability::Alugado->value]))
        ->assertInertia(fn ($page) => $page->has('cars.data', 1));
});

test('a car is created and gets a sequential code', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('cars.store'), carPayload())->assertRedirect(route('cars.index'));

    $car = Car::firstOrFail();

    expect($car->code)->toBe('CAR-0001')
        ->and($car->brand)->toBe('Toyota')
        ->and($car->price)->toBe('12500000.00')
        ->and($car->condition)->toBe(CarCondition::Bom)
        ->and($car->image_path)->toBeNull();
});

test('the price is optional and rejects negatives', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('cars.store'), [...carPayload(), 'price' => null])
        ->assertSessionHasNoErrors();

    expect(Car::firstOrFail()->price)->toBeNull();

    $this->post(route('cars.store'), carPayload([
        'license_plate' => 'ZZ9999ZZ',
        'price' => -5,
    ]))->assertSessionHasErrors('price');

    expect(Car::count())->toBe(1);
});

test('the index exposes the price as a number', function () {
    Car::factory()->create(['price' => 9800000]);

    $this->actingAs(User::factory()->create())
        ->get(route('cars.index'))
        ->assertInertia(fn ($page) => $page->where(
            'cars.data.0.price',
            fn ($price) => is_numeric($price) && (float) $price === 9800000.0
        ));
});

test('a car is created with an image', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('cars.store'), carPayload([
        'image' => UploadedFile::fake()->image('carro.jpg'),
    ]))->assertRedirect(route('cars.index'));

    $car = Car::firstOrFail();

    expect($car->image_path)->toStartWith('cars/')
        ->and($car->image_url)->not->toBeNull();

    Storage::disk('public')->assertExists($car->image_path);
});

test('the license plate is normalized so formatting differences still collide', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('cars.store'), carPayload(['license_plate' => 'AB-12-CD']));

    expect(Car::firstOrFail()->license_plate)->toBe('AB12CD');

    $this->post(route('cars.store'), carPayload(['license_plate' => 'ab 12 cd']))
        ->assertSessionHasErrors('license_plate');

    expect(Car::count())->toBe(1);
});

test('a non image upload is rejected', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('cars.store'), carPayload([
        'image' => UploadedFile::fake()->create('documento.pdf', 100, 'application/pdf'),
    ]))->assertSessionHasErrors('image');

    expect(Car::count())->toBe(0);
});

test('an invalid enum value is rejected', function () {
    $this->actingAs(User::factory()->create());

    $this->post(route('cars.store'), carPayload(['condition' => 'quebrado']))
        ->assertSessionHasErrors('condition');

    $this->post(route('cars.store'), carPayload(['availability' => 'sumiu']))
        ->assertSessionHasErrors('availability');
});

// The test client handles UploadedFile on PUT fine. The POST + ?_method=PUT dance
// the front end uses is a browser/PHP-SAPI limitation, not a test one — do not
// "fix" the front end to match these tests.
test('updating without a file keeps the current image', function () {
    $this->actingAs(User::factory()->create());

    $car = Car::factory()->create(['image_path' => 'cars/original.jpg']);
    Storage::disk('public')->put('cars/original.jpg', 'conteudo');

    $this->put(route('cars.update', $car), carPayload(['brand' => 'Hyundai']))
        ->assertRedirect(route('cars.index'));

    expect($car->refresh()->image_path)->toBe('cars/original.jpg')
        ->and($car->brand)->toBe('Hyundai');

    Storage::disk('public')->assertExists('cars/original.jpg');
});

test('updating with a new file replaces and deletes the old one', function () {
    $this->actingAs(User::factory()->create());

    $car = Car::factory()->create(['image_path' => 'cars/original.jpg']);
    Storage::disk('public')->put('cars/original.jpg', 'conteudo');

    $this->put(route('cars.update', $car), carPayload([
        'image' => UploadedFile::fake()->image('nova.jpg'),
    ]));

    expect($car->refresh()->image_path)->not->toBe('cars/original.jpg');

    Storage::disk('public')->assertExists($car->image_path);
    Storage::disk('public')->assertMissing('cars/original.jpg');
});

test('remove_image clears the column and deletes the file', function () {
    $this->actingAs(User::factory()->create());

    $car = Car::factory()->create(['image_path' => 'cars/original.jpg']);
    Storage::disk('public')->put('cars/original.jpg', 'conteudo');

    $this->put(route('cars.update', $car), carPayload(['remove_image' => true]));

    expect($car->refresh()->image_path)->toBeNull();

    Storage::disk('public')->assertMissing('cars/original.jpg');
});

test('deleting a car removes its image file', function () {
    $this->actingAs(User::factory()->create());

    $car = Car::factory()->create(['image_path' => 'cars/original.jpg']);
    Storage::disk('public')->put('cars/original.jpg', 'conteudo');

    $this->delete(route('cars.destroy', $car))->assertRedirect(route('cars.index'));

    expect(Car::find($car->id))->toBeNull();

    Storage::disk('public')->assertMissing('cars/original.jpg');
});
