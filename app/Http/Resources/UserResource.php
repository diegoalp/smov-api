<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'lastname' => $this->lastname,
            'birthdate' => $this->birthdate?->toDateString(),
            'email' => $this->email,
            'type' => $this->type?->value,
            'instance_id' => $this->instance_id,
            'team_id' => $this->team_id,
            'funnel_id' => $this->funnel_id,
            'supervisor_id' => $this->supervisor_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
