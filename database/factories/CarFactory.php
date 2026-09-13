<?php

namespace Database\Factories;

use App\Enums\CarAvailability;
use App\Enums\CarCondition;
use App\Models\Car;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Car>
 */
class CarFactory extends Factory
{
    /**
     * The brand and model pairs used to build plausible vehicles.
     *
     * @var array<string, list<string>>
     */
    protected const MODELS = [
        'Toyota' => ['Corolla', 'Hilux', 'RAV4', 'Yaris'],
        'Volkswagen' => ['Golf', 'Polo', 'Tiguan', 'Amarok'],
        'Hyundai' => ['Tucson', 'i30', 'Santa Fe', 'Accent'],
        'Nissan' => ['Qashqai', 'Navara', 'Micra', 'X-Trail'],
        'Ford' => ['Ranger', 'Focus', 'Fiesta', 'Kuga'],
        'Mitsubishi' => ['Pajero', 'L200', 'ASX', 'Outlander'],
    ];

    /**
     * The places a vehicle may be parked at.
     *
     * @var list<string>
     */
    protected const LOCATIONS = [
        'Pátio Central',
        'Filial Norte',
        'Filial Sul',
        'Oficina',
        'Showroom',
    ];

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $brand = fake()->randomElement(array_keys(self::MODELS));

        return [
            // Not Car::generateCode(): a batched factory evaluates every definition
            // before inserting any row, so a database derived code would hand the
            // same value to the whole batch. The unique faker sequence cannot clash,
            // and generateCode() retries around anything it finds.
            'code' => 'CAR-'.str_pad((string) fake()->unique()->numberBetween(1, 9999), 4, '0', STR_PAD_LEFT),
            'license_plate' => Car::normalizePlate(
                fake()->unique()->bothify('??-##-##')
            ),
            'brand' => $brand,
            'model' => fake()->randomElement(self::MODELS[$brand]),
            'year' => fake()->numberBetween(2005, (int) date('Y')),
            'price' => fake()->randomFloat(2, 800_000, 25_000_000),
            'condition' => fake()->randomElement(CarCondition::cases()),
            'location' => fake()->randomElement(self::LOCATIONS),
            'availability' => fake()->randomElement(CarAvailability::cases()),
            'image_path' => null,
        ];
    }

    /**
     * Indicate that the car is available.
     */
    public function available(): static
    {
        return $this->state(fn (array $attributes) => [
            'availability' => CarAvailability::Disponivel,
        ]);
    }

    /**
     * Indicate that the car is rented.
     */
    public function rented(): static
    {
        return $this->state(fn (array $attributes) => [
            'availability' => CarAvailability::Alugado,
        ]);
    }

    /**
     * Indicate that the car already has a stored image.
     */
    public function withImage(string $path = 'cars/exemplo.jpg'): static
    {
        return $this->state(fn (array $attributes) => [
            'image_path' => $path,
        ]);
    }
}
