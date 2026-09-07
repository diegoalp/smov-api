<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ActivityType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Support\InstanceContext;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Http\Resources\ActivityTypeResource;
use App\Http\Requests\ActivityTypeRequests\StoreActivityTypeRequest;

class ActivityTypeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): AnonymousResourceCollection
    {
        $activityTypes = ActivityType::query()->with('funnels')
            ->where('instance_id', InstanceContext::id(request()));

        if (request()->filled('funnel_id')) {
            $activityTypes->where(function ($query) {
                $query->whereDoesntHave('funnels')
                    ->orWhereHas('funnels', fn ($funnels) => $funnels->whereKey(request()->query('funnel_id')));
            });
        }

        $activityTypes = $activityTypes->latest()->paginate();

        return ActivityTypeResource::collection($activityTypes);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreActivityTypeRequest $request)
    {
        $data = $request->validated();
        $activityType = DB::transaction(function () use ($data) {
            $activityType = ActivityType::create([
                'instance_id' => $data['instance_id'],
                'activity_type' => $data['activity_type'],
            ]);
            $activityType->funnels()->sync($data['funnel_ids'] ?? []);

            return $activityType->load('funnels');
        });

        return (new ActivityTypeResource($activityType))->response()->setStatusCode(201);
    }

    /**
     * Display the specified resource.
     */
    public function show(ActivityType $activityType)
    {
        //
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(ActivityType $activityType)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, ActivityType $activityType)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(ActivityType $activityType)
    {
        $activityType->delete();

        return response()->noContent();
    }
}
