<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'instance_id' => $this->instance_id,
            'client_id' => $this->client_id,
            'user_id' => $this->user_id,
            'category_id' => $this->category_id,
            'product_id' => $this->product_id,
            'funnel_id' => $this->funnel_id,
            'stage_id' => $this->stage_id,
            'expiration_date' => $this->expiration_date,
            'status' => $this->status,
            'value' => $this->value,
            'notes' => $this->notes,
            'priority' => $this->priority,
            'dueDate' => $this->due_at,
            'lossReason' => $this->loss_reason,
            // Keep numeric field IDs: JsonResource reindexes nested PHP arrays.
            'customData' => (object) ($this->custom_data ?? []),
            'timeline' => BusinessEventResource::collection($this->whenLoaded('events')),
            'conversation' => BusinessMessageResource::collection($this->whenLoaded('messages')),
            'checklists' => $this->whenLoaded('checklistItemCompletions', fn (): array => $this->checklistsPayload()),
            'client' => new ClientResource($this->whenLoaded('client')),
            'user' => new UserResource($this->whenLoaded('user')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'product' => new ProductResource($this->whenLoaded('product')),
            'funnel' => new FunnelResource($this->whenLoaded('funnel')),
            'stage' => new StageResource($this->whenLoaded('stage')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }

    private function checklistsPayload(): array
    {
        return $this->checklistItemCompletions
            ->filter(fn ($completion): bool => $completion->item?->checklist !== null)
            ->groupBy(fn ($completion): int => $completion->item->checklist_id)
            ->map(function ($completions): array {
                $checklist = $completions->first()->item->checklist;
                $items = $completions
                    ->map(function ($completion) {
                        $item = $completion->item;
                        $item->setRelation('completions', collect([$completion]));

                        return $item;
                    })
                    ->sortBy('position')
                    ->values();

                $checklist->setRelation('items', $items);

                return (new BusinessChecklistResource($checklist))->resolve(request());
            })
            ->values()
            ->all();
    }
}
