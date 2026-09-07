<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\PhoneRequests\StorePhoneRequest;
use App\Http\Requests\PhoneRequests\UpdatePhoneRequest;
use App\Http\Resources\PhoneResource;
use App\Models\Business;
use App\Models\Phone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PhoneController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $query = Phone::query()->latest();
        if (request()->user()->instance_id !== null) {
            $query->whereHas('client.businesses', fn ($query) => $query->where('instance_id', request()->user()->instance_id));
        }

        return PhoneResource::collection($query->paginate());
    }

    public function store(StorePhoneRequest $request): JsonResponse
    {
        $this->ensureClientAccess($request->integer('client_id'));

        return (new PhoneResource(Phone::create($request->validated())))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Phone $phone): PhoneResource
    {
        $this->ensureAccess($phone);

        return new PhoneResource($phone);
    }

    public function update(UpdatePhoneRequest $request, Phone $phone): PhoneResource
    {
        $this->ensureAccess($phone);
        $phone->update($request->validated());

        return new PhoneResource($phone->refresh());
    }

    public function destroy(Phone $phone): Response
    {
        $this->ensureAccess($phone);
        $phone->delete();

        return response()->noContent();
    }

    private function ensureAccess(Phone $phone): void
    {
        $this->ensureClientAccess($phone->client_id);
    }

    private function ensureClientAccess(int $clientId): void
    {
        $instanceId = request()->user()->instance_id;
        if ($instanceId !== null) {
            abort_unless(Business::where('instance_id', $instanceId)->where('client_id', $clientId)->exists(), 404);
        }
    }
}
