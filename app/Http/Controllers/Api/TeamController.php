<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InstanceCrudController;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeamController extends InstanceCrudController
{
    protected string $modelClass = Team::class;

    protected function rules(Request $request, ?Model $model = null): array
    {
        $instanceId = $request->user()->instance_id ?? $request->integer('instance_id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('teams')->where('instance_id', $instanceId)->ignore($model)],
            'description' => ['nullable', 'string'], 'instance_id' => ['sometimes', 'integer', 'exists:instances,id'],
        ];
    }
}
