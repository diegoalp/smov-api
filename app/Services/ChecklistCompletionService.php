<?php

namespace App\Services;

use App\Models\{Business, Checklist, ChecklistItemCompletion, Stage};
use Illuminate\Database\Eloquent\Collection;

class ChecklistCompletionService
{
    public function applicableToBusiness(Business $business): Collection
    {
        $business->loadMissing('stage');

        $checklists = Checklist::query()
            ->where('instance_id', $business->instance_id)
            ->where('active', true)
            ->whereHas('funnels', fn ($query) => $query->whereKey($business->funnel_id))
            ->with(['items', 'conditions.products', 'conditions.minStage'])
            ->latest()
            ->get();

        return $checklists
            ->filter(fn (Checklist $checklist): bool => $this->matches(
                $checklist,
                (int) $business->funnel_id,
                $business->product_id !== null ? (int) $business->product_id : null,
                $business->stage?->position,
            ))
            ->values();
    }

    public function ensureForBusiness(Business $business): void
    {
        foreach ($this->applicableToBusiness($business) as $checklist) {
            foreach ($checklist->items as $item) {
                ChecklistItemCompletion::firstOrCreate([
                    'checklist_item_id' => $item->id,
                    'business_id' => $business->id,
                ], [
                    'done' => false,
                    'completed_at' => null,
                ]);
            }
        }
    }

    public function isApplicable(Checklist $checklist, Business $business): bool
    {
        $business->loadMissing('stage');
        $checklist->loadMissing(['conditions.products', 'conditions.minStage']);

        return $checklist->active
            && $checklist->funnels()->whereKey($business->funnel_id)->exists()
            && $this->matches(
                $checklist,
                (int) $business->funnel_id,
                $business->product_id !== null ? (int) $business->product_id : null,
                $business->stage?->position,
            );
    }

    public function matchesContext(Checklist $checklist, int $funnelId, ?int $productId, ?int $stageId): bool
    {
        $stagePosition = $stageId === null ? null : Stage::whereKey($stageId)->value('position');
        $checklist->loadMissing(['conditions.products', 'conditions.minStage']);

        return $this->matches($checklist, $funnelId, $productId, $stagePosition);
    }

    private function matches(Checklist $checklist, int $funnelId, ?int $productId, ?int $stagePosition): bool
    {
        if ($checklist->conditions->isEmpty()) {
            return true;
        }

        return $checklist->conditions->contains(function ($condition) use ($funnelId, $productId, $stagePosition): bool {
            if ((int) $condition->funnel_id !== $funnelId) {
                return false;
            }

            if ($condition->products->isNotEmpty() && ! $condition->products->contains('id', $productId)) {
                return false;
            }

            return $condition->min_stage_id === null
                || ($stagePosition !== null && $condition->minStage?->position !== null
                    && $stagePosition >= $condition->minStage->position);
        });
    }
}
