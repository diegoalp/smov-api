<?php

namespace App\Http\Requests\ChecklistRequests;

use App\Enums\UserType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreChecklistRequest extends FormRequest
{
    use ChecklistPayloadRules;

    public function authorize(): bool
    {
        return in_array($this->user()?->type, [UserType::Admin, UserType::Master], true);
    }

    protected function prepareForValidation(): void
    {
        if ($this->user()?->instance_id !== null) {
            $this->merge(['instance_id' => $this->user()->instance_id]);
        }
    }

    public function rules(): array
    {
        return $this->checklistRules();
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $this->validateChecklistRelationships($validator);
        }];
    }
}
