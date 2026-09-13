<?php

namespace App\Http\Requests;

use App\Concerns\CarValidationRules;
use App\Models\Car;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCarRequest extends FormRequest
{
    use CarValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->carRules(),
            'image' => $this->imageRules(),
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        if ($this->filled('license_plate')) {
            $this->merge([
                'license_plate' => Car::normalizePlate((string) $this->input('license_plate')),
            ]);
        }
    }
}
