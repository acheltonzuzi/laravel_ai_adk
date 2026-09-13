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

class EditCar implements Approvable, Tool
{
    use CarValidationRules, InteractsWithApprovals;

    /**
     * The fields this tool may change.
     *
     * @var list<string>
     */
    private const FIELDS = ['license_plate', 'brand', 'model', 'year', 'price', 'condition', 'location', 'availability'];

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Update a car in the inventory. Find the car ID with the SearchCar tool first. Only the fields you provide are changed. The code and the image cannot be changed here.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $car = Car::find($request['id'] ?? null);

        if ($car === null) {
            return json_encode(['error' => 'No car found with the given ID.']);
        }

        $rules = ['id' => ['required', 'integer']];

        foreach ($this->carRules($car->id) as $field => $fieldRules) {
            $rules[$field] = ['sometimes', ...$fieldRules];
        }

        $validated = $request->validate($rules);

        if (isset($validated['license_plate'])) {
            $validated['license_plate'] = Car::normalizePlate($validated['license_plate']);
        }

        $car->fill(array_intersect_key($validated, array_flip(self::FIELDS)));
        $car->save();

        return json_encode([
            'id' => $car->id,
            'code' => $car->code,
            'license_plate' => $car->license_plate,
            'brand' => $car->brand,
            'model' => $car->model,
            'condition' => $car->condition->value,
            'availability' => $car->availability->value,
        ]);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The ID of the car to update.')
                ->required(),
            'license_plate' => $schema->string()
                ->description('The new license plate. Omit to keep the current one.'),
            'brand' => $schema->string()
                ->description('The new manufacturer. Omit to keep the current one.'),
            'model' => $schema->string()
                ->description('The new model. Omit to keep the current one.'),
            'year' => $schema->integer()
                ->description('The new manufacturing year. Omit to keep the current one.'),
            'price' => $schema->number()
                ->description('The new asking price. Omit to keep the current one.'),
            'condition' => $schema->string()
                ->enum(CarCondition::class)
                ->description('The new physical condition. Omit to keep the current one.'),
            'availability' => $schema->string()
                ->enum(CarAvailability::class)
                ->description('The new commercial availability. Omit to keep the current one.'),
            'location' => $schema->string()
                ->description('The new location. Omit to keep the current one.'),
        ];
    }

    /**
     * Changing inventory records should never happen silently.
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        $changes = array_values(array_filter(
            self::FIELDS,
            fn (string $field): bool => ($request[$field] ?? null) !== null,
        ));

        $car = Car::find($request['id'] ?? null);

        return Approval::required(sprintf(
            'This will update %s of %s.',
            $changes === [] ? 'nothing' : implode(', ', $changes),
            $car === null
                ? 'car #'.($request['id'] ?? 'unknown')
                : "{$car->code} ({$car->brand} {$car->model})",
        ));
    }
}
