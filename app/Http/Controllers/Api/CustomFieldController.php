<?php

namespace App\Http\Controllers\Api;

use App\Models\Category;
use App\Http\Controllers\Api\Concerns\InstanceCrudController;
use App\Models\CustomField;
use App\Models\Funnel;
use App\Models\Product;
use App\Support\InstanceContext;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomFieldController extends InstanceCrudController
{
    protected string $modelClass = CustomField::class;

    protected function rules(Request $request, ?Model $model = null): array
    {
        $presence = $model ? 'sometimes' : 'required';
        $fieldType = $request->input('type', $model?->type);
        $section = $request->input('section', $model?->section);
        $requiresTypeConfiguration = $model === null || $request->has('type');

        return [
            'label' => [$presence, 'string', 'max:255'],
            'section' => [$presence, Rule::in(['business', 'product', 'client'])],
            'custom_field_section_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('custom_field_sections', 'id')
                    ->where('instance_id', InstanceContext::id($request))
                    ->where('section', $section),
            ],
            'type' => [$presence, Rule::in(['text', 'textarea', 'currency', 'number', 'group', 'date', 'select', 'file', 'phone', 'document', 'checkbox'])],
            'required' => ['sometimes', 'boolean'],
            'default_value' => [Rule::requiredIf($requiresTypeConfiguration && $fieldType === 'checkbox'), 'nullable', 'boolean'],
            'visible_when' => ['sometimes', 'string', 'max:255'],
            'conditions' => ['nullable', 'array'],
            'conditions.*.field' => [
                'required', 'string', Rule::in(['category_id', 'product_id', 'funnel_id']),
                function (string $attribute, mixed $value, Closure $fail) use ($request, $model): void {
                    $section = $request->input('section', $model?->section);
                    if (in_array($value, ['product_id', 'funnel_id'], true) && $section !== 'business') {
                        $fail('Condições de produto ou funil são permitidas somente em campos de negócio.');
                    }
                },
            ],
            'conditions.*.operator' => ['required', 'string', Rule::in(['equals', 'not_equals'])],
            'conditions.*.value' => ['required', 'array', 'min:1'],
            'conditions.*.value.*' => [
                'required', 'integer', 'distinct',
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    preg_match('/conditions\.(\d+)\.value\.\d+/', $attribute, $matches);
                    $field = $request->input('conditions.'.($matches[1] ?? 0).'.field');
                    $modelClass = match ($field) {
                        'category_id' => Category::class,
                        'product_id' => Product::class,
                        'funnel_id' => Funnel::class,
                        default => null,
                    };

                    if ($modelClass && ! $modelClass::query()
                        ->where('instance_id', InstanceContext::id($request))
                        ->whereKey($value)
                        ->exists()) {
                        $fail('A opção selecionada não existe nesta instância.');
                    }
                },
            ],
            'options' => [Rule::requiredIf($requiresTypeConfiguration && $fieldType === 'select'), 'nullable', 'array', 'min:1'],
            'options.*' => ['required', 'string', 'max:255', 'distinct'],
            'sub_fields' => [Rule::requiredIf($requiresTypeConfiguration && $fieldType === 'group'), 'nullable', 'array', 'min:1'],
            'sub_fields.*.key' => ['required', 'string', 'max:100', 'distinct', 'regex:/^[a-zA-Z][a-zA-Z0-9_]*$/'],
            'sub_fields.*.label' => ['required', 'string', 'max:255'],
            'sub_fields.*.type' => ['required', Rule::in(['text', 'textarea', 'currency', 'number', 'date', 'select', 'file', 'phone', 'document', 'checkbox'])],
            'sub_fields.*.required' => ['required', 'boolean'],
            'sub_fields.*.options' => [
                'present', 'array',
                function (string $attribute, mixed $value, Closure $fail) use ($request): void {
                    preg_match('/sub_fields\.(\d+)\.options/', $attribute, $matches);
                    $type = $request->input('sub_fields.'.($matches[1] ?? 0).'.type');
                    if ($type === 'select' && count($value) === 0) {
                        $fail('Subcampos do tipo seleção precisam ter ao menos uma opção.');
                    }
                },
            ],
            'sub_fields.*.options.*' => ['required', 'string', 'max:255', 'distinct'],
            'sub_fields.*.position' => ['required', 'integer', 'min:0', 'distinct'],
            'position' => ['sometimes', 'integer', 'min:0'], 'active' => ['sometimes', 'boolean'],
            'instance_id' => ['sometimes', 'integer', 'exists:instances,id'],
        ];
    }
}
