<?php

namespace App\Http\Requests\WorkOrder;

use Illuminate\Foundation\Http\FormRequest;

class StoreWorkOrderRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Add authorization logic as needed
    }

    public function rules()
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'priority_id' => 'required|exists:work_order_priority,id',
            'status_id' => 'required|exists:work_order_status,id',
            'category_id' => 'nullable|exists:work_order_categories,id',
            'due_date' => 'nullable|date|after:now',
            'asset_id' => 'nullable|exists:assets,id',
            'location_id' => 'nullable|exists:locations,id',
            'assigned_to' => 'nullable|exists:users,id',
            'estimated_hours' => 'nullable|numeric|min:0|max:999999.99',
            'actual_hours' => 'nullable|numeric|min:0|max:999999.99',
            'notes' => 'nullable|string|max:1000',
            'meta' => 'nullable|array',
        ];
    }

    public function messages()
    {
        return [
            'title.required' => 'Work order title is required.',
            'title.max' => 'Work order title cannot exceed 255 characters.',
            'description.max' => 'Description cannot exceed 1000 characters.',
            'priority_id.required' => 'Priority is required.',
            'priority_id.exists' => 'Selected priority does not exist.',
            'status_id.required' => 'Status is required.',
            'status_id.exists' => 'Selected status does not exist.',
            'category_id.exists' => 'Selected category does not exist.',
            'due_date.after' => 'Due date must be in the future.',
            'asset_id.exists' => 'Selected asset does not exist.',
            'location_id.exists' => 'Selected location does not exist.',
            'assigned_to.exists' => 'Selected user does not exist.',
            'estimated_hours.numeric' => 'Estimated hours must be a number.',
            'estimated_hours.min' => 'Estimated hours cannot be negative.',
            'actual_hours.numeric' => 'Actual hours must be a number.',
            'actual_hours.min' => 'Actual hours cannot be negative.',
            'notes.max' => 'Notes cannot exceed 1000 characters.',
        ];
    }

    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $companyId = $this->user()->company_id;

            // Validate asset belongs to the same company
            if ($this->filled('asset_id')) {
                $asset = \App\Models\Asset::find($this->asset_id);
                if ($asset && $asset->company_id !== $companyId) {
                    $validator->errors()->add('asset_id', 'The selected asset does not belong to your company.');
                }
            }

            // Validate location belongs to the same company
            if ($this->filled('location_id')) {
                $location = \App\Models\Location::find($this->location_id);
                if ($location && $location->company_id !== $companyId) {
                    $validator->errors()->add('location_id', 'The selected location does not belong to your company.');
                }
            }

            // Validate assigned user belongs to the same company
            if ($this->filled('assigned_to')) {
                $user = \App\Models\User::find($this->assigned_to);
                if ($user && $user->company_id !== $companyId) {
                    $validator->errors()->add('assigned_to', 'The selected user does not belong to your company.');
                }
            }
        });
    }
}
