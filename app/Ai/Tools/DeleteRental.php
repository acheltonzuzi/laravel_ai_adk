<?php

namespace App\Ai\Tools;

use App\Enums\CarAvailability;
use App\Models\Rental;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class DeleteRental implements Approvable, Tool
{
    use InteractsWithApprovals;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Permanently delete a rental. Find the rental ID with the SearchRental tool first. Frees the car if the rental was still active.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'id' => ['required', 'integer'],
        ]);

        $rental = Rental::find($validated['id']);

        if ($rental === null) {
            return json_encode(['error' => 'No rental found with the given ID.']);
        }

        $wasActive = $rental->status->isActive();
        $car = $rental->car;

        $deleted = [
            'id' => $rental->id,
            'car' => "{$car->code} {$car->brand} {$car->model}",
            'user' => $rental->user->name,
            'status' => $rental->status->value,
        ];

        $rental->delete();

        if ($wasActive) {
            $car->update(['availability' => CarAvailability::Disponivel]);
        }

        return json_encode(['deleted' => $deleted]);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'id' => $schema->integer()
                ->description('The ID of the rental to delete.')
                ->required(),
        ];
    }

    /**
     * Deleting a rental is irreversible, so always ask first.
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        $rental = Rental::find($request['id'] ?? null);

        return Approval::required(sprintf(
            'This will permanently delete %s.',
            $rental === null
                ? 'rental #'.($request['id'] ?? 'unknown')
                : "rental #{$rental->id} ({$rental->car->brand} {$rental->car->model} for {$rental->user->name})",
        ));
    }
}
