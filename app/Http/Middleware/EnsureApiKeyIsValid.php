<?php

namespace App\Http\Middleware;

use App\Support\ApiError;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiKeyIsValid
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredKey = config('api.key');

        if (! is_string($configuredKey) || $configuredKey === '') {
            throw new \RuntimeException('API_KEY não está configurada.');
        }

        $providedKey = $request->header('X-API-Key');

        if (! is_string($providedKey) || ! hash_equals($configuredKey, $providedKey)) {
            return ApiError::response(
                Response::HTTP_UNAUTHORIZED,
                'INVALID_API_KEY',
                'A chave de acesso à API não foi informada ou é inválida.',
            );
        }

        return $next($request);
    }
}
