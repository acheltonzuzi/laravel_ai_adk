<?php

namespace App\Http\Controllers;

use App\Enums\CarAvailability;
use App\Enums\CarCondition;
use App\Http\Requests\StoreCarRequest;
use App\Http\Requests\UpdateCarRequest;
use App\Models\Car;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CarController extends Controller
{
    /**
     * Show the paginated, searchable car inventory.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $condition = (string) $request->query('condition', '');
        $availability = (string) $request->query('availability', '');

        $cars = Car::query()
            ->when($search !== '', fn ($query) => $query->search($search))
            ->when($condition !== '', fn ($query) => $query->where('condition', $condition))
            ->when($availability !== '', fn ($query) => $query->where('availability', $availability))
            ->latest('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Car $car): array => $this->toPayload($car));

        return Inertia::render('cars/index', [
            'cars' => $cars,
            'filters' => [
                'search' => $search,
                'condition' => $condition,
                'availability' => $availability,
            ],
            'conditions' => CarCondition::options(),
            'availabilities' => CarAvailability::options(),
        ]);
    }

    /**
     * Store a newly created car.
     */
    public function store(StoreCarRequest $request): RedirectResponse
    {
        $data = $request->safe()->except(['image']);

        if ($request->hasFile('image')) {
            $data['image_path'] = $request->file('image')->store('cars', 'public');
        }

        Car::create($data);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Veículo cadastrado.')]);

        return to_route('cars.index');
    }

    /**
     * Update the given car, keeping the current image unless a new one is sent.
     */
    public function update(UpdateCarRequest $request, Car $car): RedirectResponse
    {
        $car->fill($request->safe()->except(['image', 'remove_image']));

        $previousImage = $car->getOriginal('image_path');

        if ($request->hasFile('image')) {
            $car->image_path = $request->file('image')->store('cars', 'public');
        } elseif ($request->boolean('remove_image')) {
            $car->image_path = null;
        }

        $car->save();

        if ($previousImage !== null && $previousImage !== $car->image_path) {
            Storage::disk('public')->delete($previousImage);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Veículo atualizado.')]);

        return to_route('cars.index');
    }

    /**
     * Delete the given car. The model removes its image file.
     */
    public function destroy(Car $car): RedirectResponse
    {
        $car->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Veículo removido.')]);

        return to_route('cars.index');
    }

    /**
     * Shape a car for the front end, sending both the raw enum value and its label.
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
            'condition_label' => $car->condition->label(),
            'location' => $car->location,
            'availability' => $car->availability->value,
            'availability_label' => $car->availability->label(),
            'image_url' => $car->image_url,
            'created_at' => $car->created_at?->toIso8601String(),
        ];
    }
}
