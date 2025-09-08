<?php

namespace App\Http\Requests\Asset;

use Illuminate\Foundation\Http\FormRequest;

class StoreAssetRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Add authorization logic as needed
    }

    public function rules()
    {
        return [
            'name' => 'required|string|max:100',
            'asset_id' => 'nullable|string|max:255|unique:assets,asset_id,NULL,id,company_id,' . ($this->user() ? $this->user()->company_id : 'NULL'),
            'description' => 'nullable|string|max:500',
            'category_id' => 'nullable|exists:asset_categories,id',
            'type' => 'nullable',
            'serial_number' => 'nullable|string|max:255|unique:assets,serial_number,NULL,id,company_id,' . ($this->user() ? $this->user()->company_id : 'NULL'),
            'model' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'brand' => 'nullable|string|max:255',
            'capacity' => 'nullable|string|max:255',
            'dimensions' => 'nullable|string|max:255',
            'weight' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date|before_or_equal:today',
            'purchase_price' => 'nullable|numeric|min:0.01',
            'depreciation' => 'nullable|numeric',
            'depreciation_life' => 'nullable|integer|min:1',
            'location_id' => 'nullable|exists:locations,id',
            'department_id' => 'nullable|integer',
            'parent_id' => 'nullable|exists:assets,id',
            'warranty' => 'nullable|string|max:255',
            'insurance' => 'nullable|string|max:255',
            'health_score' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:255',
            'images' => 'nullable|array',
            'images.*' => 'string',
            'meta' => 'nullable|array',
        ];
    }
}
