<?php

namespace App\Http\Requests\InstanceRequests;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInstanceRequest extends FormRequest
{
    public function authorize(): bool {
        return in_array($this->user()?->type, [\App\Enums\UserType::Admin, \App\Enums\UserType::Master], true);
    }
    public function rules(): array
    {
        return [
            'is_principal' => [
                'sometimes',
                'boolean',
                Rule::prohibitedIf(fn (): bool => $this->user()?->type !== UserType::Master && $this->boolean('is_principal')),
            ],
            'expiration_date' => [
                Rule::prohibitedIf(fn (): bool => $this->isPrincipal() && $this->input('expiration_date') !== null),
                Rule::prohibitedIf($this->user()?->type !== UserType::Master),
                Rule::requiredIf(fn (): bool => $this->user()?->type === UserType::Master && ! $this->isPrincipal()),
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:'.now(config('crm.timezone'))->toDateString(),
            ],

            'name' => ['required', 'string', 'max:255', 'unique:instances,name'],
            'primaryColor' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondaryColor' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accentColor' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'primaryTextColor' => ['sometimes', 'nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ];
    }

    private function isPrincipal(): bool
    {
        return $this->user()?->type === UserType::Master && $this->boolean('is_principal');
    }
}
