<?php

namespace App\Http\Requests\Maintenance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMaintenancePlanChecklistRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'maintenance_plan_id' => 'sometimes|exists:maintenance_plans,id',
            'title' => 'sometimes|required|string|max:255',
            'type' => 'sometimes|required|in:checkbox,measurements,text_input,photo_capture,pass_fail',
            'description' => 'nullable|string',
            'is_required' => 'nullable|boolean',
            'is_safety_critical' => 'nullable|boolean',
            'is_photo_required' => 'nullable|boolean',
            'order' => 'nullable|integer|min:0',
        ];
    }
}


