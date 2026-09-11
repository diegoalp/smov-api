<?php

namespace App\Models;

use App\Models\Concerns\BelongsToInstance;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['instance_id', 'label', 'section', 'custom_field_section_id', 'type', 'required', 'default_value', 'visible_when', 'conditions', 'options', 'sub_fields', 'position', 'active'])]
class CustomField extends Model
{
    use BelongsToInstance, SoftDeletes;

    protected function casts(): array
    {
        return ['required' => 'boolean', 'default_value' => 'boolean', 'active' => 'boolean', 'conditions' => 'array', 'options' => 'array', 'sub_fields' => 'array'];
    }
}
