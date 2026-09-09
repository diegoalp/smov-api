<?php

namespace App\Http\Resources;

use App\Support\FileUrl;
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
            'profile_photo_path' => $this->profile_photo_path,
            'profile_photo_url' => FileUrl::publicUrl($request, $this->profile_photo_path),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
