<?php

namespace App\Http\Controllers;

use App\Enums\CarAvailability;
use App\Enums\RentalStatus;
use App\Http\Requests\StoreRentalRequest;
use App\Http\Requests\UpdateRentalRequest;
use App\Models\Car;
use App\Models\Rental;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RentalController extends Controller
{
    /**
     * Show the paginated, searchable, filterable rental list.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $status = (string) $request->query('status', '');
        $carId = (string) $request->query('car_id', '');
        $userId = (string) $request->query('user_id', '');

        $rentals = Rental::query()
            ->with(['car', 'user'])
            ->when($search !== '', fn ($query) => $query->where(
                fn ($query) => $query
                    ->whereHas('car', fn ($query) => $query->search($search))
                    ->orWhereHas('user', fn ($query) => $query
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%"))
            ))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($carId !== '', fn ($query) => $query->where('car_id', $carId))
            ->when($userId !== '', fn ($query) => $query->where('user_id', $userId))
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Rental $rental): array => $this->toPayload($rental));

        return Inertia::render('rentals/index', [
            'rentals' => $rentals,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'car_id' => $carId,
                'user_id' => $userId,
            ],
            'statuses' => RentalStatus::options(),
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'email']),
            'cars' => Car::query()
                ->where('availability', CarAvailability::Disponivel)
                ->orderBy('brand')->orderBy('model')
                ->get(['id', 'code', 'brand', 'model', 'license_plate', 'price']),
        ]);
    }

    /**
     * Book a new rental for an available car.
     */
    public function store(StoreRentalRequest $request): RedirectResponse
    {
        $car = Car::findOrFail($request->validated('car_id'));

        if ($car->availability !== CarAvailability::Disponivel) {
            return back()->withErrors([
                'car_id' => __('This car is not available for rent.'),
            ])->withInput();
        }

        Rental::create([
            ...$request->validated(),
            'status' => RentalStatus::Pendente,
        ]);

        $car->update(['availability' => CarAvailability::Alugado]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rental booked.')]);

        return to_route('rentals.index');
    }

    /**
     * Update the given rental, syncing the car's availability with any status change.
     */
    public function update(UpdateRentalRequest $request, Rental $rental): RedirectResponse
    {
        $previousStatus = $rental->status;
        $newStatus = RentalStatus::from($request->validated('status'));

        if (! $previousStatus->isActive() && $newStatus->isActive()) {
            if ($rental->car->availability !== CarAvailability::Disponivel) {
                return back()->withErrors([
                    'status' => __('This car is not available for rent.'),
                ])->withInput();
            }
        }

        $rental->fill(collect($request->validated())->except('car_id')->all());
        $rental->save();

        $this->syncCarAvailability($rental, $previousStatus);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rental updated.')]);

        return to_route('rentals.index');
    }

    /**
     * Delete the given rental, freeing the car when the rental was active.
     */
    public function destroy(Rental $rental): RedirectResponse
    {
        $wasActive = $rental->status->isActive();

        $rental->delete();

        if ($wasActive) {
            $rental->car->update(['availability' => CarAvailability::Disponivel]);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Rental deleted.')]);

        return to_route('rentals.index');
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
     * Shape a rental for the front end, with a summary of its car and renter.
     *
     * @return array<string, mixed>
     */
    private function toPayload(Rental $rental): array
    {
        return [
            'id' => $rental->id,
            'user_id' => $rental->user_id,
            'car_id' => $rental->car_id,
            'start_date' => $rental->start_date->toDateString(),
            'end_date' => $rental->end_date->toDateString(),
            'days' => $rental->days,
            'daily_rate' => (float) $rental->daily_rate,
            'total' => (float) $rental->total,
            'status' => $rental->status->value,
            'status_label' => $rental->status->label(),
            'created_at' => $rental->created_at?->toIso8601String(),
            'car' => [
                'id' => $rental->car->id,
                'code' => $rental->car->code,
                'brand' => $rental->car->brand,
                'model' => $rental->car->model,
                'license_plate' => $rental->car->license_plate,
            ],
            'user' => [
                'id' => $rental->user->id,
                'name' => $rental->user->name,
                'email' => $rental->user->email,
            ],
        ];
    }
}
