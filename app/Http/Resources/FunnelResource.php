<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class FunnelResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'instance_id' => $this->instance_id ?? 1,
            'name' => $this->name,
            'description' => $this->description,
            'ownerTeam' => $this->owner_team,
            'color' => $this->color,
            'active' => $this->active,
            'stages' => StageResource::collection($this->whenLoaded('stages')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
