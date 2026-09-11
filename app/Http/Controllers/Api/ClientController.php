<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientRequests\StoreClientRequest;
use App\Http\Requests\ClientRequests\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ClientController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $query = Client::query()->with('phones')->latest();
        if (request()->user()->instance_id !== null) {
            $query->accessibleByInstance(request()->user()->instance_id);
        }

        return ClientResource::collection($query->paginate());
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        return (new ClientResource(Client::create($request->validated())->load('phones')))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Client $client): ClientResource
    {
        $this->ensureAccess($client);

        return new ClientResource($client->load('phones'));
    }

    public function update(UpdateClientRequest $request, Client $client): ClientResource
    {
        $this->ensureAccess($client);
        $client->update($request->validated());

        return new ClientResource($client->refresh()->load('phones'));
    }

    public function destroy(Client $client): Response
    {
        $this->ensureAccess($client);
        abort_if($client->businesses()->exists(), 409, 'Clientes com negócios não podem ser removidos.');
        $client->delete();

        return response()->noContent();
    }

    public function resolve(Request $request): JsonResponse
    {
        $data = $request->validate([
            'fullname' => ['required', 'string', 'max:255'],
            'type' => ['required', 'string', 'in:individual,company'],
            'registration' => ['required', 'string', 'max:18'],
            'birthdate' => ['nullable', 'date', 'before:today'],
            'phones' => ['sometimes', 'array'],
            'phones.*.number' => ['required', 'string', 'max:20'],
            'phones.*.whatsapp' => ['sometimes', 'boolean'],
            'extra' => ['sometimes', 'nullable', 'array'],
        ]);

        $registration = preg_replace('/\D+/', '', $data['registration']);
        validator(['registration' => $registration], [
            'registration' => [$data['type'] === 'individual' ? 'size:11' : 'size:14'],
        ])->validate();

        $client = DB::transaction(function () use ($data, $registration): Client {
            $client = Client::firstOrCreate(['registration' => $registration], [
                'fullname' => $data['fullname'], 'type' => $data['type'], 'birthdate' => $data['birthdate'] ?? null,
            ]);
            $client->update(array_filter([
                'fullname' => $data['fullname'], 'type' => $data['type'], 'birthdate' => $data['birthdate'] ?? null,
                'extra' => $data['extra'] ?? null,
            ], fn ($value) => $value !== null));
            foreach ($data['phones'] ?? [] as $phone) {
                $number = preg_replace('/\D+/', '', $phone['number']);
                $client->phones()->updateOrCreate(['number' => $number], ['whatsapp' => $phone['whatsapp'] ?? false]);
            }

            return $client->load('phones');
        });

        return (new ClientResource($client))->response();
    }

    private function ensureAccess(Client $client): void
    {
        $instanceId = request()->user()->instance_id;
        abort_if($instanceId !== null && ! $client->businesses()->where('instance_id', $instanceId)->exists(), 404);
    }
}
