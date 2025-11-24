<?php

namespace App\Http\Requests\Maintenance;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMaintenancePlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                Rule::unique('maintenance_plans', 'name')->where(function ($query) {
                    return $query->where('company_id', auth()->user()->company_id);
                })
            ],
            'priority_id' => 'nullable|integer',
            'sort' => 'nullable|integer|min:0',
            'descriptions' => 'nullable|string',
            'category_id' => 'nullable|integer',
            'plan_type' => 'nullable|in:preventive,predictive,condition_based',
            'estimeted_duration' => 'nullable|integer|min:0',
            'instractions' => 'nullable|string',
            'safety_notes' => 'nullable|string',
            'asset_ids' => 'nullable|array',
            'asset_ids.*' => 'integer',
            'frequency_type' => 'nullable|in:time,usage,condition',
            'frequency_value' => 'required_if:frequency_type,time|nullable|integer|min:1',
            'frequency_unit' => 'required_if:frequency_type,time|nullable|in:days,weeks,months,years',
            'is_active' => 'nullable|boolean',

            'checklist_items' => 'required|array|min:1',
            'checklist_items.*.title' => 'required|string|max:255',
            'checklist_items.*.type' => 'required|in:checkbox,measurements,text_input,photo_capture,pass_fail',
            'checklist_items.*.description' => 'nullable|string',
            'checklist_items.*.is_required' => 'nullable|boolean',
            'checklist_items.*.is_safety_critical' => 'nullable|boolean',
            'checklist_items.*.is_photo_required' => 'nullable|boolean',
            'checklist_items.*.order' => 'nullable|integer|min:0',
            
            'assigned_user_id' => 'nullable|exists:users,id',
            'assigned_role_id' => 'nullable|integer',
            'assigned_team_id' => 'nullable|integer',
        ];
    }
}


