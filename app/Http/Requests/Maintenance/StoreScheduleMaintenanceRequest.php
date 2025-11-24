<?php

namespace App\Http\Requests\Maintenance;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'maintenance_plan_id' => 'required|exists:maintenance_plans,id',
            'asset_ids' => 'nullable|array',
            'asset_ids.*' => 'integer',
            'start_date' => 'nullable|date',
            'due_date' => 'prohibited',
            'status' => 'nullable|in:scheduled,in_progress,completed',
            'priority_id' => 'nullable|integer',
            'assigned_user_id' => 'nullable|exists:users,id',
            'assigned_role_id' => 'nullable|integer',
            'assigned_team_id' => 'nullable|integer',
        ];
    }
}


