<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InstanceCrudController;
use App\Models\LeadSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class LeadSourceController extends InstanceCrudController
{
    protected string $modelClass = LeadSource::class;

    protected function rules(Request $request, ?Model $model = null): array
    {
        $instanceId = $request->user()->instance_id ?? $request->integer('instance_id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('lead_sources')->where('instance_id', $instanceId)->ignore($model)],
            'type' => ['sometimes', 'required', Rule::in(['manual', 'automatica'])],
            'description' => ['nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
            'instance_id' => ['sometimes', 'integer', 'exists:instances,id'],
        ];
    }
}
