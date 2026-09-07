<?php

namespace App\Http\Requests\StageRequests;

use App\Http\Requests\Concerns\UsesAuthenticatedInstance;
use App\Models\Stage;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateStageRequest extends FormRequest
{
    use UsesAuthenticatedInstance;

    public function rules(): array
    {
        /** @var Stage $stage */
        $stage = $this->route('stage');
        $funnelId = $this->integer('funnel_id') ?: $stage->funnel_id;

        return [
            'instance_id' => ['required', 'integer', 'exists:instances,id'],
            'funnel_id' => ['sometimes', 'required', 'integer', Rule::exists('funnels', 'id')->where('instance_id', $this->integer('instance_id'))],
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('stages')->where('funnel_id', $funnelId)->ignore($stage)],
            'position' => ['sometimes', 'required', 'integer', 'min:1', Rule::unique('stages')->where('funnel_id', $funnelId)->ignore($stage)],
            'duration' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'color' => ['sometimes', 'nullable', 'string'],
            'duration_unit' => ['sometimes', 'nullable', Rule::in(['horas', 'dias'])],
            'is_final' => ['sometimes', 'boolean'],
        ];
    }
}
