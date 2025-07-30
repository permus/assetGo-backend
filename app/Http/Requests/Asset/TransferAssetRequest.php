<?php

namespace App\Http\Requests\Asset;

use Illuminate\Foundation\Http\FormRequest;

class TransferAssetRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Add authorization logic as needed
    }

    public function rules()
    {
        return [
            'new_location_id' => 'required|exists:locations,id',
            'new_department_id' => 'nullable|exists:departments,id',
            'transfer_reason' => 'required|in:Relocation,Department Change,Maintenance,Upgrade,Storage,Disposal,Other',
            'transfer_date' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:1000',
            'to_user_id' => 'nullable|exists:users,id',
            'condition_report' => 'nullable|string|max:1000',
        ];
    }

    public function messages()
    {
        return [
            'new_location_id.required' => 'New location is required.',
            'new_location_id.exists' => 'Selected location does not exist.',
            'new_department_id.exists' => 'Selected department does not exist.',
            'transfer_reason.required' => 'Transfer reason is required.',
            'transfer_reason.in' => 'Transfer reason must be one of: Relocation, Department Change, Maintenance, Upgrade, Storage, Disposal, Other.',
            'transfer_date.required' => 'Transfer date is required.',
            'transfer_date.date' => 'Transfer date must be a valid date.',
            'transfer_date.before_or_equal' => 'Transfer date cannot be in the future.',
        ];
    }
} 