<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstance;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['instance_id', 'template_key', 'installed_at', 'installed_by'])]
class InstalledOperationTemplate extends Model
{
    use BelongsToInstance;

    protected function casts(): array
    {
        return ['installed_at' => 'datetime'];
    }
}
