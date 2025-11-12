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
            'team_id' => [
                'required',
                'integer',
                'exists:users,id',
                // Prevent duplicate assignments - user can only be assigned once per schedule
                function ($attribute, $value, $fail) {
                    $exists = \App\Models\ScheduleMaintenanceAssigned::where('schedule_maintenance_id', $this->schedule_maintenance_id)
                        ->where('team_id', $value)
                        ->exists();
                    
                    if ($exists) {
                        $fail('This user is already assigned to this maintenance schedule.');
                    }
                },
            ],
        ];
    }
}


