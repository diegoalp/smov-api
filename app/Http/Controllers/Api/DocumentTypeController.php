<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\DocumentTypeRequests\StoreDocumentTypeRequest;
use App\Models\DocumentType;
use App\Support\InstanceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class DocumentTypeController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(DocumentType::where('instance_id', InstanceContext::id(request()))
            ->orderBy('name')->paginate());
    }

    public function store(StoreDocumentTypeRequest $request): JsonResponse
    {
        $documentType = DocumentType::create($request->validated());

        return response()->json(['data' => $documentType], 201);
    }

    public function show(DocumentType $documentType): JsonResponse
    {
        InstanceContext::authorize(request(), $documentType->instance_id);

        return response()->json(['data' => $documentType]);
    }

    public function update(StoreDocumentTypeRequest $request, DocumentType $documentType): JsonResponse
    {
        InstanceContext::authorize($request, $documentType->instance_id);
        $documentType->update($request->safe()->only('name'));

        return response()->json(['data' => $documentType->refresh()]);
    }

    public function destroy(DocumentType $documentType): Response
    {
        InstanceContext::authorize(request(), $documentType->instance_id);
        $documentType->delete();

        return response()->noContent();
    }
}
