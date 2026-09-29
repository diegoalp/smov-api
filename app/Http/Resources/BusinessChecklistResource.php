<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessChecklistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'items' => $this->items->map(function ($item): array {
                $completion = $item->completions->first();
                $completedBy = null;

                if ($completion?->done) {
                    $completedBy = $completion->histories
                        ->firstWhere('action', 'checklist_item_completed')
                        ?->user_id;
                }

                return [
                    'id' => $item->id,
                    'label' => $item->label,
                    'position' => $item->position,
                    'required' => $item->required,
                    'done' => (bool) ($completion?->done ?? false),
                    'completed_at' => $completion?->completed_at,
                    'completed_by' => $completedBy,
                ];
            })->values()->all(),
        ];
    }
}
