<?php

namespace App\Support;

use App\Models\Instance;
use Illuminate\Http\Request;

final class InstanceContext
{
    /** Resolve the tenant assigned to the user or explicitly selected by a master user. */
    public static function id(Request $request): int
    {
        $instanceId = $request->user()?->instance_id;

        if ($request->user()?->type === \App\Enums\UserType::Master) {
            $instanceId = $request->integer('instance_id') ?: (int) $request->header('instance_id') ?: $instanceId;
        }
        if ($instanceId === null && $request->user()?->type !== \App\Enums\UserType::Master) {
            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                ApiError::response(403, 'INSTANCE_REQUIRED', 'Crie sua instância antes de acessar o CRM.')
            );
        }
        if (!$instanceId) {
            $instanceId = $request->integer('instance_id')
                ?: (int) $request->header('instance_id');
        }

        abort_unless(
            $instanceId > 0 && Instance::whereKey($instanceId)->exists(),
            422,
            'A valid instance must be selected.'
        );

        return (int) $instanceId;
    }

    /** Prevent route-model binding from exposing a resource from another tenant. */
    public static function authorize(Request $request, int $resourceInstanceId): void
    {
        abort_if($resourceInstanceId !== self::id($request), 404);
    }
}
