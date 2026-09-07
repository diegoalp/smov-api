<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'instance_id' => $this->instance_id,
            'funnels' => FunnelResource::collection($this->whenLoaded('funnels')),
            'title' => $this->title,
            'name' => $this->title,
            'prefix' => $this->prefix,
            'color' => $this->color,
            'description' => $this->description,
            'fields' => $this->fields ?? [],
            'active' => $this->active,
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
