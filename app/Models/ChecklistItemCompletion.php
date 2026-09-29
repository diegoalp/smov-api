<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['checklist_item_id', 'business_id', 'done', 'completed_at'])]
class ChecklistItemCompletion extends Model
{
    protected function casts(): array
    {
        return [
            'done' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(ChecklistItem::class, 'checklist_item_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(History::class, 'object_id')
            ->where('object_type', 'checklist_item_completion')
            ->latest();
    }
}
