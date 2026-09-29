<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ChecklistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'instance_id' => $this->instance_id,
            'title' => $this->title,
            'description' => $this->description,
            'active' => $this->active,
            'funnels' => FunnelResource::collection($this->whenLoaded('funnels')),
            'items' => ChecklistItemResource::collection($this->whenLoaded('items')),
            'conditions' => $this->whenLoaded('conditions', fn (): array => $this->conditions->map(fn ($condition): array => [
                'id' => $condition->id,
                'funnel_id' => $condition->funnel_id,
                'product_ids' => $condition->products->pluck('id')->values()->all(),
                'min_stage_id' => $condition->min_stage_id,
            ])->all()),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
