<?php

namespace App\Ai\Tools;

use App\Models\Car;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DeleteCar implements Approvable, Tool
{
    use InteractsWithApprovals;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Permanently delete a car from the inventory. Find the car ID with the SearchCar tool first.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $car = Car::find($validated['id']);

        if ($car === null) {
            return json_encode(['error' => 'No car found with the given ID.']);
        }

        $deleted = [
            'id' => $car->id,
            'code' => $car->code,
            'license_plate' => $car->license_plate,
            'brand' => $car->brand,
            'model' => $car->model,
        ];

        $car->delete();

        return json_encode(['deleted' => $deleted]);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The ID of the car to delete.')
                ->required(),
        ];
    }

    /**
     * Deleting inventory is irreversible, so always ask first.
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        $car = Car::find($request['id'] ?? null);

        return Approval::required(sprintf(
            'This will permanently delete %s.',
            $car === null
                ? 'car #'.($request['id'] ?? 'unknown')
                : "{$car->code} — {$car->brand} {$car->model} ({$car->license_plate})",
        ));
    }
}
