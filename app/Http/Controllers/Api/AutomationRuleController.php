<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InstanceCrudController;
use App\Models\AutomationRule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class AutomationRuleController extends InstanceCrudController
{
    protected string $modelClass = AutomationRule::class;

    protected function rules(Request $request, ?Model $model = null): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'], 'trigger' => ['sometimes', 'required', 'string', 'max:255'],
            'condition' => ['nullable', 'string'], 'action' => ['sometimes', 'required', 'string'], 'active' => ['sometimes', 'boolean'],
            'instance_id' => ['sometimes', 'integer', 'exists:instances,id'],
        ];
    }
}
