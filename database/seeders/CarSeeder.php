<?php

namespace Database\Seeders;

use App\Models\Car;
use Illuminate\Database\Seeder;

class CarSeeder extends Seeder
{
    /**
     * Seed the car inventory.
     */
    public function run(): void
    {
        Car::factory(25)->create();
    }
}
