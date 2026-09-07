<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstance;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['instance_id', 'name', 'description'])]
class Team extends Model
{
    use BelongsToInstance, SoftDeletes;

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
