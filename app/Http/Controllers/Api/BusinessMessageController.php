<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\BusinessMessageResource;
use App\Models\Business;
use App\Support\InstanceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class BusinessMessageController extends Controller
{
    public function index(Request $request, Business $business): AnonymousResourceCollection
    {
        $this->authorizeBusiness($request, $business);

        return BusinessMessageResource::collection($business->messages()->paginate());
    }

    public function store(Request $request, Business $business): JsonResponse
    {
        $this->authorizeBusiness($request, $business);
        $data = $request->validate(['sender' => ['required', Rule::in(['customer', 'agent'])], 'text' => ['required', 'string', 'max:10000']]);
        $message = $business->messages()->create($data + ['instance_id' => $business->instance_id, 'user_id' => $data['sender'] === 'agent' ? $request->user()->id : null]);

        return (new BusinessMessageResource($message))->response()->setStatusCode(201);
    }

    private function authorizeBusiness(Request $request, Business $business): void
    {
        InstanceContext::authorize($request, $business->instance_id);
    }
}
