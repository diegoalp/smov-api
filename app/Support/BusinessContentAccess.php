<?php
namespace App\Support;
use App\Enums\UserType;
use App\Models\{Business,User};
use Illuminate\Http\Request;
use Illuminate\Http\Exceptions\HttpResponseException;
final class BusinessContentAccess {
    public static function resolve(Request $request, int $id, bool $write = false): Business {
        $instanceId = InstanceContext::id($request);
        $business = Business::where('instance_id', $instanceId)->findOrFail($id);
        $user = $request->user();
        abort_unless(in_array($user->type, [UserType::Admin, UserType::Master], true)
            || $business->user_id === $user->id
            || User::whereKey($business->user_id)->where('instance_id', $instanceId)->where('supervisor_id', $user->id)->exists(), 403);
        if ($write && Business::where('instance_id', $instanceId)->where('user_id', $user->id)
            ->where('expiration_date', '<', now())->exists()
            && (!$business->expiration_date || !$business->expiration_date->isPast())) {
            throw new HttpResponseException(ApiError::response(423, 'EXPIRED_BUSINESSES_PENDING',
                'Regularize seus negócios expirados antes de alterar outros negócios.'));
        }
        return $business;
    }
}
