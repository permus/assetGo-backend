<?php

namespace App\Http\Requests\Sla;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSlaDefinitionRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $slaDefinitionId = $this->route('slaDefinition');

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
                Rule::unique('sla_definitions', 'name')
                    ->where(function ($query) {
                        return $query->where('company_id', auth()->user()->company_id);
                    })
                    ->ignore($slaDefinitionId)
            ],
            'description' => 'sometimes|nullable|string',
            'applies_to' => 'sometimes|required|in:work_orders,maintenance,both',
            'priority_level' => 'sometimes|nullable|in:low,medium,high,critical,ppm',
            'category' => 'sometimes|nullable|string|max:255',
            'response_time_hours' => 'sometimes|required|numeric|min:0.01',
            'containment_time_hours' => 'sometimes|nullable|numeric|min:0.01',
            'completion_time_hours' => 'sometimes|required|numeric|min:0.01',
            'business_hours_only' => 'sometimes|nullable|boolean',
            'working_days' => 'sometimes|required|array|min:1',
            'working_days.*' => 'required|string|in:monday,tuesday,wednesday,thursday,friday,saturday,sunday',
            'is_active' => 'sometimes|nullable|boolean',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'SLA rule name is required',
            'name.unique' => 'An SLA rule with this name already exists',
            'applies_to.required' => 'Please select what this SLA applies to',
            'applies_to.in' => 'Invalid applies to value',
            'response_time_hours.required' => 'Response time is required',
            'response_time_hours.numeric' => 'Response time must be a number',
            'response_time_hours.min' => 'Response time must be greater than 0',
            'completion_time_hours.required' => 'Completion time is required',
            'completion_time_hours.numeric' => 'Completion time must be a number',
            'completion_time_hours.min' => 'Completion time must be greater than 0',
            'working_days.required' => 'At least one working day must be selected',
            'working_days.array' => 'Working days must be an array',
            'working_days.min' => 'At least one working day must be selected',
        ];
    }
}
