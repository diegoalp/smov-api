<?php

namespace App\Http\Requests\StageRequests;

use App\Http\Requests\Concerns\UsesAuthenticatedInstance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStageRequest extends FormRequest
{
    use UsesAuthenticatedInstance;

    public function rules(): array
    {
        return [
            'instance_id' => ['required', 'integer', 'exists:instances,id'],
            'funnel_id' => ['required', 'integer', Rule::exists('funnels', 'id')->where('instance_id', $this->integer('instance_id'))],
            'name' => ['required', 'string', 'max:255', Rule::unique('stages')->where('funnel_id', $this->integer('funnel_id'))],
            'position' => ['required', 'integer', 'min:1', Rule::unique('stages')->where('funnel_id', $this->integer('funnel_id'))],
            'duration' => ['nullable', 'integer', 'min:1'],
            'duration_unit' => ['nullable', Rule::in(['horas', 'dias'])],
            'color' => ['nullable', 'string', 'max:16'],
            'is_final' => ['sometimes', 'boolean'],
        ];
    }
}
