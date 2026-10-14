<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SavedReportRequest;
use App\Models\SavedReport;
use App\Support\InstanceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->ensureAdmin($request);
        return response()->json(['data' => SavedReport::query()
            ->where('instance_id', InstanceContext::id($request))
            ->latest()
            ->get(['id', 'name', 'filters', 'user_id', 'created_at', 'updated_at'])]);
    }

    public function store(SavedReportRequest $request): JsonResponse
    {
        $this->ensureAdmin($request);
        $saved = SavedReport::create([
            'instance_id' => InstanceContext::id($request),
            'user_id' => $request->user()->id,
            'name' => trim($request->string('name')->toString()),
            'filters' => $request->validated('filters'),
        ]);
        return response()->json(['data' => $saved->only(['id', 'name', 'filters', 'user_id', 'created_at', 'updated_at'])], 201);
    }

    public function destroy(Request $request, SavedReport $savedReport): JsonResponse
    {
        $this->ensureAdmin($request);
        abort_unless($savedReport->instance_id === InstanceContext::id($request), 404);
        $savedReport->delete();
        return response()->json([], 204);
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(in_array($request->user()?->type?->value, ['admin', 'master'], true), 403);
    }
}
