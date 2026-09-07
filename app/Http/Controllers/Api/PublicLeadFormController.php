<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\InstanceCrudController;
use App\Models\PublicLeadForm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PublicLeadFormController extends InstanceCrudController
{
    protected string $modelClass = PublicLeadForm::class;

    protected function rules(Request $request, ?Model $model = null): array
    {
        $instanceId = $request->user()->instance_id ?? $request->integer('instance_id');

        return [
            'name' => ['sometimes', 'required', 'string', 'max:255'], 'headline' => ['sometimes', 'required', 'string', 'max:255'],
            'channel' => ['nullable', 'string', 'max:255'],
            'funnel_id' => ['sometimes', 'required', 'integer', Rule::exists('funnels', 'id')->where('instance_id', $instanceId)],
            'fields' => ['sometimes', 'required', 'array', 'min:1'], 'fields.*' => ['string', 'max:255'],
            'active' => ['sometimes', 'boolean'], 'instance_id' => ['sometimes', 'integer', 'exists:instances,id'],
        ];
    }
}
