<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstance;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['instance_id', 'key', 'name', 'description', 'permissions'])]
class PermissionRole extends Model
{
    use BelongsToInstance;

    protected function casts(): array
    {
        return ['permissions' => 'array'];
    }
}
