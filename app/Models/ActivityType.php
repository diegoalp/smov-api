<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'instance_id', 'activity_type'
])]
class ActivityType extends Model
{
    public function funnels(): BelongsToMany
    {
        return $this->belongsToMany(Funnel::class)->withTimestamps();
    }
}
