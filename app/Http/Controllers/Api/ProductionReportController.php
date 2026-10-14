<?php

namespace App\Http\Controllers\Api;

use App\Enums\Status;
use App\Http\Controllers\Controller;
use App\Http\Requests\ProductionReportRequest;
use App\Models\Business;
use App\Models\CustomField;
use App\Models\Product;
use App\Models\User;
use App\Support\InstanceContext;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Arr;

class ProductionReportController extends Controller
{
    private const FILTER_OPERATORS = ['equals', 'contains', 'gte', 'lte'];

    public function __invoke(ProductionReportRequest $request): JsonResponse
    {
        abort_unless($request->user()?->type?->value === 'master' || $request->user()?->type?->value === 'admin', 403);

        $instanceId = InstanceContext::id($request);
        $this->validateRelationships($request, $instanceId);
        $filters = $this->normalizeFilters($request->customFilters(), $instanceId);
        $sumFieldIds = $request->sumFieldIds();

        $businesses = Business::query()
            ->where('instance_id', $instanceId)
            ->whereBetween('created_at', [
                Carbon::createFromFormat('Y-m-d', $request->string('from')->toString())->startOfDay(),
                Carbon::createFromFormat('Y-m-d', $request->string('to')->toString())->endOfDay(),
            ])
            ->when($request->filled('user_id'), fn ($query) => $query->where('user_id', $request->integer('user_id')))
            ->when($request->filled('product_id'), fn ($query) => $query->where('product_id', $request->integer('product_id')))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('funnel_id'), fn ($query) => $query->where('funnel_id', $request->integer('funnel_id')))
            ->when($request->filled('stage_id'), fn ($query) => $query->where('stage_id', $request->integer('stage_id')))
            ->when($request->filled('lead_source_id'), fn ($query) => $query->where('lead_source_id', $request->integer('lead_source_id')))
            ->when($request->filled('disposition_id'), fn ($query) => $query->where('disposition_id', $request->integer('disposition_id')))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->integer('status')))
            ->with([
                'user:id,name,lastname', 'client:id,fullname', 'product:id,title,color', 'category:id,name', 'funnel:id,name', 'stage:id,name,color',
                'activities:id,business_id,user_id,status,scheduled_at,completed_at',
            ])
            ->get();

        $businesses = $businesses->filter(fn (Business $business): bool => $this->matchesCustomFilters($this->customValues($business), $filters));
        $rows = $businesses->groupBy(fn (Business $business): string => ($business->user_id ?? 'none').':'.($business->product_id ?? 'none'))
            ->map(fn ($group): array => $this->row($group))
            ->values();

        $totalValue = (float) $businesses->sum(fn (Business $business): float => (float) $business->value);
        $totalCount = $businesses->count();
        $won = $businesses->where('status', Status::Winned->number());
        $lost = $businesses->where('status', Status::Lost->number());
        $open = $businesses->where('status', Status::Open->number());
        $activities = $businesses->flatMap(fn (Business $business) => $business->activities);
        $completedActivities = $activities->where('status', 'completed');
        $overdueOpen = $open->filter(fn (Business $business): bool => $business->due_at !== null && $business->due_at->isPast());
        $customFieldTotals = [];
        foreach ($sumFieldIds as $fieldId) {
            $customFieldTotals[(string) $fieldId] = $businesses->sum(fn (Business $business): float => $this->numericSum($this->customValues($business)[$fieldId] ?? null));
        }

        return response()->json([
            'summary' => [
                'total_production' => $totalCount,
                'total_count' => $totalCount,
                'total_value' => $totalValue,
                'average_value' => $totalCount > 0 ? $totalValue / $totalCount : 0,
                'won_count' => $won->count(), 'lost_count' => $lost->count(), 'open_count' => $open->count(),
                'won_value' => (float) $won->sum(fn (Business $business): float => (float) $business->value),
                'lost_value' => (float) $lost->sum(fn (Business $business): float => (float) $business->value),
                'open_value' => (float) $open->sum(fn (Business $business): float => (float) $business->value),
                'conversion_rate' => $totalCount > 0 ? round($won->count() / $totalCount * 100, 2) : 0,
                'loss_rate' => $totalCount > 0 ? round($lost->count() / $totalCount * 100, 2) : 0,
                'overdue_open_count' => $overdueOpen->count(),
                'activity_count' => $activities->count(),
                'completed_activity_count' => $completedActivities->count(),
            ],
            'rows' => $rows,
            'status_breakdown' => $businesses->groupBy('status')->map(fn ($group, $status): array => [
                'status' => (int) $status,
                'count' => $group->count(),
                'total_value' => (float) $group->sum(fn (Business $business): float => (float) $business->value),
                'businesses' => $group->sortByDesc('created_at')->map(fn (Business $business): array => $this->businessSummaryRow($business))->values(),
            ])->values(),
            'daily_breakdown' => $businesses->groupBy(fn (Business $business): string => $business->created_at->format('Y-m-d'))
                ->map(fn ($group, $date): array => [
                    'date' => $date, 'label' => Carbon::parse($date)->format('d/m'), 'total_count' => $group->count(),
                    'won_count' => $group->where('status', Status::Winned->number())->count(),
                    'lost_count' => $group->where('status', Status::Lost->number())->count(),
                    'open_count' => $group->where('status', Status::Open->number())->count(),
                    'total_value' => (float) $group->sum(fn (Business $business): float => (float) $business->value),
                ])->sortKeys()->values(),
            'loss_reasons' => $businesses->where('status', Status::Lost->number())
                ->groupBy(fn (Business $business): string => trim((string) $business->loss_reason) ?: 'Sem motivo informado')
                ->map(fn ($group, $reason): array => [
                    'label' => $reason,
                    'count' => $group->count(),
                    'total_value' => (float) $group->sum(fn (Business $business): float => (float) $business->value),
                    'percentage' => $lost->count() > 0 ? round($group->count() / $lost->count() * 100, 2) : 0,
                ])->sortByDesc('count')->values(),
            'losses_by_user' => $businesses->where('status', Status::Lost->number())
                ->groupBy('user_id')->map(fn ($group): array => $this->userOutcomeRow($group, 'lost_count'))->sortByDesc('count')->values(),
            'outcomes_by_product' => $businesses->groupBy('product_id')
                ->map(fn ($group): array => $this->productOutcomeRow($group))->sortByDesc('total_count')->values(),
            'user_performance' => $businesses->groupBy('user_id')->map(fn ($group): array => $this->performanceRow($group, 'user'))->sortByDesc('total_value')->values(),
            'product_performance' => $businesses->groupBy('product_id')->map(fn ($group): array => $this->performanceRow($group, 'product'))->sortByDesc('total_value')->values(),
            'category_performance' => $businesses->groupBy('category_id')->map(fn ($group): array => $this->performanceRow($group, 'category'))->sortByDesc('total_value')->values(),
            'stage_breakdown' => $businesses->groupBy('stage_id')->map(fn ($group): array => $this->performanceRow($group, 'stage'))->sortByDesc('total_count')->values(),
            'funnel_breakdown' => $businesses->groupBy('funnel_id')->map(fn ($group): array => $this->performanceRow($group, 'funnel'))->sortByDesc('total_count')->values(),
            'lead_source_breakdown' => $businesses->groupBy('lead_source_id')->map(fn ($group): array => $this->namedOutcomeRow($group, 'lead_source'))->sortByDesc('total_count')->values(),
            'disposition_breakdown' => $businesses->groupBy('disposition_id')->map(fn ($group): array => $this->namedOutcomeRow($group, 'disposition'))->sortByDesc('total_count')->values(),
            'activity_summary' => [
                'total' => $activities->count(), 'completed' => $completedActivities->count(),
                'pending' => $activities->where('status', 'pending')->count(), 'cancelled' => $activities->where('status', 'cancelled')->count(),
            ],
            'custom_field_totals' => $customFieldTotals,
            'meta' => [
                'from' => $request->string('from')->toString(),
                'to' => $request->string('to')->toString(),
                'generated_at' => now()->toIso8601String(),
                'filters' => $request->only(['user_id', 'product_id', 'category_id', 'funnel_id', 'stage_id', 'lead_source_id', 'disposition_id', 'status']),
            ],
        ]);
    }

    private function validateRelationships(ProductionReportRequest $request, int $instanceId): void
    {
        if ($request->filled('user_id') && ! User::whereKey($request->integer('user_id'))->where('instance_id', $instanceId)->exists()) {
            abort(422, 'O usuário selecionado não pertence à instância atual.');
        }
        if ($request->filled('product_id') && ! Product::whereKey($request->integer('product_id'))->where('instance_id', $instanceId)->exists()) {
            abort(422, 'O produto selecionado não pertence à instância atual.');
        }
        foreach ([['category_id', 'categories'], ['funnel_id', 'funnels'], ['lead_source_id', 'lead_sources'], ['disposition_id', 'dispositions']] as [$input, $table]) {
            if ($request->filled($input) && ! \DB::table($table)->where('id', $request->integer($input))->where('instance_id', $instanceId)->exists()) abort(422, 'O filtro selecionado não pertence à instância atual.');
        }
        if ($request->filled('stage_id') && ! \DB::table('stages')->join('funnels', 'funnels.id', '=', 'stages.funnel_id')->where('stages.id', $request->integer('stage_id'))->where('funnels.instance_id', $instanceId)->exists()) abort(422, 'A etapa selecionada não pertence à instância atual.');

        $fieldIds = collect($request->customFilters())->pluck('fieldId')->merge($request->sumFieldIds())->filter()->map(fn ($id): int => (int) $id)->unique();
        if ($fieldIds->isNotEmpty() && CustomField::where('instance_id', $instanceId)->whereIn('id', $fieldIds)->count() !== $fieldIds->count()) {
            abort(422, 'Um ou mais campos personalizados não pertencem à instância atual.');
        }
    }

    private function normalizeFilters(array $filters, int $instanceId): array
    {
        return collect($filters)->map(function ($filter): ?array {
            if (! is_array($filter) || ! isset($filter['fieldId'], $filter['operator'], $filter['value'])) return null;
            if (! ctype_digit((string) $filter['fieldId']) || ! in_array($filter['operator'], self::FILTER_OPERATORS, true)) return null;
            return ['fieldId' => (int) $filter['fieldId'], 'operator' => $filter['operator'], 'value' => (string) $filter['value']];
        })->filter()->values()->all();
    }

    private function customValues(Business $business): array
    {
        $data = $business->custom_data ?? [];
        $values = is_array($data['custom_fields'] ?? null) ? $data['custom_fields'] : $data;
        return is_array($values) ? $values : [];
    }

    private function matchesCustomFilters(array $values, array $filters): bool
    {
        foreach ($filters as $filter) {
            $actual = $values[$filter['fieldId']] ?? $values[(string) $filter['fieldId']] ?? null;
            $actualValues = is_array($actual) ? Arr::flatten($actual) : [$actual];
            $matched = collect($actualValues)->contains(function ($candidate) use ($filter): bool {
                $expected = $filter['value'];
                return match ($filter['operator']) {
                    'contains' => str_contains(mb_strtolower((string) $candidate), mb_strtolower($expected)),
                    'gte' => is_numeric($candidate) && (float) $candidate >= (float) $expected,
                    'lte' => is_numeric($candidate) && (float) $candidate <= (float) $expected,
                    default => mb_strtolower((string) $candidate) === mb_strtolower($expected),
                };
            });
            if (! $matched) return false;
        }
        return true;
    }

    private function numericSum(mixed $value): float
    {
        if (is_array($value)) return collect($value)->sum(fn ($item): float => $this->numericSum($item));
        return is_numeric($value) ? (float) $value : 0;
    }

    private function row($group): array
    {
        /** @var Business $first */
        $first = $group->first();
        return [
            'user_id' => $first->user_id,
            'user_name' => $first->user ? trim($first->user->name.' '.($first->user->lastname ?? '')) : null,
            'product_id' => $first->product_id,
            'product_name' => $first->product?->title,
            'count' => $group->count(),
            'total' => $group->count(),
            'total_value' => (float) $group->sum(fn (Business $business): float => (float) $business->value),
            'won_count' => $group->where('status', Status::Winned->number())->count(),
            'lost_count' => $group->where('status', Status::Lost->number())->count(),
            'open_count' => $group->where('status', Status::Open->number())->count(),
        ];
    }

    private function userOutcomeRow($group, string $outcomeKey): array
    {
        /** @var Business $first */
        $first = $group->first();
        return [
            'user_id' => $first->user_id,
            'user_name' => $first->user ? trim($first->user->name.' '.($first->user->lastname ?? '')) : 'Não atribuído',
            'count' => $group->count(),
            $outcomeKey => $group->count(),
            'total_value' => (float) $group->sum(fn (Business $business): float => (float) $business->value),
        ];
    }

    private function productOutcomeRow($group): array
    {
        /** @var Business $first */
        $first = $group->first();
        return [
            'product_id' => $first->product_id,
            'product_name' => $first->product?->title ?: 'Sem produto',
            'won_count' => $group->where('status', Status::Winned->number())->count(),
            'lost_count' => $group->where('status', Status::Lost->number())->count(),
            'open_count' => $group->where('status', Status::Open->number())->count(),
            'total_count' => $group->count(),
            'total_value' => (float) $group->sum(fn (Business $business): float => (float) $business->value),
        ];
    }

    private function performanceRow($group, string $dimension): array
    {
        /** @var Business $first */
        $first = $group->first();
        $relation = $first->{$dimension === 'lead_source' ? 'leadSource' : $dimension};
        $label = match ($dimension) {
            'user' => $relation ? trim($relation->name.' '.($relation->lastname ?? '')) : 'Não atribuído',
            default => $relation?->name ?? ($dimension === 'product' ? ($first->product?->title ?? 'Sem produto') : 'Sem '.$dimension),
        };
        $won = $group->where('status', Status::Winned->number());
        $lost = $group->where('status', Status::Lost->number());
        $activities = $group->flatMap(fn (Business $business) => $business->activities);
        return [
            'id' => $first->{$dimension.'_id'}, 'label' => $label,
            $dimension.'_id' => $first->{$dimension.'_id'}, $dimension.'_name' => $label,
            'total_count' => $group->count(), 'won_count' => $won->count(), 'lost_count' => $lost->count(), 'open_count' => $group->where('status', Status::Open->number())->count(),
            'total_value' => (float) $group->sum(fn (Business $business): float => (float) $business->value), 'won_value' => (float) $won->sum(fn (Business $business): float => (float) $business->value),
            'conversion_rate' => $group->count() > 0 ? round($won->count() / $group->count() * 100, 2) : 0,
            'activity_count' => $activities->count(), 'completed_activity_count' => $activities->where('status', 'completed')->count(),
        ];
    }

    private function namedOutcomeRow($group, string $dimension): array
    {
        /** @var Business $first */
        $first = $group->first();
        $relation = $first->{$dimension === 'lead_source' ? 'leadSource' : $dimension};
        $won = $group->where('status', Status::Winned->number());
        return ['id' => $first->{$dimension.'_id'}, 'label' => $relation?->name ?? 'Não informado', 'total_count' => $group->count(), 'won_count' => $won->count(), 'lost_count' => $group->where('status', Status::Lost->number())->count(), 'total_value' => (float) $group->sum(fn (Business $business): float => (float) $business->value), 'conversion_rate' => $group->count() > 0 ? round($won->count() / $group->count() * 100, 2) : 0];
    }

    private function businessSummaryRow(Business $business): array
    {
        return [
            'id' => $business->id,
            'client_name' => $business->client?->fullname ?? 'Cliente não informado',
            'product_name' => $business->product?->title ?? 'Sem produto',
            'product_color' => $business->product?->color,
            'user_name' => $business->user ? trim($business->user->name.' '.($business->user->lastname ?? '')) : 'Não atribuído',
            'stage_name' => $business->stage?->name ?? 'Sem etapa',
            'stage_color' => $business->stage?->color,
            'value' => (float) $business->value,
            'created_at' => $business->created_at?->toIso8601String(),
            'loss_reason' => $business->loss_reason,
        ];
    }
}
