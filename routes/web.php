<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

Route::get('/', function () {
    return response()->json(['message' => 'Welcome to the API']);
});

Route::get('/files/{path}', function (Request $request, string $path) {
    abort_if(str_contains($path, '..'), 404);
    abort_unless(Storage::disk('public')->exists($path), 404);

    return Storage::disk('public')->response($path, null, [
        'X-Content-Type-Options' => 'nosniff',
    ]);
})->where('path', '.*')->name('files.public');
