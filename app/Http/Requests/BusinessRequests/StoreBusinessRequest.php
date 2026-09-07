<?php

namespace App\Http\Requests\BusinessRequests;

use App\Http\Requests\Concerns\UsesAuthenticatedInstance;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreBusinessRequest extends FormRequest
{
    use UsesAuthenticatedInstance {
        prepareForValidation as prepareForInstanceValidation;
    }

    protected function prepareForValidation(): void
    {
        $this->prepareForInstanceValidation();

        if (! $this->route('business') && $this->filled('instance_id')) {
            $requestedUserCanOwnBusiness = \DB::table('users')
                ->where('id', $this->integer('user_id'))
                ->where(function ($query): void {
                    $query->where('instance_id', $this->integer('instance_id'))
                        ->orWhere('type', 'master');
                })
                ->whereNull('deleted_at')
                ->exists();

            if (! $requestedUserCanOwnBusiness) {
                $tenantUserId = \DB::table('users')
                    ->where('instance_id', $this->integer('instance_id'))
                    ->whereNull('deleted_at')
                    ->orderByRaw("case when type = 'seller' then 0 else 1 end")
                    ->orderBy('id')
                    ->value('id');

                if ($tenantUserId) {
                    $this->merge(['user_id' => $tenantUserId]);
                }
            }
        }

        // Updates must preserve the explicitly requested stage; only creation
        // is forced into the first stage of the selected funnel.
        if ($this->route('business')) {
            return;
        }

        if (! $this->filled('funnel_id')) {
            return;
        }

        $firstStageId = \DB::table('stages')
            ->where('funnel_id', $this->integer('funnel_id'))
            ->whereNull('deleted_at')
            ->orderBy('position')
            ->orderBy('id')
            ->value('id');

        if ($firstStageId) {
            $this->merge(['stage_id' => $firstStageId]);
        }
    }

    public function rules(): array
    {
        $instanceId = $this->integer('instance_id');

        return [
            'instance_id' => ['required', 'integer', 'exists:instances,id'],
            'client_id' => ['required', 'integer', 'exists:clients,id'],
            'user_id' => ['required', 'integer', Rule::exists('users', 'id')->where(function ($query) use ($instanceId): void {
                $query->where('instance_id', $instanceId)
                    ->orWhere('type', 'master');
            })->whereNull('deleted_at')],
            'category_id' => ['required', 'integer', Rule::exists('categories', 'id')->where('instance_id', $instanceId)],
            'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')->where('instance_id', $instanceId)],
            'funnel_id' => ['required', 'integer', Rule::exists('funnels', 'id')->where('instance_id', $instanceId)],
            'stage_id' => ['required', 'integer', 'exists:stages,id'],
            'status' => ['sometimes', 'required', 'integer', Rule::in([0, 1, 2])],
            'value' => ['sometimes', 'integer', 'min:0'],
            'notes' => ['nullable', 'string'],
            'priority' => ['sometimes', Rule::in(['low', 'medium', 'high'])],
            'due_at' => ['nullable', 'date'],
            'loss_reason' => ['nullable', 'string', 'max:255'],
            'custom_data' => ['nullable', 'array'],
            'custom_data.custom_fields' => ['sometimes', 'array'],
            'lead_source_id' => ['nullable', 'integer', Rule::exists('lead_sources', 'id')->where('instance_id', $instanceId)],
            'disposition_id' => ['nullable', 'integer', Rule::exists('dispositions', 'id')->where('instance_id', $instanceId)],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            if (! $this->relationshipsAreConsistent()) {
                $validator->errors()->add('relationships', 'Produto, categoria, funil e fase não possuem uma combinação válida.');
            }
            if ($this->integer('status') === 2 && ! \DB::table('stages')->where('id', $this->integer('stage_id'))->where('is_final', true)->exists()) {
                $validator->errors()->add('status', 'O negócio só pode ser ganho em uma fase final do funil.');
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'client_id' => 'cliente',
            'user_id' => 'responsável',
            'category_id' => 'categoria',
            'product_id' => 'produto',
            'funnel_id' => 'funil',
            'stage_id' => 'fase',
            'lead_source_id' => 'origem do lead',
        ];
    }

    public function messages(): array
    {
        return [
            'client_id.exists' => 'O cliente selecionado não existe.',
            'user_id.exists' => 'O responsável selecionado não pertence à instância atual.',
            'category_id.exists' => 'A categoria selecionada não existe na instância atual.',
            'product_id.exists' => 'O produto selecionado não existe na instância atual.',
            'funnel_id.exists' => 'O funil selecionado não existe na instância atual.',
            'stage_id.exists' => 'A fase selecionada não existe.',
            'lead_source_id.exists' => 'A origem do lead selecionada não existe na instância atual.',
        ];
    }

    private function relationshipsAreConsistent(): bool
    {
        $hasProduct = $this->filled('product_id');
        $productMatchesCategory = ! $hasProduct || \DB::table('category_product')
            ->where('category_id', $this->integer('category_id'))
            ->where('product_id', $this->integer('product_id'))
            ->exists();

        $stageMatchesFunnel = \DB::table('stages')
            ->where('id', $this->integer('stage_id'))
            ->where('funnel_id', $this->integer('funnel_id'))
            ->exists();

        $productMatchesFunnel = ! $hasProduct || \DB::table('funnel_product')
            ->where('product_id', $this->integer('product_id'))
            ->where('funnel_id', $this->integer('funnel_id'))
            ->exists();

        $categoryMatchesFunnel = \DB::table('category_funnel')
            ->where('category_id', $this->integer('category_id'))
            ->where('funnel_id', $this->integer('funnel_id'))
            ->exists();

        return $productMatchesCategory && $stageMatchesFunnel
            && $productMatchesFunnel && $categoryMatchesFunnel;
    }
}
