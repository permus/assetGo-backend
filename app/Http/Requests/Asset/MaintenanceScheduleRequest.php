<?php

namespace App\Http\Requests\Asset;

use Illuminate\Foundation\Http\FormRequest;

class MaintenanceScheduleRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Add authorization logic as needed
    }

    public function rules()
    {
        return [
            'schedule_type' => 'required|string|max:255',
            'next_due' => 'nullable|date',
            'last_done' => 'nullable|date',
            'frequency' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'status' => 'nullable|string|max:50',
        ];
    }
} 