<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InstanceCrudController;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TaskController extends InstanceCrudController
{
    protected string $modelClass = Task::class;

    protected function query(Request $request): Builder
    {
        return parent::query($request)->with(['business.client', 'owner']);
    }

    protected function rules(Request $request, ?Model $model = null): array
    {
        $instanceId = $request->user()->instance_id ?? $request->integer('instance_id');

        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'business_id' => ['nullable', 'integer', Rule::exists('businesses', 'id')->where('instance_id', $instanceId)],
            'owner_id' => ['nullable', 'integer', Rule::exists('users', 'id')->where('instance_id', $instanceId)],
            'due_at' => ['nullable', 'date'], 'priority' => ['sometimes', Rule::in(['low', 'medium', 'high'])],
            'status' => ['sometimes', Rule::in(['open', 'completed', 'cancelled'])],
            'instance_id' => ['sometimes', 'integer', 'exists:instances,id'],
        ];
    }
}
