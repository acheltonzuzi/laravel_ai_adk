<?php

namespace Database\Factories;

use App\Enums\RentalStatus;
use App\Models\Car;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Rental>
 */
class RentalFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * days/total are recalculated by the model's "saving" hook regardless of
     * what is set here, so they are omitted.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = fake()->dateTimeBetween('-2 months', '+2 months');
        $end = (clone $start)->modify('+'.fake()->numberBetween(1, 10).' days');

        return [
            'user_id' => User::factory(),
            'car_id' => Car::factory(),
            'start_date' => $start,
            'end_date' => $end,
            'daily_rate' => fake()->randomFloat(2, 15000, 150000),
            'status' => fake()->randomElement(RentalStatus::cases()),
        ];
    }

    /**
     * Indicate that the rental is confirmed.
     */
    public function confirmed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RentalStatus::Confirmado,
        ]);
    }

    /**
     * Indicate that the rental is finished.
     */
    public function concluded(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RentalStatus::Concluido,
        ]);
    }

    /**
     * Indicate that the rental was cancelled.
     */
    public function cancelled(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => RentalStatus::Cancelado,
        ]);
    }
}
