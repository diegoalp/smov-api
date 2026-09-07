<?php

namespace App\Http\Requests\BusinessRequests;

use App\Models\Business;

class UpdateBusinessRequest extends StoreBusinessRequest
{
    public function rules(): array
    {
        /** @var Business $business */
        $business = $this->route('business');
        $this->merge(array_filter([
            'client_id' => $this->input('client_id', $business->client_id),
            'user_id' => $this->input('user_id', $business->user_id),
            'category_id' => $this->input('category_id', $business->category_id),
            'product_id' => $this->input('product_id', $business->product_id),
            'funnel_id' => $this->input('funnel_id', $business->funnel_id),
            'stage_id' => $this->input('stage_id', $business->stage_id),
        ]));

        $rules = parent::rules();
        foreach (['client_id', 'user_id', 'category_id', 'product_id', 'funnel_id', 'stage_id'] as $field) {
            array_unshift($rules[$field], 'sometimes');
        }

        return $rules;
    }
}
