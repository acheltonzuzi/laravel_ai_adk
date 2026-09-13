<?php

namespace App\Concerns;

use App\Enums\RentalStatus;
use App\Models\Car;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Validation\Rule;

trait RentalValidationRules
{
    /**
     * Get the validation rules used to validate rentals.
     *
     * No field here needs an "ignore this record" id: unlike license plates or
     * emails, nothing on a rental is unique. The one check that does need to
     * ignore the current record — date overlap — lives outside this trait
     * (see needsApproval()/withValidator() callers) and takes the id directly.
     *
     * @return array<string, array<int, ValidationRule|array<mixed>|string>>
     */
    protected function rentalRules(): array
    {
        return [
            'user_id' => $this->userIdRules(),
            'car_id' => $this->carIdRules(),
            'start_date' => $this->startDateRules(),
            'end_date' => $this->endDateRules(),
            'daily_rate' => $this->dailyRateRules(),
        ];
    }

    /**
     * Get the validation rules used to validate the renter.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function userIdRules(): array
    {
        return ['required', 'integer', Rule::exists(User::class, 'id')];
    }

    /**
     * Get the validation rules used to validate the rented car.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function carIdRules(): array
    {
        return ['required', 'integer', Rule::exists(Car::class, 'id')];
    }

    /**
     * Get the validation rules used to validate the rental start date.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function startDateRules(): array
    {
        return ['required', 'date'];
    }

    /**
     * Get the validation rules used to validate the rental end date.
     *
     * "after_or_equal", not "after": a single-day rental has start === end.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function endDateRules(): array
    {
        return ['required', 'date', 'after_or_equal:start_date'];
    }

    /**
     * Get the validation rules used to validate the daily rate.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function dailyRateRules(): array
    {
        return ['required', 'numeric', 'min:0', 'max:9999999999.99'];
    }

    /**
     * Get the validation rules used to validate the rental status.
     *
     * @return array<int, ValidationRule|array<mixed>|string>
     */
    protected function statusRules(): array
    {
        return ['required', Rule::enum(RentalStatus::class)];
    }
}
