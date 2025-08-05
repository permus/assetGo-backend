<?php

namespace App\Http\Requests\Asset;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAssetRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Add authorization logic as needed
    }

    public function rules()
    {
        $assetId = $this->route('asset') ? $this->route('asset')->id : null;
        return [
            'name' => 'sometimes|required|string|max:100',
            'description' => 'nullable|string|max:500',
            'category_id' => 'nullable|exists:asset_categories,id',
            'type' => 'nullable',
            'serial_number' => 'nullable|string|max:255|unique:assets,serial_number,' . $assetId . ',id,company_id,' . ($this->user() ? $this->user()->company_id : 'NULL'),
            'model' => 'nullable|string|max:255',
            'manufacturer' => 'nullable|string|max:255',
            'capacity' => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date|before_or_equal:today',
            'purchase_price' => 'nullable|numeric|min:0.01',
            'depreciation' => 'nullable|numeric',
            'depreciation_life' => 'nullable|integer|min:1',
            'location_id' => 'nullable|exists:locations,id',
            'department_id' => 'nullable|integer',
            'user_id' => 'nullable|exists:users,id',
            'parent_id' => 'nullable|exists:assets,id',
            'company_id' => 'sometimes|required|exists:companies,id',
            'warranty' => 'nullable|string|max:255',
            'insurance' => 'nullable|string|max:255',
            'health_score' => 'nullable|numeric|min:0|max:100',
            'status' => 'nullable',
            'tags' => 'nullable|array',
            'tags.*' => 'string|max:255',
            'images' => 'nullable|array',
            'images.*' => 'string',
            'remove_image_ids' => 'nullable|array',
            'meta' => 'nullable|array',
        ];
    }
}
