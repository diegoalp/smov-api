<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\FunnelRequests\StoreFunnelRequest;
use App\Http\Requests\FunnelRequests\UpdateFunnelRequest;
use App\Http\Resources\FunnelResource;
use App\Models\Funnel;
use App\Support\InstanceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class FunnelController extends Controller
{
    public function index(Request $request)
    {
        $query = Funnel::query()->with('stages')->latest();
        $query->where('instance_id', InstanceContext::id($request));

        return FunnelResource::collection($query->paginate());
    }
 
    public function store(StoreFunnelRequest $request): JsonResponse
    {
        return (new FunnelResource(Funnel::create($request->validated())->load('stages')))->response()->setStatusCode(201);
    }

    public function show(Funnel $funnel): FunnelResource
    {
        $this->ensureTenantAccess($funnel);

        return new FunnelResource($funnel->load('stages'));
    }

    public function update(UpdateFunnelRequest $request, Funnel $funnel): FunnelResource
    {
        $this->ensureTenantAccess($funnel);
        $funnel->update($request->validated());

        return new FunnelResource($funnel->refresh()->load('stages'));
    }

    public function destroy(Funnel $funnel): Response
    {
        $this->ensureTenantAccess($funnel);
        $funnel->delete();

        return response()->noContent();
    }

    private function ensureTenantAccess(Funnel $funnel): void
    {
        InstanceContext::authorize(request(), $funnel->instance_id);
    }
}
