<?php
namespace App\Http\Resources;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
class ActivityResource extends JsonResource {
    public function toArray(Request $request): array {
        return [
            'id' => $this->id, 'business_id' => $this->business_id, 'instance_id' => $this->instance_id,
            'user_id' => $this->user_id, 'activity_type_id' => $this->activity_type_id,
            'title' => $this->title, 'description' => $this->description,
            'scheduled_at' => $this->scheduled_at->toISOString(), 'status' => $this->status,
            'completed_at' => $this->completed_at?->toISOString(),
            'activity_type' => new ActivityTypeResource($this->whenLoaded('activityType')),
            'user' => new UserResource($this->whenLoaded('user')),
            'business_name' => $this->business?->client?->fullname,
        ];
    }
}
