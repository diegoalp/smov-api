<?php

namespace App\Http\Requests\CategoryRequests;

use App\Http\Requests\Concerns\UsesAuthenticatedInstance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCategoryRequest extends FormRequest
{
    use UsesAuthenticatedInstance;

    public function rules(): array
    {
        return [
            'instance_id' => ['required', 'integer', 'exists:instances,id'],
            'funnel_ids' => ['sometimes', 'required', 'array', 'min:1'],
            'funnel_ids.*' => [
                'integer', 'distinct',
                Rule::exists('funnels', 'id')->when(
                    $this->integer('instance_id') > 0,
                    fn ($rule) => $rule->where('instance_id', $this->integer('instance_id')),
                ),
            ],
            'name' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('categories')->where('instance_id', $this->integer('instance_id'))->ignore($this->route('category'))],
            'segment' => ['sometimes', 'nullable', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
            'product_ids' => ['sometimes', 'array'],
            'product_ids.*' => [
                'integer', 'distinct',
                Rule::exists('products', 'id')->when(
                    $this->user()?->instance_id !== null,
                    fn ($rule) => $rule->where('instance_id', $this->user()->instance_id),
                ),
            ],
        ];
    }
}
