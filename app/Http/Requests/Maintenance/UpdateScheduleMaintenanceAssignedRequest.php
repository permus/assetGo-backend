<?php

namespace App\Http\Requests\Maintenance;

use Illuminate\Foundation\Http\FormRequest;

class UpdateScheduleMaintenanceAssignedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'schedule_maintenance_id' => 'sometimes|exists:schedule_maintenance,id',
            'team_id' => 'sometimes|required|integer',
        ];
    }
}


