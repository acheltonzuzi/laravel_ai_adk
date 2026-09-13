<?php

namespace App\Http\Requests;

use App\Concerns\CarValidationRules;
use App\Models\Car;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCarRequest extends FormRequest
{
    use CarValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * An absent image means "keep the current one", so removing it needs the
     * explicit remove_image flag.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->carRules($this->route('car')->id),
            'image' => $this->imageRules(),
            'remove_image' => ['nullable', 'boolean'],
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
