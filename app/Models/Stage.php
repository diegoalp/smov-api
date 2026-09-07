<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['funnel_id', 'name', 'position', 'duration', 'duration_unit', 'color', 'is_final'])]
class Stage extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return ['position' => 'integer', 'duration' => 'integer', 'is_final' => 'boolean'];
    }

    public function funnel(): BelongsTo
    {
        return $this->belongsTo(Funnel::class);
    }

    public function businesses(): HasMany
    {
        return $this->hasMany(Business::class);
    }
}
