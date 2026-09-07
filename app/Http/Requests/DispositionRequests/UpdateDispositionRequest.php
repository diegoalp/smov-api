<?php

namespace App\Http\Requests\DispositionRequests;

use App\Http\Requests\Concerns\UsesAuthenticatedInstance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateDispositionRequest extends FormRequest
{
    use UsesAuthenticatedInstance;

    public function rules(): array
    {
        return [
            'instance_id' => ['required', 'integer', 'exists:instances,id'],
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('dispositions')
                    ->where('instance_id', $this->integer('instance_id'))
                    ->ignore($this->route('disposition')),
            ],
        ];
    }
}
