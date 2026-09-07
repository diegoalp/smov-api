<?php

namespace App\Http\Requests\InstanceRequests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInstanceRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'expiration_date' => [Rule::prohibitedIf($this->user()?->type !== \App\Enums\UserType::Master),
                'sometimes','required','date_format:Y-m-d','after_or_equal:'.now(config('crm.timezone'))->toDateString()],

            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('instances', 'name')->ignore($this->route('instance')),
            ],
        ];
    }
}
