<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ActivityTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id, 'activity_type' => $this->activity_type, 'funnel_ids' => $this->funnels->modelKeys(), 'instance_id' => $this->instance_id, 'created_at' => $this->created_at, 'updated_at' => $this->updated_at,
        ];
    }
}
