<?php

namespace App\Http\Requests\Maintenance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleMaintenanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'maintenance_plan_id' => 'sometimes|exists:maintenance_plans,id',
            'asset_ids' => 'sometimes|nullable|array',
            'asset_ids.*' => 'integer',
            'start_date' => 'sometimes|nullable|date',
            'due_date' => 'prohibited',
            'status' => 'sometimes|nullable|in:scheduled,in_progress,completed',
            'priority_id' => 'sometimes|nullable|integer',
            'assigned_user_id' => 'sometimes|nullable|exists:users,id',
            'assigned_role_id' => 'sometimes|nullable|integer',
            'assigned_team_id' => 'sometimes|nullable|integer',
        ];
    }
}


