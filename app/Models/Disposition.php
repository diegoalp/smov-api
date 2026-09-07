<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstance;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['instance_id', 'name'])]
class Disposition extends Model
{
    use BelongsToInstance, SoftDeletes;
}
