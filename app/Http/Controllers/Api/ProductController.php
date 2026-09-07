<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductRequests\StoreProductRequest;
use App\Http\Requests\ProductRequests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use App\Support\InstanceContext;

class ProductController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $query = Product::query()
            ->where('instance_id', InstanceContext::id(request()))
            ->with(['categories', 'funnels'])
            ->latest();

        return ProductResource::collection($query->paginate());
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        $data = $request->validated();
        $product = Product::create($data);
        $product->categories()->sync($data['category_ids'] ?? []);
        $product->funnels()->sync($data['funnel_ids']);

        return (new ProductResource($product->load(['categories', 'funnels'])))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Product $product): ProductResource
    {
        $this->ensureTenantAccess($product);

        return new ProductResource($product->load(['categories', 'funnels']));
    }

    public function update(UpdateProductRequest $request, Product $product): ProductResource
    {
        $this->ensureTenantAccess($product);
        $data = $request->validated();
        $product->update($data);
        if (array_key_exists('category_ids', $data)) {
            $product->categories()->sync($data['category_ids']);
        }
        if (array_key_exists('funnel_ids', $data)) {
            $product->funnels()->sync($data['funnel_ids']);
        }

        return new ProductResource($product->refresh()->load(['categories', 'funnels']));
    }

    public function destroy(Product $product): Response
    {
        $this->ensureTenantAccess($product);
        $product->delete();

        return response()->noContent();
    }

    private function ensureTenantAccess(Product $product): void
    {
        InstanceContext::authorize(request(), $product->instance_id);
    }
}
