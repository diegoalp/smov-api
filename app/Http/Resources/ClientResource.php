<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'fullname' => $this->fullname,
            'type' => $this->type,
            'birthdate' => $this->birthdate?->toDateString(),
            'registration' => $this->registration,
            'rg' => $this->rg,
            'street' => $this->street,
            'district' => $this->district,
            'city' => $this->city,
            'state' => $this->state,
            'zipcode' => $this->zipcode,
            'gender' => $this->gender,
            'extra' => $this->extra,
            'phones' => PhoneResource::collection($this->whenLoaded('phones')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
