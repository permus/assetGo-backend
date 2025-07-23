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
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'category_id' => 'nullable|exists:asset_categories,id',
            'type' => 'nullable|string|max:100',
            'serial_number' => 'required|string|max:255|unique:assets,serial_number',
            'model' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'purchase_price' => 'nullable|numeric',
            'depreciation' => 'nullable|numeric',
            'location_id' => 'nullable|exists:locations,id',
            'department_id' => 'nullable|integer',
            'warranty' => 'nullable|string|max:255',
            'insurance' => 'nullable|string|max:255',
            'health_score' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable|string|max:50',
            'tags' => 'nullable|array',
            'tags.*' => 'exists:asset_tags,id',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,gif,svg|max:5120',
            'meta' => 'nullable|array',
        ];
    }
}
