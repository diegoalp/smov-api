<?php

namespace App\Http\Requests\ClientRequests;

use Illuminate\Foundation\Http\FormRequest;

class StoreClientRequest extends FormRequest
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
            'fullname' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:individual,company'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'registration' => ['required', 'string', 'digits_between:11,14', 'unique:clients,registration'],
            'rg' => ['nullable', 'string', 'max:20'],
            'street' => ['nullable', 'string', 'max:255'],
            'district' => ['nullable', 'string', 'max:255'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'size:2'],
            'zipcode' => ['nullable', 'string', 'max:9'],
            'gender' => ['nullable', 'string', 'max:30'],
            'extra' => ['nullable', 'array'],
        ];
    }
}
