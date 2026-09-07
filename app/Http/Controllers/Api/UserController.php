<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UserRequests\StoreUserRequest;
use App\Http\Requests\UserRequests\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use App\Support\InstanceContext;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $query = User::query()
            ->where('instance_id', InstanceContext::id(request()))
            ->latest();

        return UserResource::collection($query->paginate());
    }

    public function store(StoreUserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $instanceId = InstanceContext::id($request);
        $data['instance_id'] = $instanceId;
        $shouldRestore = (bool) ($data['restore'] ?? false);
        unset($data['restore']);

        $deletedUser = User::onlyTrashed()
            ->where('instance_id', $instanceId)
            ->where('email', $data['email'])
            ->first();

        if ($deletedUser && ! $shouldRestore) {
            return response()->json([
                'error' => [
                    'code' => 'USER_RESTORE_REQUIRED',
                    'message' => 'Este e-mail pertence a um usuário removido desta instância. Confirme para restaurá-lo e atualizar os dados informados.',
                ],
            ], 409);
        }

        if ($deletedUser) {
            $deletedUser->fill($data);
            $deletedUser->restore();
            $deletedUser->save();

            return (new UserResource($deletedUser->refresh()))
                ->response()
                ->setStatusCode(200);
        }

        // E-mail uniqueness is global, but restoration is strictly tenant-scoped.
        if (User::onlyTrashed()->where('email', $data['email'])->exists()) {
            return response()->json([
                'error' => [
                    'code' => 'EMAIL_ALREADY_IN_USE',
                    'message' => 'Este e-mail já está em uso.',
                ],
            ], 422);
        }

        return (new UserResource(User::create($data)))
            ->response()
            ->setStatusCode(201);
    }

    public function show(User $user): UserResource
    {
        $this->ensureTenantAccess($user);

        return new UserResource($user);
    }

    public function update(UpdateUserRequest $request, User $user): UserResource
    {
        $this->ensureTenantAccess($user);
        $data = $request->validated();
        if ($request->user()->instance_id !== null) {
            unset($data['instance_id']);
        }
        if ($user->instance_id !== null && $request->user()->type !== \App\Enums\UserType::Master) unset($data['instance_id']);
        $user->update($data);

        return new UserResource($user->refresh());
    }

    public function destroy(User $user): Response
    {
        $this->ensureTenantAccess($user);
        abort_if($user->is(request()->user()), 409, 'Você não pode remover o próprio usuário.');
        $user->delete();

        return response()->noContent();
    }

    private function ensureTenantAccess(User $user): void
    {
        InstanceContext::authorize(request(), $user->instance_id);
    }
}
