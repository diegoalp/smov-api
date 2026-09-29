<?php

namespace App\Http\Requests\ChecklistRequests;

use App\Enums\UserType;
use App\Models\Checklist;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateChecklistRequest extends FormRequest
{
    use ChecklistPayloadRules;

    public function authorize(): bool
    {
        return in_array($this->user()?->type, [UserType::Admin, UserType::Master], true);
    }

    protected function prepareForValidation(): void
    {
        $checklist = $this->route('checklist');

        if (! $checklist instanceof Checklist) {
            return;
        }

        $this->merge([
            'instance_id' => $checklist->instance_id,
            'funnel_ids' => $this->input('funnel_ids', $checklist->funnels()->pluck('funnels.id')->all()),
            'items' => $this->input('items', $checklist->items()->get(['id', 'label', 'position', 'required'])->toArray()),
            'conditions' => $this->input('conditions', $checklist->conditions()->with('products')->get()->map(fn ($condition): array => [
                'funnel_id' => $condition->funnel_id,
                'product_ids' => $condition->products->pluck('id')->all(),
                'min_stage_id' => $condition->min_stage_id,
            ])->all()),
        ]);
    }

    public function rules(): array
    {
        $rules = $this->checklistRules(true);
        $checklist = $this->route('checklist');
        $rules['items.*.id'] = [
            'sometimes',
            'integer',
            Rule::exists('checklist_items', 'id')->where('checklist_id', $checklist->id),
        ];

        return $rules;
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->validateChecklistRelationships($validator);
        }];
    }
}
