<?php

namespace App\Concerns;

use App\Enums\CarAvailability;
use App\Enums\CarCondition;
use App\Models\Car;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait CarValidationRules
{
    /**
     * Get the validation rules used to validate cars.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function carRules(?int $carId = null): array
    {
        return [
            'license_plate' => $this->licensePlateRules($carId),
            'brand' => $this->brandRules(),
            'model' => $this->modelRules(),
            'year' => $this->yearRules(),
            'price' => $this->priceRules(),
            'condition' => $this->conditionRules(),
            'location' => $this->locationRules(),
            'availability' => $this->availabilityRules(),
        ];
    }

    /**
     * Get the validation rules used to validate license plates.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function licensePlateRules(?int $carId = null): array
    {
        return [
            'required',
            'string',
            'max:16',
            $carId === null
                ? Rule::unique(Car::class, 'license_plate')
                : Rule::unique(Car::class, 'license_plate')->ignore($carId),
        ];
    }

    /**
     * Get the validation rules used to validate car brands.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function brandRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate car models.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function modelRules(): array
    {
        return ['required', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate car years.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function yearRules(): array
    {
        return ['required', 'integer', 'min:1900', 'max:'.((int) date('Y') + 1)];
    }

    /**
     * Get the validation rules used to validate car prices.
     *
     * The upper bound keeps the value inside the decimal(12, 2) column.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function priceRules(): array
    {
        return ['nullable', 'numeric', 'min:0', 'max:9999999999.99'];
    }

    /**
     * Get the validation rules used to validate the car condition.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function conditionRules(): array
    {
        return ['required', Rule::enum(CarCondition::class)];
    }

    /**
     * Get the validation rules used to validate car locations.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function locationRules(): array
    {
        return ['nullable', 'string', 'max:255'];
    }

    /**
     * Get the validation rules used to validate the car availability.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function availabilityRules(): array
    {
        return ['required', Rule::enum(CarAvailability::class)];
    }

    /**
     * Get the validation rules used to validate the car image.
     *
     * The mime types are allow-listed explicitly: an SVG accepted by the "image"
     * rule and served from our own origin is a stored XSS vector.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function imageRules(): array
    {
        return ['nullable', 'file', 'image', 'mimetypes:image/jpeg,image/png,image/webp', 'max:4096'];
    }
}
