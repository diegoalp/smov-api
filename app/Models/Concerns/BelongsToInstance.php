<?php

namespace App\Models\Concerns;

use App\Models\Instance;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

trait BelongsToInstance
{
    public function instance(): BelongsTo
    {
        return $this->belongsTo(Instance::class);
    }
}
