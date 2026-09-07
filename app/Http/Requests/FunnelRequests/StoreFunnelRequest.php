<?php

namespace App\Http\Requests\FunnelRequests;

use App\Http\Requests\Concerns\UsesAuthenticatedInstance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFunnelRequest extends FormRequest
{
    use UsesAuthenticatedInstance;

    public function rules(): array
    {
        return [
            'instance_id' => ['required', 'integer', 'exists:instances,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('funnels')->where('instance_id', $this->integer('instance_id'))],
            'description' => ['nullable', 'string'],
            'owner_team' => ['sometimes', 'nullable', 'string', 'max:255'],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'active' => ['sometimes', 'boolean'],
        ];
    }
}
