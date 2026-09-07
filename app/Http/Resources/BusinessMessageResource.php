<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessMessageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'sender' => $this->sender, 'text' => $this->text,
            'timestamp' => $this->created_at?->format('H:i'), 'createdAt' => $this->created_at,
        ];
    }
}
