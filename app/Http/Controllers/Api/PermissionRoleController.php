<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InstanceCrudController;
use App\Models\PermissionRole;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PermissionRoleController extends InstanceCrudController
{
    protected string $modelClass = PermissionRole::class;

    protected function rules(Request $request, ?Model $model = null): array
    {
        $instanceId = $request->user()->instance_id ?? $request->integer('instance_id');

        return [
            'key' => ['sometimes', 'required', 'alpha_dash', 'max:50', Rule::unique('permission_roles')->where('instance_id', $instanceId)->ignore($model)],
            'name' => ['sometimes', 'required', 'string', 'max:255'], 'description' => ['nullable', 'string'],
            'permissions' => ['sometimes', 'required', 'array'], 'permissions.*' => ['boolean'],
            'instance_id' => ['sometimes', 'integer', 'exists:instances,id'],
        ];
    }
}
