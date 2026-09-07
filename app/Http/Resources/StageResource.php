<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'funnel_id' => $this->funnel_id,
            'name' => $this->name,
            'position' => $this->position,
            'duration' => $this->duration,
            'durationUnit' => $this->duration_unit,
            'color' => $this->color,
            'is_final' => $this->is_final,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
