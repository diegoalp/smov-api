<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'instance_id' => $this->instance_id,
            'funnels' => FunnelResource::collection($this->whenLoaded('funnels')),
            'name' => $this->name,
            'segment' => $this->segment,
            'description' => $this->description,
            'active' => $this->active,
            'products' => ProductResource::collection($this->whenLoaded('products')),
            'created_at' => $this->created_at, 
            'updated_at' => $this->updated_at,
        ];
    }
}
