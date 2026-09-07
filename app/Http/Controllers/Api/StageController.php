<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StageRequests\StoreStageRequest;
use App\Http\Requests\StageRequests\UpdateStageRequest;
use App\Http\Resources\StageResource;
use App\Models\Stage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use App\Support\InstanceContext;

class StageController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $instanceId = InstanceContext::id(request());
        $query = Stage::query()
            ->whereHas('funnel', fn ($query) => $query->where('instance_id', $instanceId))
            ->orderBy('funnel_id')
            ->orderBy('position');

        return StageResource::collection($query->paginate());
    }

    public function store(StoreStageRequest $request): JsonResponse
    {
        return (new StageResource(Stage::create($request->safe()->except('instance_id'))))->response()->setStatusCode(201);
    }

    public function show(Stage $stage): StageResource
    {
        $this->ensureTenantAccess($stage);

        return new StageResource($stage);
    }

    public function update(UpdateStageRequest $request, Stage $stage): StageResource
    {
        $this->ensureTenantAccess($stage);
        $stage->update($request->safe()->except('instance_id'));

        return new StageResource($stage->refresh());
    }

    public function destroy(Stage $stage): Response
    {
        $this->ensureTenantAccess($stage);
        $stage->delete();

        return response()->noContent();
    }

    private function ensureTenantAccess(Stage $stage): void
    {
        InstanceContext::authorize(request(), $stage->funnel->instance_id);
    }
}
