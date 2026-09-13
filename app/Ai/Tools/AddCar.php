<?php

namespace App\Ai\Tools;

use App\Concerns\CarValidationRules;
use App\Enums\CarAvailability;
use App\Enums\CarCondition;
use App\Models\Car;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AddCar implements Approvable, Tool
{
    use CarValidationRules, InteractsWithApprovals;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Add a new car to the inventory. The inventory code is generated automatically. Images cannot be set from the chat.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate($this->carRules());

        $validated['license_plate'] = Car::normalizePlate($validated['license_plate']);

        $car = Car::create($validated);

        return json_encode([
            'id' => $car->id,
            'code' => $car->code,
            'license_plate' => $car->license_plate,
            'brand' => $car->brand,
            'model' => $car->model,
        ]);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'license_plate' => $schema->string()
                ->description('The license plate. Must not already be in use.')
                ->required(),
            'brand' => $schema->string()
                ->description('The manufacturer, such as Toyota.')
                ->required(),
            'model' => $schema->string()
                ->description('The model, such as Corolla.')
                ->required(),
            'year' => $schema->integer()
                ->description('The manufacturing year.')
                ->required(),
            'price' => $schema->number()
                ->description('The asking price. Omit when the car has no price yet.'),
            'condition' => $schema->string()
                ->enum(CarCondition::class)
                ->description('The physical condition of the car.')
                ->required(),
            'availability' => $schema->string()
                ->enum(CarAvailability::class)
                ->description('The commercial availability of the car.')
                ->required(),
            'location' => $schema->string()
                ->description('Where the car is parked.'),
        ];
    }

    /**
     * Adding inventory is a write, so always ask first.
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        return Approval::required(sprintf(
            'This will add a new car to the inventory: %s %s (%s).',
            $request['brand'] ?? 'unknown brand',
            $request['model'] ?? 'unknown model',
            $request['license_plate'] ?? 'unknown plate',
        ));
    }
}
