<?php

namespace App\Http\Requests\PhoneRequests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePhoneRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'number' => [
                'required', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{8,20}$/',
                Rule::unique('phones')->where('client_id', $this->integer('client_id')),
            ],
            'whatsapp' => ['sometimes', 'boolean'],
        ];
    }
}
