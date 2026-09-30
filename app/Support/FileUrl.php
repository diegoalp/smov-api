<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileUrl
{
    /**
     * Builds the public URL for files stored in the local public disk.
     * Branding assets must not be resolved through a cloud disk such as S3.
     */
    public static function localPublicUrl(Request $request, ?string $path): ?string
    {
        if (! $path || str_starts_with($path, 'data:')) {
            return null;
        }

        // Branding is always stored on the local public disk. Do not use the
        // generic document disk (which may be configured as S3) here.
        return url(Storage::disk('public')->url(ltrim($path, '/')));
    }

    public static function publicUrl(Request $request, ?string $path): ?string
    {
        if (! $path || str_starts_with($path, 'data:')) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return route('files.public', ['path' => ltrim($path, '/')]);
    }

    public static function diskUrl(string $disk, string $path): ?string
    {
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        $url = Storage::disk($disk)->url($path);

        return $url ? url($url) : null;
    }
}
