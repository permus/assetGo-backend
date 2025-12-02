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
            'category_id' => 'sometimes|nullable|exists:work_order_categories,id',
            'response_time_hours' => 'sometimes|required|numeric|min:0.01',
            'containment_time_hours' => 'sometimes|nullable|numeric|min:0.01',
            'completion_time_hours' => 'sometimes|required|numeric|min:0.01',
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
            'category_id.exists' => 'Selected category does not exist',
        ];
    }
}
