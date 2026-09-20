<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientRequests\StoreClientRequest;
use App\Http\Requests\ClientRequests\UpdateClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use App\Services\ClientResolver;
use App\Support\InstanceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Validator;

class ClientController extends Controller
{
    public function __construct(private readonly ClientResolver $clientResolver) {}

    public function index(): AnonymousResourceCollection
    {
        $query = Client::query()->with('phones')->latest();
        $query->accessibleByInstance(InstanceContext::id(request()));

        return ClientResource::collection($query->paginate());
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        return (new ClientResource(Client::create([
            ...$request->validated(),
            'instance_id' => InstanceContext::id($request),
        ])->load('phones')))
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

        $client = $this->clientResolver->resolve($data, InstanceContext::id($request));

        return (new ClientResource($client))->response();
    }

    public function search(Request $request): ClientResource
    {
        $data = $request->validate([
            'registration' => ['required', 'string', 'max:18'],
        ]);

        $registration = preg_replace('/\D+/', '', (string) $data['registration']);

        Validator::make(['registration' => $registration], [
            'registration' => [
                'required',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! in_array(strlen((string) $value), [11, 14], true)) {
                        $fail('O campo registration deve conter um CPF ou CNPJ valido.');
                    }
                },
            ],
        ])->validate();

        $client = Client::query()
            ->with('phones')
            ->accessibleByInstance(InstanceContext::id($request))
            ->where('registration', $registration)
            ->firstOrFail();

        return new ClientResource($client);
    }

    private function ensureAccess(Client $client): void
    {
        InstanceContext::authorize(request(), (int) $client->instance_id);
    }
}
