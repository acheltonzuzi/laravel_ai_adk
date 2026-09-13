<?php

namespace App\Http\Requests;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateUserRequest extends FormRequest
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Get the validation rules that apply to the request.
     *
     * The password is only validated when the form actually sets a new one.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $passwordRules = array_values(array_filter(
            $this->passwordRules(),
            fn (mixed $rule): bool => $rule !== 'required',
        ));

        return [
            ...$this->profileRules($this->route('user')->id),
            'password' => ['nullable', ...$passwordRules],
        ];
    }
}
