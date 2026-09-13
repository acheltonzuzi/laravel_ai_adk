<?php

namespace App\Ai\Tools;

use App\Enums\RentalStatus;
use App\Models\Rental;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Validation\Rule;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class SearchRental implements Tool
{
    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'List rentals, optionally filtered by car, user or status. Use this to find the ID of a rental before editing or deleting it.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            'car_id' => ['nullable', 'integer'],
            'user_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(RentalStatus::class)],
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        return Rental::query()
            ->with(['car', 'user'])
            ->when(isset($validated['car_id']), fn ($query) => $query->where('car_id', $validated['car_id']))
            ->when(isset($validated['user_id']), fn ($query) => $query->where('user_id', $validated['user_id']))
            ->when(isset($validated['status']), fn ($query) => $query->where('status', $validated['status']))
            ->latest('id')
            ->limit($validated['limit'] ?? 10)
            ->get()
            ->map(fn (Rental $rental): array => $this->toPayload($rental))
            ->toJson();
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'car_id' => $schema->integer()
                ->description('Restrict the results to rentals of this car.'),
            'user_id' => $schema->integer()
                ->description('Restrict the results to rentals of this user.'),
            'status' => $schema->string()
                ->enum(RentalStatus::class)
                ->description('Restrict the results to rentals with this status.'),
            'limit' => $schema->integer()
                ->description('Maximum number of rentals to return. Defaults to 10, at most 50.'),
        ];
    }

    /**
     * Shape a rental for the model.
     *
     * @return array<string, mixed>
     */
    private function toPayload(Rental $rental): array
    {
        return [
            'id' => $rental->id,
            'car' => "{$rental->car->code} {$rental->car->brand} {$rental->car->model} ({$rental->car->license_plate})",
            'user' => "{$rental->user->name} <{$rental->user->email}>",
            'start_date' => $rental->start_date->toDateString(),
            'end_date' => $rental->end_date->toDateString(),
            'days' => $rental->days,
            'daily_rate' => (float) $rental->daily_rate,
            'total' => (float) $rental->total,
            'status' => $rental->status->value,
        ];
    }
}
