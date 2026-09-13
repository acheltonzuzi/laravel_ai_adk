<?php

namespace App\Http\Requests;

use App\Concerns\RentalValidationRules;
use App\Models\Rental;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;

class UpdateRentalRequest extends FormRequest
{
    use RentalValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * car_id is intentionally absent: the car of a rental cannot be changed
     * once created. Swapping the car is done by cancelling and booking a new
     * rental, which keeps the availability-sync logic in one place.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...collect($this->rentalRules())->except('car_id')->all(),
            'status' => $this->statusRules(),
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if (! $this->filled(['start_date', 'end_date'])) {
                return;
            }

            /** @var Rental $rental */
            $rental = $this->route('rental');

            $overlaps = Rental::query()->overlapping(
                $rental->car_id,
                Carbon::parse($this->input('start_date')),
                Carbon::parse($this->input('end_date')),
                $rental->id,
            )->exists();

            if ($overlaps) {
                $validator->errors()->add(
                    'start_date',
                    __('This car is already booked for an overlapping period.'),
                );
            }
        });
    }
}
