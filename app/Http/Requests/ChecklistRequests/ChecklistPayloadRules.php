<?php

namespace App\Http\Requests\ChecklistRequests;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ChecklistPayloadRules
{
    protected function checklistRules(bool $updating = false): array
    {
        $instanceId = $this->integer('instance_id');
        $checklist = $this->route('checklist');

        return [
            'instance_id' => ['required', 'integer', 'exists:instances,id'],
            'title' => [
                $updating ? 'sometimes' : 'required',
                'string',
                'max:255',
                Rule::unique('checklists', 'title')
                    ->where('instance_id', $instanceId)
                    ->ignore($checklist?->id),
            ],
            'description' => ['sometimes', 'nullable', 'string'],
            'active' => ['sometimes', 'boolean'],
            'funnel_ids' => ['required', 'array', 'min:1'],
            'funnel_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('funnels', 'id')
                    ->where('instance_id', $instanceId)
                    ->whereNull('deleted_at'),
            ],
            'items' => ['sometimes', 'array'],
            'items.*.label' => ['required', 'string', 'max:255'],
            'items.*.position' => ['required', 'integer', 'min:1', 'distinct'],
            'items.*.required' => ['required', 'boolean'],
            'conditions' => ['sometimes', 'array'],
            'conditions.*.funnel_id' => [
                'required',
                'integer',
                Rule::exists('funnels', 'id')
                    ->where('instance_id', $instanceId)
                    ->whereNull('deleted_at'),
            ],
            'conditions.*.product_ids' => ['sometimes', 'nullable', 'array'],
            'conditions.*.product_ids.*' => [
                'integer',
                'distinct',
                Rule::exists('products', 'id')
                    ->where('instance_id', $instanceId)
                    ->whereNull('deleted_at'),
            ],
            'conditions.*.min_stage_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('stages', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    protected function validateChecklistRelationships(Validator $validator): void
    {
        $funnelIds = array_map('intval', $this->input('funnel_ids', []));
        $unrestrictedFunnels = [];

        foreach ($this->input('conditions', []) ?? [] as $index => $condition) {
            $funnelId = (int) ($condition['funnel_id'] ?? 0);

            if (! in_array($funnelId, $funnelIds, true)) {
                $validator->errors()->add("conditions.$index.funnel_id", 'A condição deve usar um funil associado ao checklist.');

                continue;
            }

            if (empty($condition['product_ids'])) {
                if (in_array($funnelId, $unrestrictedFunnels, true)) {
                    $validator->errors()->add("conditions.$index.product_ids", 'Não pode haver mais de uma condição sem produto para o mesmo funil.');
                }
                $unrestrictedFunnels[] = $funnelId;
            } elseif (in_array($funnelId, $unrestrictedFunnels, true)) {
                $validator->errors()->add("conditions.$index.product_ids", 'Uma condição sem produto já cobre todos os produtos deste funil.');
            }

            if (! empty($condition['min_stage_id'])) {
                $stageMatchesFunnel = \DB::table('stages')
                    ->where('id', (int) $condition['min_stage_id'])
                    ->where('funnel_id', $funnelId)
                    ->whereNull('deleted_at')
                    ->exists();

                if (! $stageMatchesFunnel) {
                    $validator->errors()->add("conditions.$index.min_stage_id", 'A etapa mínima deve pertencer ao funil da condição.');
                }
            }

            foreach ($condition['product_ids'] ?? [] as $productIndex => $productId) {
                $productMatchesFunnel = \DB::table('funnel_product')
                    ->where('funnel_id', $funnelId)
                    ->where('product_id', (int) $productId)
                    ->exists();

                if (! $productMatchesFunnel) {
                    $validator->errors()->add("conditions.$index.product_ids.$productIndex", 'O produto deve estar associado ao funil da condição.');
                }
            }
        }

        $this->validateProductChecklistUniqueness($validator);
    }

    private function validateProductChecklistUniqueness(Validator $validator): void
    {
        $checklist = $this->route('checklist');
        $currentChecklistId = $checklist?->id;
        $instanceId = $this->integer('instance_id');
        $unrestrictedFunnels = collect($this->input('conditions', []) ?? [])
            ->filter(fn (array $condition): bool => empty($condition['product_ids']))
            ->pluck('funnel_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();
        $conditions = $this->input('conditions', []) ?? [];

        if ($conditions === []) {
            foreach (array_map('intval', $this->input('funnel_ids', [])) as $funnelId) {
                $hasChecklist = \DB::table('checklist_funnel as existing_funnel')
                    ->join('checklists as existing_checklist', 'existing_checklist.id', '=', 'existing_funnel.checklist_id')
                    ->where('existing_funnel.funnel_id', $funnelId)
                    ->where('existing_checklist.instance_id', $instanceId)
                    ->whereNull('existing_checklist.deleted_at')
                    ->when($currentChecklistId, fn ($query) => $query->where('existing_checklist.id', '!=', $currentChecklistId))
                    ->exists();

                if ($hasChecklist) {
                    $validator->errors()->add('funnel_ids', 'Cada produto pode estar associado a somente um checklist por funil.');
                }
            }
        }

        $seenProductIds = [];
        foreach ($conditions as $index => $condition) {
            $productIds = array_map('intval', $condition['product_ids'] ?? []);
            $funnelId = (int) ($condition['funnel_id'] ?? 0);

            if ($productIds === []) {
                $hasExplicitConflict = \DB::table('checklist_conditions as existing_condition')
                    ->join('checklists as existing_checklist', 'existing_checklist.id', '=', 'existing_condition.checklist_id')
                    ->join('checklist_condition_product as existing_product', 'existing_product.condition_id', '=', 'existing_condition.id')
                    ->where('existing_checklist.instance_id', $instanceId)
                    ->where('existing_condition.funnel_id', $funnelId)
                    ->whereNull('existing_checklist.deleted_at')
                    ->when($currentChecklistId, fn ($query) => $query->where('existing_checklist.id', '!=', $currentChecklistId))
                    ->exists();

                if ($hasExplicitConflict || $unrestrictedFunnels->filter(fn (int $id): bool => $id === $funnelId)->count() > 1) {
                    $validator->errors()->add("conditions.$index.product_ids", 'Este funil já possui um checklist associado a produtos específicos.');
                }

                continue;
            }

            $hasUnrestrictedConflict = \DB::table('checklist_conditions as existing_condition')
                ->join('checklists as existing_checklist', 'existing_checklist.id', '=', 'existing_condition.checklist_id')
                ->where('existing_checklist.instance_id', $instanceId)
                ->where('existing_condition.funnel_id', $funnelId)
                ->whereNull('existing_checklist.deleted_at')
                ->whereNotExists(function ($query): void {
                    $query->selectRaw('1')
                        ->from('checklist_condition_product as existing_product')
                        ->whereColumn('existing_product.condition_id', 'existing_condition.id');
                })
                ->when($currentChecklistId, fn ($query) => $query->where('existing_checklist.id', '!=', $currentChecklistId))
                ->exists();

            if ($hasUnrestrictedConflict || $unrestrictedFunnels->contains($funnelId)) {
                $validator->errors()->add("conditions.$index.product_ids", 'Uma condição sem produto já cobre todos os produtos deste funil.');
            }

            $hasExplicitConflict = \DB::table('checklist_condition_product as existing_product')
                ->join('checklist_conditions as existing_condition', 'existing_condition.id', '=', 'existing_product.condition_id')
                ->join('checklists as existing_checklist', 'existing_checklist.id', '=', 'existing_condition.checklist_id')
                ->where('existing_checklist.instance_id', $instanceId)
                ->whereIn('existing_product.product_id', $productIds)
                ->whereNull('existing_checklist.deleted_at')
                ->when($currentChecklistId, fn ($query) => $query->where('existing_checklist.id', '!=', $currentChecklistId))
                ->exists();

            if ($hasExplicitConflict) {
                $validator->errors()->add("conditions.$index.product_ids", 'Um dos produtos já possui outro checklist associado.');
            }

            foreach ($productIds as $productId) {
                if (in_array($productId, $seenProductIds, true)) {
                    $validator->errors()->add("conditions.$index.product_ids", 'Um produto não pode aparecer em mais de uma condição do mesmo checklist.');
                }
                $seenProductIds[] = $productId;
            }
        }
    }
}
