<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserType;
use App\Http\Controllers\Controller;
use App\Http\Requests\ChecklistRequests\{StoreChecklistRequest, UpdateChecklistCompletionRequest, UpdateChecklistRequest};
use App\Http\Resources\{BusinessChecklistResource, ChecklistResource};
use App\Models\{Business, Checklist, ChecklistItem, ChecklistItemCompletion, History};
use App\Services\ChecklistCompletionService;
use App\Support\{BusinessContentAccess, InstanceContext};
use Illuminate\Http\{JsonResponse, Request, Response};
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChecklistController extends Controller
{
    public function __construct(
        private readonly ChecklistCompletionService $checklistCompletionService,
    ) {}

    public function index(Request $request)
    {
        $instanceId = InstanceContext::id($request);
        $checklists = Checklist::query()
            ->where('instance_id', $instanceId)
            ->with(['funnels', 'items', 'conditions.products'])
            ->latest()
            ->paginate();

        return ChecklistResource::collection($checklists);
    }

    public function store(StoreChecklistRequest $request): JsonResponse
    {
        $instanceId = InstanceContext::id($request);
        $data = $request->validated();
        $this->assertInstanceContext($instanceId, (int) $data['instance_id']);

        $checklist = DB::transaction(function () use ($data): Checklist {
            $checklist = Checklist::create([
                'instance_id' => $data['instance_id'],
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'active' => $data['active'] ?? true,
            ]);

            $this->syncConfiguration($checklist, $data);

            return $checklist;
        });

        return (new ChecklistResource($this->loadChecklist($checklist)))
            ->response()
            ->setStatusCode(201);
    }

    public function available(Request $request)
    {
        $instanceId = InstanceContext::id($request);
        $data = $request->validate([
            'funnel_id' => ['required', 'integer'],
            'product_id' => ['sometimes', 'nullable', 'integer'],
            'stage_id' => ['sometimes', 'nullable', 'integer'],
        ]);

        $this->validateContextRelations($instanceId, $data);

        $checklists = Checklist::query()
            ->where('instance_id', $instanceId)
            ->where('active', true)
            ->whereHas('funnels', fn ($query) => $query->whereKey($data['funnel_id']))
            ->with(['funnels', 'items', 'conditions.products'])
            ->latest()
            ->get()
            ->filter(fn (Checklist $checklist): bool => $this->checklistCompletionService->matchesContext(
                $checklist,
                (int) $data['funnel_id'],
                isset($data['product_id']) ? (int) $data['product_id'] : null,
                isset($data['stage_id']) ? (int) $data['stage_id'] : null,
            ))
            ->values();

        return ChecklistResource::collection($checklists);
    }

    public function show(Request $request, Checklist $checklist): ChecklistResource
    {
        $this->authorizeChecklist($request, $checklist);

        return new ChecklistResource($this->loadChecklist($checklist));
    }

    public function forBusiness(Request $request, Business $business)
    {
        $business = BusinessContentAccess::resolve($request, $business->id);
        $checklists = $this->checklistCompletionService->applicableToBusiness($business);
        $checklists->load([
            'items.completions' => fn ($query) => $query->where('business_id', $business->id),
            'items.completions.histories',
        ]);

        return BusinessChecklistResource::collection($checklists);
    }

    public function update(UpdateChecklistRequest $request, Checklist $checklist): ChecklistResource
    {
        $this->authorizeChecklist($request, $checklist);
        $data = $request->validated();

        $updated = DB::transaction(function () use ($checklist, $data): Checklist {
            $attributes = [];
            foreach (['title', 'description', 'active'] as $attribute) {
                if (array_key_exists($attribute, $data)) {
                    $attributes[$attribute] = $data[$attribute];
                }
            }
            $checklist->update($attributes);

            $this->syncConfiguration($checklist->refresh(), $data);

            return $checklist;
        });

        return new ChecklistResource($this->loadChecklist($updated));
    }

    public function updateCompletion(
        UpdateChecklistCompletionRequest $request,
        Business $business,
        Checklist $checklist,
        ChecklistItem $item,
    ): JsonResponse {
        $business = BusinessContentAccess::resolve($request, $business->id);
        abort_unless($checklist->instance_id === $business->instance_id && $checklist->active, 404);
        abort_unless($item->checklist_id === $checklist->id, 404);
        abort_unless($this->checklistCompletionService->isApplicable($checklist, $business), 404);

        $done = $request->boolean('done');
        $completion = DB::transaction(function () use ($request, $business, $item, $done): ChecklistItemCompletion {
            $completion = ChecklistItemCompletion::query()
                ->where('checklist_item_id', $item->id)
                ->where('business_id', $business->id)
                ->lockForUpdate()
                ->first();

            if (! $completion) {
                $completion = ChecklistItemCompletion::create([
                    'checklist_item_id' => $item->id,
                    'business_id' => $business->id,
                    'done' => false,
                ]);
            }

            if ((bool) $completion->done === $done) {
                return $completion;
            }

            $completion->update([
                'done' => $done,
                'completed_at' => $done ? now() : null,
            ]);

            History::create([
                'instance_id' => $business->instance_id,
                'object_id' => $completion->id,
                'object_type' => 'checklist_item_completion',
                'action' => $done ? 'checklist_item_completed' : 'checklist_item_uncompleted',
                'user_id' => $request->user()->id,
            ]);

            return $completion->refresh();
        });

        $completedBy = $completion->done
            ? $completion->histories()->where('action', 'checklist_item_completed')->value('user_id')
            : null;

        return response()->json([
            'data' => [
                'checklist_item_id' => $completion->checklist_item_id,
                'business_id' => $completion->business_id,
                'done' => $completion->done,
                'completed_at' => $completion->completed_at,
                'completed_by' => $completedBy,
            ],
        ]);
    }

    public function destroy(Request $request, Checklist $checklist): Response
    {
        $this->authorizeChecklist($request, $checklist);
        abort_unless(in_array($request->user()->type, [UserType::Admin, UserType::Master], true), 403);
        $checklist->delete();

        return response()->noContent();
    }

    private function syncConfiguration(Checklist $checklist, array $data): void
    {
        $checklist->funnels()->sync($data['funnel_ids']);

        $existingItems = $checklist->items()->get()->keyBy('id');
        $retainedItemIds = [];
        foreach ($data['items'] ?? [] as $item) {
            $attributes = [
                'label' => $item['label'],
                'position' => $item['position'],
                'required' => $item['required'],
            ];

            if (isset($item['id']) && $existingItems->has($item['id'])) {
                $existingItems[$item['id']]->update($attributes);
                $retainedItemIds[] = (int) $item['id'];
            } else {
                $retainedItemIds[] = (int) $checklist->items()->create($attributes)->id;
            }
        }

        if ($existingItems->isNotEmpty()) {
            $checklist->items()
                ->whereNotIn('id', $retainedItemIds ?: [0])
                ->delete();
        }

        $checklist->conditions()->delete();
        foreach ($data['conditions'] ?? [] as $conditionData) {
            $condition = $checklist->conditions()->create([
                'funnel_id' => $conditionData['funnel_id'],
                'min_stage_id' => $conditionData['min_stage_id'] ?? null,
            ]);
            $condition->products()->sync($conditionData['product_ids'] ?? []);
        }
    }

    private function loadChecklist(Checklist $checklist): Checklist
    {
        return $checklist->load(['funnels', 'items', 'conditions.products']);
    }

    private function authorizeChecklist(Request $request, Checklist $checklist): void
    {
        InstanceContext::authorize($request, $checklist->instance_id);
    }

    private function assertInstanceContext(int $contextId, int $payloadId): void
    {
        abort_unless($contextId === $payloadId, 422, 'The selected instance is not available in the current context.');
    }

    private function validateContextRelations(int $instanceId, array $data): void
    {
        $funnel = \App\Models\Funnel::where('instance_id', $instanceId)
            ->whereKey($data['funnel_id'])
            ->first();
        if (! $funnel) {
            throw ValidationException::withMessages(['funnel_id' => ['O funil selecionado não existe na instância atual.']]);
        }

        if (array_key_exists('product_id', $data) && $data['product_id'] !== null) {
            $validProduct = \App\Models\Product::where('instance_id', $instanceId)
                ->whereKey($data['product_id'])
                ->whereHas('funnels', fn ($query) => $query->whereKey($data['funnel_id']))
                ->exists();
            if (! $validProduct) {
                throw ValidationException::withMessages(['product_id' => ['O produto selecionado não pertence ao funil atual.']]);
            }
        }

        if (array_key_exists('stage_id', $data) && $data['stage_id'] !== null) {
            $validStage = \App\Models\Stage::whereKey($data['stage_id'])
                ->where('funnel_id', $data['funnel_id'])
                ->whereNull('deleted_at')
                ->exists();
            if (! $validStage) {
                throw ValidationException::withMessages(['stage_id' => ['A etapa selecionada não pertence ao funil atual.']]);
            }
        }
    }

}
