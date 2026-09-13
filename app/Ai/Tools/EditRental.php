<?php

namespace App\Ai\Tools;

use App\Concerns\RentalValidationRules;
use App\Enums\CarAvailability;
use App\Enums\RentalStatus;
use App\Models\Rental;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Carbon;
use Laravel\Ai\Approvals\Approval;
use Laravel\Ai\Concerns\InteractsWithApprovals;
use Laravel\Ai\Contracts\Approvable;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class EditRental implements Approvable, Tool
{
    use InteractsWithApprovals, RentalValidationRules;

    /**
     * The fields this tool may change, besides the status.
     *
     * @var list<string>
     */
    private const FIELDS = ['start_date', 'end_date', 'daily_rate'];

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Update a rental: its dates, daily rate or status. Find the rental ID with the SearchRental tool first. Only the fields you provide are changed. The car of a rental cannot be changed here.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $rental = Rental::find($request['id'] ?? null);

        if ($rental === null) {
            return json_encode(['error' => 'No rental found with the given ID.']);
        }

        // start_date/end_date are validated separately below: "sometimes" plus
        // Laravel's "after_or_equal:start_date" silently passes when start_date
        // is absent from a partial payload, so the cross-field order check has
        // to be resolved against the persisted record instead.
        $rules = ['id' => ['required', 'integer']];

        foreach ($this->rentalRules() as $field => $fieldRules) {
            if (in_array($field, ['start_date', 'end_date', 'user_id', 'car_id'], true)) {
                continue;
            }

            $rules[$field] = ['sometimes', ...$fieldRules];
        }
        $rules['start_date'] = ['sometimes', 'date'];
        $rules['end_date'] = ['sometimes', 'date'];
        $rules['status'] = ['sometimes', ...$this->statusRules()];

        $validated = $request->validate($rules);

        $start = isset($validated['start_date']) ? Carbon::parse($validated['start_date']) : $rental->start_date;
        $end = isset($validated['end_date']) ? Carbon::parse($validated['end_date']) : $rental->end_date;

        if ($end->lt($start)) {
            return json_encode(['error' => 'end_date must be on or after start_date.']);
        }

        if (isset($validated['start_date']) || isset($validated['end_date'])) {
            $overlaps = Rental::query()->overlapping($rental->car_id, $start, $end, $rental->id)->exists();

            if ($overlaps) {
                return json_encode(['error' => 'This car is already booked for an overlapping period.']);
            }
        }

        $previousStatus = $rental->status;
        $newStatus = isset($validated['status']) ? RentalStatus::from($validated['status']) : $previousStatus;

        if (! $previousStatus->isActive() && $newStatus->isActive() && $rental->car->availability !== CarAvailability::Disponivel) {
            return json_encode(['error' => 'This car is not available for rent.']);
        }

        $rental->fill(array_intersect_key($validated, array_flip(self::FIELDS)));
        $rental->status = $newStatus;
        $rental->save();

        $this->syncCarAvailability($rental, $previousStatus);

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
            'id' => $schema->integer()
                ->description('The ID of the rental to update.')
                ->required(),
            'start_date' => $schema->string()
                ->description('The new start date, as YYYY-MM-DD. Omit to keep the current one.'),
            'end_date' => $schema->string()
                ->description('The new end date, as YYYY-MM-DD. Omit to keep the current one.'),
            'daily_rate' => $schema->number()
                ->description('The new daily rate. Omit to keep the current one.'),
            'status' => $schema->string()
                ->enum(RentalStatus::class)
                ->description('The new status. Omit to keep the current one.'),
        ];
    }

    /**
     * Free or reserve the rental's car according to the status transition.
     */
    private function syncCarAvailability(Rental $rental, RentalStatus $previousStatus): void
    {
        $wasActive = $previousStatus->isActive();
        $isActive = $rental->status->isActive();

        if ($wasActive && ! $isActive) {
            $rental->car->update(['availability' => CarAvailability::Disponivel]);
        } elseif (! $wasActive && $isActive) {
            $rental->car->update(['availability' => CarAvailability::Alugado]);
        }
    }

    /**
     * Changing a rental should never happen silently.
     */
    protected function needsApproval(Request $request): Approval|bool
    {
        $changes = array_values(array_filter(
            [...self::FIELDS, 'status'],
            fn (string $field): bool => ($request[$field] ?? null) !== null,
        ));

        $rental = Rental::find($request['id'] ?? null);

        return Approval::required(sprintf(
            'This will update %s of %s.',
            $changes === [] ? 'nothing' : implode(', ', $changes),
            $rental === null
                ? 'rental #'.($request['id'] ?? 'unknown')
                : "rental #{$rental->id} ({$rental->car->brand} {$rental->car->model} for {$rental->user->name})",
        ));
    }
}
