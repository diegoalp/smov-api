<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessEventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'date' => $this->created_at, 'title' => $this->title,
            'type' => $this->type, 'userName' => $this->user?->name ?? 'Sistema', 'metadata' => $this->metadata,
        ];
    }
}
