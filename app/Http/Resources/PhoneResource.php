<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PhoneResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'client_id' => $this->client_id,
            'number' => $this->number,
            'whatsapp' => $this->whatsapp,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
