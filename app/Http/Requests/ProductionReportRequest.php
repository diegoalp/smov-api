<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProductionReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->type?->value, ['admin', 'master'], true);
    }

    public function rules(): array
    {
        return [
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'product_id' => ['nullable', 'integer', 'exists:products,id'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'funnel_id' => ['nullable', 'integer', 'exists:funnels,id'],
            'stage_id' => ['nullable', 'integer', 'exists:stages,id'],
            'lead_source_id' => ['nullable', 'integer', 'exists:lead_sources,id'],
            'disposition_id' => ['nullable', 'integer', 'exists:dispositions,id'],
            'status' => ['nullable', 'integer', 'in:1,2,3'],
            'custom_filters' => ['nullable', 'json'],
            'sum_fields' => ['nullable', 'string'],
        ];
    }

    public function customFilters(): array
    {
        $filters = $this->input('custom_filters');
        if (is_string($filters)) {
            $filters = json_decode($filters, true);
        }

        return is_array($filters) ? $filters : [];
    }

    public function sumFieldIds(): array
    {
        return collect(explode(',', (string) $this->input('sum_fields')))
            ->filter(fn (string $id): bool => ctype_digit($id))
            ->map(fn (string $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
