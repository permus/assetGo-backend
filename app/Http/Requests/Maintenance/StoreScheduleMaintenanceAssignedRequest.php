<?php

namespace App\Http\Requests\Maintenance;

use Illuminate\Foundation\Http\FormRequest;

class StoreScheduleMaintenanceAssignedRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        return [
            'schedule_maintenance_id' => 'required|exists:schedule_maintenance,id',
            'team_id' => 'required|integer',
        ];
    }
}


