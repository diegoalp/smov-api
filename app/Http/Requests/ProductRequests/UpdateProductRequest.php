<?php

namespace App\Http\Requests\ProductRequests;

use App\Http\Requests\Concerns\UsesAuthenticatedInstance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    use UsesAuthenticatedInstance;

    public function rules(): array
    {
        return [
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            'instance_id' => ['required', 'integer', 'exists:instances,id'],
            'funnel_ids' => ['sometimes', 'required', 'array', 'min:1'],
            'funnel_ids.*' => ['integer', 'distinct', Rule::exists('funnels', 'id')->where('instance_id', $this->integer('instance_id'))],
            'prefix' => ['sometimes', 'required', 'string', 'max:30', 'alpha_dash:ascii', Rule::unique('products')->where('instance_id', $this->integer('instance_id'))->ignore($this->route('product'))],
            'color' => ['sometimes', 'required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'description' => ['sometimes', 'nullable', 'string'],
            'fields' => ['sometimes', 'array'],
            'fields.*.id' => ['required', 'string', 'max:100'],
            'fields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.type' => ['required', Rule::in(['text', 'currency', 'number', 'group'])],
            'fields.*.required' => ['required', 'boolean'],
            'fields.*.subFields' => ['sometimes', 'array'],
            'fields.*.subFields.*.label' => ['required', 'string', 'max:255'],
            'fields.*.subFields.*.type' => ['required', Rule::in(['text', 'currency', 'number'])],
            'active' => ['sometimes', 'boolean'],
            'category_ids' => ['sometimes', 'array'],
            'category_ids.*' => ['integer', 'distinct', 'exists:categories,id'],
        ];
    }
}
