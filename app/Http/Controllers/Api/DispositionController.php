<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DispositionRequests\StoreDispositionRequest;
use App\Http\Requests\DispositionRequests\UpdateDispositionRequest;
use App\Http\Resources\DispositionResource;
use App\Models\Disposition;
use App\Support\InstanceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class DispositionController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return DispositionResource::collection(
            Disposition::query()
                ->where('instance_id', InstanceContext::id(request()))
                ->latest()
                ->paginate()
        );
    }

    public function store(StoreDispositionRequest $request): JsonResponse
    {
        $disposition = Disposition::create($request->validated());

        return (new DispositionResource($disposition))->response()->setStatusCode(201);
    }

    public function show(Disposition $disposition): DispositionResource
    {
        $this->ensureTenantAccess($disposition);

        return new DispositionResource($disposition);
    }

    public function update(UpdateDispositionRequest $request, Disposition $disposition): DispositionResource
    {
        $this->ensureTenantAccess($disposition);
        $disposition->update($request->validated());

        return new DispositionResource($disposition->refresh());
    }

    public function destroy(Disposition $disposition): Response
    {
        $this->ensureTenantAccess($disposition);
        $disposition->delete();

        return response()->noContent();
    }

    private function ensureTenantAccess(Disposition $disposition): void
    {
        InstanceContext::authorize(request(), $disposition->instance_id);
    }
}
