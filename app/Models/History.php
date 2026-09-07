<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class History extends Model
{
    protected $fillable = ['instance_id', 'object_id', 'object_type', 'action', 'user_id'];
}
