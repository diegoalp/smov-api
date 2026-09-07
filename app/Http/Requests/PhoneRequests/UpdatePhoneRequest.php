<?php

namespace App\Http\Requests\PhoneRequests;

use App\Models\Phone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePhoneRequest extends FormRequest
{
    public function rules(): array
    {
        /** @var Phone $phone */
        $phone = $this->route('phone');
        $clientId = $this->integer('client_id') ?: $phone->client_id;

        return [
            'client_id' => ['sometimes', 'required', 'integer', 'exists:clients,id'],
            'number' => [
                'required_with:client_id', 'string', 'max:20', 'regex:/^\+?[0-9 ()-]{8,20}$/',
                Rule::unique('phones')->where('client_id', $clientId)->ignore($phone),
            ],
            'whatsapp' => ['sometimes', 'boolean'],
        ];
    }
}
