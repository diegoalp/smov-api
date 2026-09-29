<?php

namespace App\Http\Requests\ChecklistRequests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateChecklistCompletionRequest extends FormRequest
{
    public function rules(): array
    {
        return ['done' => ['required', 'boolean']];
    }
}
