<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InstanceCrudController;
use App\Models\CustomFieldSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomFieldSectionController extends InstanceCrudController
{
    protected string $modelClass = CustomFieldSection::class;

    protected function query(Request $request): Builder
    {
        return parent::query($request)->orderBy('section')->orderBy('position')->orderBy('name');
    }

    protected function rules(Request $request, ?Model $model = null): array
    {
        $instanceId = $request->user()->instance_id ?? $request->integer('instance_id');
        $section = $request->input('section', $model?->section);

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('custom_field_sections')->where('instance_id', $instanceId)->where('section', $section)->ignore($model)],
            'section' => ['sometimes', 'required', Rule::in(['business', 'product', 'client'])],
            'position' => ['sometimes', 'integer', 'min:0'],
            'instance_id' => ['sometimes', 'integer', 'exists:instances,id'],
        ];
    }
}
