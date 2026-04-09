<?php

namespace App\Http\Requests\Settings;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // New fields added to user profile should be also be added to resources/js/types/index.d.ts - interface called User
        return [
            'title' => ['required', 'string', 'max:5',
                Rule::in(['Mr', 'Mrs', 'Miss', 'Ms', 'Mx', 'Other']),
            ],
            'name' => ['required', 'string', 'max:255'],
            'house_name_number' => ['required', 'string', 'max:100'],
            'street_name' => ['required', 'string', 'max:100'],
            'locality_name' => ['nullable', 'string', 'max:100'],
            'city' => ['required', 'string', 'max:100'],
            'post_code' => ['required', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
            'mobile' => ['nullable', 'string', 'max:20'],
            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],
        ];
    }
}
