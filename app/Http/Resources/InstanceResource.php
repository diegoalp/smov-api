<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InstanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $logoUrl = match (true) {
            ! $this->logo_url, str_starts_with($this->logo_url, 'data:') => null,
            str_starts_with($this->logo_url, 'http://'), str_starts_with($this->logo_url, 'https://') => $this->logo_url,
            default => rtrim($request->getSchemeAndHttpHost(), '/')
                .'/storage/'.ltrim($this->logo_url, '/'),
        };

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
