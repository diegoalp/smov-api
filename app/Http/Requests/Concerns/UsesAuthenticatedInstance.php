<?php

namespace App\Http\Requests\Concerns;

trait UsesAuthenticatedInstance
{
    protected function prepareForValidation(): void
    {
        foreach ([
            'categoryIds' => 'category_ids', 'funnelIds' => 'funnel_ids',
            'ownerTeam' => 'owner_team', 'dueDate' => 'due_at',
            'lossReason' => 'loss_reason', 'customData' => 'custom_data',
            'sourceId' => 'lead_source_id', 'dispositionId' => 'disposition_id',
        ] as $from => $to) {
            if ($this->has($from) && ! $this->has($to)) {
                $this->merge([$to => $this->input($from)]);
            }
        }

        if ($this->has('name') && ! $this->has('title') && str_contains(static::class, 'ProductRequest')) {
            $this->merge(['title' => $this->input('name')]);
        }

        // Normalize product fields written by older frontend versions.
        if (str_contains(static::class, 'ProductRequest') && $this->has('fields')) {
            $fields = collect($this->input('fields', []))->map(function (array $field): array {
                $field['type'] ??= $field['tipo'] ?? null;
                $field['required'] ??= $field['obrigatorio'] ?? false;
                $field['subFields'] ??= $field['subCampos'] ?? [];
                $field['subFields'] = collect($field['subFields'])->map(function (array $subField): array {
                    $subField['type'] ??= $subField['tipo'] ?? null;

                    return $subField;
                })->all();

                unset($field['tipo'], $field['obrigatorio'], $field['subCampos']);

                return $field;
            })->all();

            $this->merge(['fields' => $fields]);
        }

        if ($this->user()?->instance_id !== null) {
            $this->merge(['instance_id' => $this->user()->instance_id]);
        }
    }
}
