<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequests\StoreCategoryRequest;
use App\Http\Requests\CategoryRequests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use App\Support\InstanceContext;

class CategoryController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $instanceId = InstanceContext::id(request());

        return CategoryResource::collection(Category::query()
            ->where('instance_id', $instanceId)
            ->with([
                'funnels',
                'products' => fn ($query) => $query->where('instance_id', $instanceId),
            ])
            ->latest()->paginate());
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $data = $request->validated();
        $category = Category::create($data);
        $category->products()->sync($data['product_ids'] ?? []);
        $category->funnels()->sync($data['funnel_ids']);

        return (new CategoryResource($this->loadTenantProducts($category)))->response()->setStatusCode(201);
    }

    public function show(Category $category): CategoryResource
    {
        $this->ensureTenantAccess($category);

        return new CategoryResource($this->loadTenantProducts($category));
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $this->ensureTenantAccess($category);
        $data = $request->validated();
        $category->update($data);
        if (array_key_exists('product_ids', $data)) {
            $this->syncTenantProducts($category, $data['product_ids']);
        }
        if (array_key_exists('funnel_ids', $data)) {
            $category->funnels()->sync($data['funnel_ids']);
        }

        return new CategoryResource($this->loadTenantProducts($category->refresh()));
    }

    public function destroy(Category $category): Response
    {
        $this->ensureTenantAccess($category);
        $category->delete();

        return response()->noContent();
    }

    /** @param array<int, int> $productIds */
    private function syncTenantProducts(Category $category, array $productIds): void
    {
        $instanceId = InstanceContext::id(request());

        $otherTenantIds = $category->products()
            ->where('instance_id', '!=', $instanceId)
            ->pluck('products.id')
            ->all();

        $category->products()->sync([...$otherTenantIds, ...$productIds]);
    }

    private function loadTenantProducts(Category $category): Category
    {
        $instanceId = InstanceContext::id(request());

        return $category->load([
            'funnels',
            'products' => fn ($query) => $query->where('instance_id', $instanceId),
        ]);
    }

    private function ensureTenantAccess(Category $category): void
    {
        InstanceContext::authorize(request(), $category->instance_id);
    }
}
