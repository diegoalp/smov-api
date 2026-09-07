<?php

namespace App\Http\Requests\InstanceRequests;

use Illuminate\Foundation\Http\FormRequest;

class StoreInstanceRequest extends FormRequest
{
    public function authorize(): bool {
        return in_array($this->user()?->type, [\App\Enums\UserType::Admin, \App\Enums\UserType::Master], true);
    }
    public function rules(): array
    {
        return [
            'expiration_date' => [\Illuminate\Validation\Rule::prohibitedIf($this->user()?->type !== \App\Enums\UserType::Master),
                \Illuminate\Validation\Rule::requiredIf($this->user()?->type === \App\Enums\UserType::Master),'date_format:Y-m-d','after_or_equal:'.now(config('crm.timezone'))->toDateString()],

            'name' => ['required', 'string', 'max:255', 'unique:instances,name'],
            'primaryColor' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondaryColor' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accentColor' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'primaryTextColor' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }
}
