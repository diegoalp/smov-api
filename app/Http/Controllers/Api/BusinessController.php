<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\BusinessRequests\StoreBusinessRequest;
use App\Http\Requests\BusinessRequests\UpdateBusinessRequest;
use App\Http\Resources\BusinessResource;
use App\Models\Business;
use App\Models\History;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use App\Support\InstanceContext;
use Illuminate\Support\Facades\DB;
use App\Enums\UserType;

class BusinessController extends Controller
{
    private const RELATIONS = ['client.phones', 'user', 'category', 'product', 'funnel', 'stage', 'events.user', 'messages'];

    public function index(): AnonymousResourceCollection
    {   
        $user = \Auth::user();
        
        $query = Business::query()
            ->where('instance_id', InstanceContext::id(request()))
            ->with(self::RELATIONS);

        //Se o usuário não for master ou admin, ele só poderá ver os negócios dele mesmo
        if(!in_array($user->type, [UserType::Master, UserType::Admin], true)){
            $query->where('user_id', $user->id);
        }

        $query->latest();

        return BusinessResource::collection($query->paginate());
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
