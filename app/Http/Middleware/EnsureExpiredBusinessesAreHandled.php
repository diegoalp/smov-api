<?php

namespace App\Http\Middleware;

use App\Models\Business;
use App\Support\ApiError;
use App\Support\InstanceContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureExpiredBusinessesAreHandled
{
    public function handle(Request $request, Closure $next): Response
    {
        $routeName = $request->route()?->getName() ?? '';
        if (! str_starts_with($routeName, 'businesses.')) {
            return $next($request);
        }

        // The board must remain readable so the owner can find expired businesses.
        if ($routeName === 'businesses.index') {
            return $next($request);
        }

        $now = now();
        $instanceId = InstanceContext::id($request);
        $hasExpired = Business::where('instance_id', $instanceId)
            ->where('user_id', $request->user()->id)
            ->where('expiration_date', '<', $now)->exists();

        if ($hasExpired) {
            $target = $request->route('business');
            $business = $target instanceof Business ? $target : Business::find($target);
            if (! $business || $business->instance_id !== $instanceId
                || ! $business->expiration_date || ! $business->expiration_date->lt($now)) {
                return ApiError::response(423, 'EXPIRED_BUSINESSES_PENDING',
                    'Regularize seus negócios expirados antes de acessar ou alterar outros negócios.');
            }
        }

        return $next($request);
    }
}
