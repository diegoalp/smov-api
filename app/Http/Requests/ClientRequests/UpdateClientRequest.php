<?php

namespace App\Http\Requests\ClientRequests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateClientRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        if ($this->has('registration')) {
            $this->merge(['registration' => preg_replace('/\D+/', '', (string) $this->input('registration'))]);
        }
    }

    public function rules(): array
    {
        return [
            'fullname' => ['sometimes', 'required', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'string', 'in:individual,company'],
            'birthdate' => ['sometimes', 'nullable', 'date', 'before:today'],
            'registration' => ['sometimes', 'required', 'string', 'digits_between:11,14', Rule::unique('clients')->ignore($this->route('client'))],
            'rg' => ['sometimes', 'nullable', 'string', 'max:20'],
            'street' => ['sometimes', 'nullable', 'string', 'max:255'],
            'district' => ['sometimes', 'nullable', 'string', 'max:255'],
            'city' => ['sometimes', 'nullable', 'string', 'max:255'],
            'state' => ['sometimes', 'nullable', 'string', 'size:2'],
            'zipcode' => ['sometimes', 'nullable', 'string', 'max:9'],
            'gender' => ['sometimes', 'nullable', 'string', 'max:30'],
            'extra' => ['sometimes', 'nullable', 'array'],
        ];
    }
}
