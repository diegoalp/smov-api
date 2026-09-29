<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['checklist_id', 'funnel_id', 'min_stage_id'])]
class ChecklistCondition extends Model
{
    protected function casts(): array
    {
        return ['min_stage_id' => 'integer'];
    }

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(Checklist::class);
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function minStage(): BelongsTo
    {
        return $this->belongsTo(Stage::class, 'min_stage_id');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'checklist_condition_product', 'condition_id', 'product_id')
            ->withTimestamps();
    }
}
