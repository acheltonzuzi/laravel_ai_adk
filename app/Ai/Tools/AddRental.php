<?php

namespace App\Ai\Tools;

use App\Concerns\RentalValidationRules;
use App\Enums\CarAvailability;
use App\Enums\RentalStatus;
use App\Models\Car;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class AddRental implements Approvable, Tool
{
    use InteractsWithApprovals, RentalValidationRules;

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Book a new rental for an available car. The car must have availability "disponivel". If daily_rate is omitted, the car\'s price is used instead.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $validated = $request->validate([
            ...$this->rentalRules(),
            'daily_rate' => ['nullable', 'numeric', 'min:0', 'max:9999999999.99'],
        ]);

        $car = Car::find($validated['car_id']);

        if ($car === null) {
            return json_encode(['error' => 'No car found with the given ID.']);
        }

        if ($car->availability !== CarAvailability::Disponivel) {
            return json_encode(['error' => 'This car is not available for rent.']);
        }

        $dailyRate = $validated['daily_rate'] ?? $car->price;

        if ($dailyRate === null) {
            return json_encode(['error' => 'This car has no price set; provide a daily_rate.']);
        }

        $start = Carbon::parse($validated['start_date']);
        $end = Carbon::parse($validated['end_date']);

        if ($end->lt($start)) {
            return json_encode(['error' => 'end_date must be on or after start_date.']);
        }

        $overlaps = Rental::query()->overlapping($car->id, $start, $end)->exists();

        if ($overlaps) {
            return json_encode(['error' => 'This car is already booked for an overlapping period.']);
        }

        $rental = Rental::create([
            'user_id' => $validated['user_id'],
            'car_id' => $car->id,
            'start_date' => $start,
            'end_date' => $end,
            'daily_rate' => $dailyRate,
            'status' => RentalStatus::Pendente,
        ]);

        $car->update(['availability' => CarAvailability::Alugado]);

        return json_encode([
            'id' => $rental->id,
            'days' => $rental->days,
            'total' => (float) $rental->total,
            'status' => $rental->status->value,
        ]);
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'user_id' => $schema->integer()
                ->description('The ID of the user renting the car.')
                ->required(),
            'car_id' => $schema->integer()
                ->description('The ID of the car to rent. Must currently be available.')
                ->required(),
            'start_date' => $schema->string()
                ->description('The first day of the rental, as YYYY-MM-DD.')
                ->required(),
            'end_date' => $schema->string()
                ->description('The last day of the rental, as YYYY-MM-DD. May equal start_date for a single day.')
                ->required(),
            'daily_rate' => $schema->number()
                ->description("The daily rate. Omit to use the car's price."),
        ];
    }

    /**
     * Booking a rental reserves the car, so always ask first.
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        $car = Car::find($request['car_id'] ?? null);
        $user = User::find($request['user_id'] ?? null);

        return Approval::required(sprintf(
            'This will book %s for %s from %s to %s.',
            $car === null ? 'an unknown car' : "{$car->brand} {$car->model} ({$car->license_plate})",
            $user === null ? 'an unknown user' : $user->name,
            $request['start_date'] ?? 'an unknown date',
            $request['end_date'] ?? 'an unknown date',
        ));
    }
}
