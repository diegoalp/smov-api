<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;

final class ApiError
{
    /**
     * @param  array<string, mixed>|null  $details
     */
    public static function response(
        int $status,
        string $code,
        string $message,
        ?array $details = null,
        array $headers = [],
    ): JsonResponse {
        $error = [
            'status' => $status,
            'code' => $code,
            'message' => $message,
        ];

        if ($details !== null) {
            $error['details'] = $details;
        }

        return response()->json([
            'success' => false,
            'error' => $error,
        ], $status, $headers);
    }
}
