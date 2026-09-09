<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class FileUrl
{
    public static function publicUrl(Request $request, ?string $path): ?string
    {
        if (! $path || str_starts_with($path, 'data:')) {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return rtrim($request->getSchemeAndHttpHost(), '/').'/storage/'.ltrim($path, '/');
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
