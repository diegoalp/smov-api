<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessEventResource;
use App\Models\Business;
use App\Support\InstanceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BusinessEventController extends Controller
{
    public function index(Request $request, Business $business): AnonymousResourceCollection
    {
        $this->authorizeBusiness($request, $business);

        return BusinessEventResource::collection($business->events()->with('user')->paginate());
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);
        $data = $request->validate(['title' => ['required', 'string', 'max:255'], 'type' => ['sometimes', 'string', 'max:40'], 'metadata' => ['nullable', 'array']]);
        $event = $business->events()->create($data + ['instance_id' => $business->instance_id, 'user_id' => $request->user()->id, 'type' => $data['type'] ?? 'note']);

        return (new BusinessEventResource($event->load('user')))->response()->setStatusCode(201);
    }

    private function authorizeBusiness(Request $request, Business $business): void
    {
        InstanceContext::authorize($request, $business->instance_id);
    }
}
