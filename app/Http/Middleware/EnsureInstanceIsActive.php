<?php
namespace App\Http\Middleware;
use App\Models\Instance;
use App\Support\{InstanceContext,ApiError};
use Closure;
use Illuminate\Http\Request;
class EnsureInstanceIsActive {
    public function handle(Request $request, Closure $next) {
        // Metadata and master renewal remain available when tenant access is blocked.
        if ($request->is('api/instances') || $request->is('api/instances/*')) return $next($request);
        $instance = Instance::findOrFail(InstanceContext::id($request));
        if ($instance->isExpired()) return ApiError::response(423, 'INSTANCE_EXPIRED',
            'Esta instância expirou. Solicite ao master uma nova data de expiração.',
            ['instance_id' => $instance->id, 'expiration_date' => $instance->expiration_date?->toDateString()]);
        return $next($request);
    }
}
