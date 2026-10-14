<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SavedReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->type?->value, ['admin', 'master'], true);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'filters' => ['required', 'array'],
            'filters.from' => ['required', 'date_format:Y-m-d'],
            'filters.to' => ['required', 'date_format:Y-m-d', 'after_or_equal:filters.from'],
            'filters.userId' => ['nullable', 'string', 'max:30'],
            'filters.productId' => ['nullable', 'string', 'max:30'],
            'filters.customFilters' => ['nullable', 'array', 'max:20'],
            'filters.sumFieldIds' => ['nullable', 'array', 'max:30'],
        ];
    }
}
