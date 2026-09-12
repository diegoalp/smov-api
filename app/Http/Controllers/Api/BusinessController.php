<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessRequests\StoreBusinessRequest;
use App\Http\Requests\BusinessRequests\UpdateBusinessRequest;
use App\Http\Resources\BusinessResource;
use App\Models\{Business, Stage};
use App\Models\History;
use App\Services\BusinessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Support\InstanceContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BusinessController extends Controller
{
    private const RELATIONS = BusinessService::RELATIONS;

    public function __construct(private readonly BusinessService $businessService)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $instanceId = InstanceContext::id($request);

        $request->validate([
            'stage_id' => ['required', 'integer'],
        ]);

        $stageBelongsToInstance = Stage::whereKey($request->integer('stage_id'))
            ->whereHas('funnel', fn ($query) => $query->where('instance_id', $instanceId))
            ->exists();

        if (! $stageBelongsToInstance) {
            throw ValidationException::withMessages([
                'stage_id' => ['A fase selecionada não existe na instância atual.'],
            ]);
        }

        return BusinessResource::collection($this->businessService->paginate($request));
    }

    public function store(StoreBusinessRequest $request): JsonResponse
    {
        $business = DB::transaction(function () use ($request): Business {
            $business = Business::create($request->validated());
            $this->recordHistory($business, $request->user()->id, 'Negócio criado', 'created');

            return $business;
        });

        return (new BusinessResource($business->load(self::RELATIONS)))
            ->response()->setStatusCode(201);
    }

    public function show(Business $business): BusinessResource
    {
        $this->ensureTenantAccess($business);

        return new BusinessResource($business->load(self::RELATIONS));
    }

    public function update(UpdateBusinessRequest $request, Business $business): BusinessResource
    {
        $this->ensureTenantAccess($business);
        DB::transaction(function () use ($request, $business): void {
            $previousStageId = $business->stage_id;
            $previousStatus = $business->status;
            $business->update($request->validated());
            $business->refresh();

            [$action, $type] = $this->describeUpdate($business, $previousStageId, $previousStatus);
            $this->recordHistory($business, $request->user()->id, $action, $type);
        });

        return new BusinessResource($business->refresh()->load(self::RELATIONS));
    }

    public function destroy(Business $business): Response
    {
        $this->ensureTenantAccess($business);
        $business->delete();

        return response()->noContent();
    }

    private function ensureTenantAccess(Business $business): void
    {
        InstanceContext::authorize(request(), $business->instance_id);
    }

    private function describeUpdate(Business $business, int $previousStageId, int $previousStatus): array
    {
        if ($business->status !== $previousStatus) {
            return match ($business->status) {
                0 => ['Negócio marcado como perdido'.($business->loss_reason ? ': '.$business->loss_reason : ''), 'lost'],
                2 => ['Negócio marcado como ganho', 'won'],
                default => ['Negócio reaberto', 'reopened'],
            };
        }

        if ($business->stage_id !== $previousStageId) {
            return ['Movido para a fase '.$business->stage->name, 'stage_changed'];
        }

        return ['Dados do negócio atualizados', 'updated'];
    }

    private function recordHistory(Business $business, int $userId, string $action, string $eventType): void
    {
        History::create([
            'instance_id' => $business->instance_id,
            'object_id' => $business->id,
            'object_type' => 'business',
            'action' => $action,
            'user_id' => $userId,
        ]);

        $business->events()->create([
            'instance_id' => $business->instance_id,
            'user_id' => $userId,
            'type' => $eventType,
            'title' => $action,
        ]);
    }
}
