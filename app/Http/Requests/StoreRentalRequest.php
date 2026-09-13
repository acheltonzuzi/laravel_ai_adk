<?php

namespace App\Http\Requests;

use App\Concerns\RentalValidationRules;
use App\Models\Rental;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class StoreRentalRequest extends FormRequest
{
    use RentalValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * The status is not accepted here: a new rental always starts Pendente,
     * decided server-side (see RentalController::store).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return $this->rentalRules();
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled(['car_id', 'start_date', 'end_date'])) {
                return;
            }

            $overlaps = Rental::query()->overlapping(
                (int) $this->input('car_id'),
                Carbon::parse($this->input('start_date')),
                Carbon::parse($this->input('end_date')),
            )->exists();

            if ($overlaps) {
                $validator->errors()->add(
                    'car_id',
                    __('This car is already booked for an overlapping period.'),
                );
            }
        });
    }
}
