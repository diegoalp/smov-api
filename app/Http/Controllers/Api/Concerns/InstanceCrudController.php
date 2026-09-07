<?php

namespace App\Http\Controllers\Api\Concerns;

use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use App\Support\InstanceContext;

abstract class InstanceCrudController extends Controller
{
    /** @var class-string<Model> */
    protected string $modelClass;

    abstract protected function rules(Request $request, ?Model $model = null): array;

    protected function query(Request $request): Builder
    {
        $query = ($this->modelClass)::query();
        return $query->where('instance_id', InstanceContext::id($request));
    }

    protected function transformPayload(array $data): array
    {
        return $data;
    }

    protected function serialize(Model $model): array
    {
        return $model->toArray();
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json($this->query($request)->latest()->paginate());
    }

    public function store(Request $request): JsonResponse
    {
        $data = $this->transformPayload($request->validate($this->rules($request)));
        $data['instance_id'] = InstanceContext::id($request);
        $model = ($this->modelClass)::create($data);

        return response()->json(['data' => $this->serialize($model->refresh())], 201);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        return response()->json(['data' => $this->serialize($this->find($request, $id))]);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $model = $this->find($request, $id);
        $model->update($this->transformPayload($request->validate($this->rules($request, $model))));

        return response()->json(['data' => $this->serialize($model->refresh())]);
    }

    public function destroy(Request $request, int $id): Response
    {
        $this->find($request, $id)->delete();

        return response()->noContent();
    }

    protected function find(Request $request, int $id): Model
    {
        return $this->query($request)->findOrFail($id);
    }

}
