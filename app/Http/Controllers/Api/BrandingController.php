<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\InstanceResource;
use App\Models\Instance;
use App\Support\InstanceContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class BrandingController extends Controller
{
    public function show(Request $request): InstanceResource
    {
        return new InstanceResource($this->instance($request));
    }

    public function update(Request $request): InstanceResource
    {
        $data = $request->validate([
            'companyName' => ['sometimes', 'required', 'string', 'max:255'],
            'primaryColor' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'secondaryColor' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'accentColor' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'primaryTextColor' => ['sometimes', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $instance = $this->instance($request);
        $instance->update([
            'name' => $data['companyName'] ?? $instance->name,
            'primary_color' => $data['primaryColor'] ?? $instance->primary_color,
            'secondary_color' => $data['secondaryColor'] ?? $instance->secondary_color,
            'accent_color' => $data['accentColor'] ?? $instance->accent_color,
            'primary_text_color' => $data['primaryTextColor'] ?? $instance->primary_text_color,
        ]);

        return new InstanceResource($instance->refresh());
    }

    public function uploadLogo(Request $request): InstanceResource
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:1024'],
        ]);

        $instance = $this->instance($request);
        $previousPath = $instance->logo_url;
        $path = $request->file('logo')->store("instances/{$instance->id}/branding", 'public');

        $instance->update(['logo_url' => $path]);
        $this->deleteStoredLogo($previousPath);

        return new InstanceResource($instance->refresh());
    }

    public function removeLogo(Request $request): InstanceResource
    {
        $instance = $this->instance($request);
        $previousPath = $instance->logo_url;

        $instance->update(['logo_url' => null]);
        $this->deleteStoredLogo($previousPath);

        return new InstanceResource($instance->refresh());
    }

    private function instance(Request $request): Instance
    {
        return Instance::findOrFail(InstanceContext::id($request));
    }

    private function deleteStoredLogo(?string $path): void
    {
        if ($path && ! str_starts_with($path, 'data:') && ! str_starts_with($path, 'http')) {
            Storage::disk('public')->delete($path);
        }
    }
}
