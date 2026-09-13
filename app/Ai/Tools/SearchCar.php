<?php

namespace App\Ai\Tools;

use App\Enums\CarAvailability;
use App\Enums\CarCondition;
use App\Models\Car;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchCar implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Search the car inventory by code, license plate, brand, model or location. Use this to find the ID of a car before editing or deleting it.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'query' => ['required', 'string', 'max:255'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
            'condition' => ['nullable', Rule::enum(CarCondition::class)],
            'availability' => ['nullable', Rule::enum(CarAvailability::class)],
        ]);

        return Car::query()
            ->search($validated['query'])
            ->when(
                isset($validated['condition']),
                fn ($query) => $query->where('condition', $validated['condition'])
            )
            ->when(
                isset($validated['availability']),
                fn ($query) => $query->where('availability', $validated['availability'])
            )
            ->latest('id')
            ->limit($validated['limit'] ?? 10)
            ->get()
            ->map(fn (Car $car): array => $this->toPayload($car))
            ->toJson();
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('Text to match against the code, license plate, brand, model or location.')
                ->required(),
            'limit' => $schema->integer()
                ->description('Maximum number of cars to return. Defaults to 10, at most 50.'),
            'condition' => $schema->string()
                ->enum(CarCondition::class)
                ->description('Restrict the results to this physical condition.'),
            'availability' => $schema->string()
                ->enum(CarAvailability::class)
                ->description('Restrict the results to this commercial availability.'),
        ];
    }

    /**
     * Shape a car for the model.
     *
     * @return array<string, mixed>
     */
    private function toPayload(Car $car): array
    {
        return [
            'id' => $car->id,
            'code' => $car->code,
            'license_plate' => $car->license_plate,
            'brand' => $car->brand,
            'model' => $car->model,
            'year' => $car->year,
            'price' => $car->price === null ? null : (float) $car->price,
            'condition' => $car->condition->value,
            'location' => $car->location,
            'availability' => $car->availability->value,
        ];
    }
}
