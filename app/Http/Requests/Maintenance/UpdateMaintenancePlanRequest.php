<?php

namespace App\Http\Requests\Maintenance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMaintenancePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $planId = $this->route('maintenancePlan')?->id;
        return [
            'name' => ['sometimes','required','string','max:255', Rule::unique('maintenance_plans','name')->ignore($planId)],
            'priority_id' => 'sometimes|nullable|integer',
            'sort' => 'sometimes|nullable|integer|min:0',
            'descriptions' => 'sometimes|nullable|string',
            'category_id' => 'sometimes|nullable|integer',
            'plan_type' => 'sometimes|nullable|in:preventive,predictive,condition_based',
            'estimeted_duration' => 'sometimes|nullable|integer|min:0',
            'instractions' => 'sometimes|nullable|string',
            'safety_notes' => 'sometimes|nullable|string',
            'asset_ids' => 'sometimes|nullable|array',
            'asset_ids.*' => 'integer',
            'frequency_type' => 'sometimes|nullable|in:time,usage,condition',
            'frequency_value' => 'sometimes|nullable|integer|min:1',
            'frequency_unit' => 'sometimes|nullable|in:days,weeks,months,years',
            'is_active' => 'sometimes|boolean',

            'checklist_items' => 'sometimes|array',
            'checklist_items.*.title' => 'required_with:checklist_items|string|max:255',
            'checklist_items.*.type' => 'required_with:checklist_items|in:checkbox,measurements,text_input,photo_capture,pass_fail',
            'checklist_items.*.description' => 'nullable|string',
            'checklist_items.*.is_required' => 'nullable|boolean',
            'checklist_items.*.is_safety_critical' => 'nullable|boolean',
            'checklist_items.*.is_photo_required' => 'nullable|boolean',
            'checklist_items.*.order' => 'nullable|integer|min:0',
        ];
    }
}


