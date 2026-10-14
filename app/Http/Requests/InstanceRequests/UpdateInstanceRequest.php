<?php

namespace App\Http\Requests\InstanceRequests;

use App\Enums\UserType;
use App\Models\Instance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInstanceRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('is_master') && ! $this->has('is_principal')) {
            $this->merge(['is_principal' => $this->input('is_master')]);
        }
    }

    public function rules(): array
    {
        return [
            'is_master' => [
                'sometimes',
                'boolean',
                Rule::prohibitedIf($this->user()?->type !== UserType::Master),
            ],
            'is_principal' => [
                'sometimes',
                'boolean',
                Rule::prohibitedIf($this->user()?->type !== UserType::Master),
            ],
            'expiration_date' => [
                Rule::prohibitedIf(fn (): bool => $this->targetIsPrincipal() && $this->input('expiration_date') !== null),
                Rule::prohibitedIf($this->user()?->type !== UserType::Master),
                Rule::requiredIf(fn (): bool => $this->user()?->type === UserType::Master
                    && $this->has('is_principal')
                    && ! $this->boolean('is_principal')),
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:'.now(config('crm.timezone'))->toDateString(),
            ],

            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('instances', 'name')->ignore($this->route('instance')),
            ],
        ];
    }

    private function targetIsPrincipal(): bool
    {
        if ($this->has('is_principal')) {
            return $this->boolean('is_principal');
        }

        $instance = $this->route('instance');

        return $instance instanceof Instance && $instance->is_principal;
    }
}
