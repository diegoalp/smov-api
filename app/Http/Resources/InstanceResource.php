<?php

namespace App\Http\Resources;

use App\Support\FileUrl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $logoUrl = FileUrl::publicUrl($request, $this->logo_url);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'expiration_date' => $this->expiration_date?->toDateString(),
            'is_expired' => $this->isExpired(),
            'branding' => [
                'companyName' => $this->name,
                'logoDataUrl' => $logoUrl,
                'primaryColor' => $this->primary_color,
                'secondaryColor' => $this->secondary_color,
                'accentColor' => $this->accent_color,
                'primaryTextColor' => $this->primary_text_color,
            ],
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
