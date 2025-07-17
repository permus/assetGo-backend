<?php

namespace App\Http\Requests\Location;

use Illuminate\Foundation\Http\FormRequest;

class StoreLocationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'address' => 'nullable|string|max:500',
            'location_type_id' => 'required|exists:location_types,id',
            'parent_id' => 'nullable|exists:locations,id',
        ];
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Location name is required',
            'name.max' => 'Location name cannot exceed 255 characters',
            'description.max' => 'Description cannot exceed 1000 characters',
            'address.max' => 'Address cannot exceed 500 characters',
            'location_type_id.required' => 'Location type is required',
            'location_type_id.exists' => 'Selected location type is invalid',
            'parent_id.exists' => 'Selected parent location is invalid',
        ];
    }

    /**
     * Get custom attributes for validator errors.
     */
    public function attributes(): array
    {
        return [
            'location_type_id' => 'location type',
            'parent_id' => 'parent location',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate that parent belongs to the same company
            if ($this->filled('parent_id')) {
                $parent = \App\Models\Location::find($this->parent_id);
                if ($parent && $parent->company_id !== $this->user()->company_id) {
                    $validator->errors()->add('parent_id', 'Parent location must belong to your company');
                }
            }

            // Validate hierarchy depth (max 4 levels)
            if ($this->filled('parent_id')) {
                $parent = \App\Models\Location::find($this->parent_id);
                if ($parent && $parent->hierarchy_level >= 3) {
                    $validator->errors()->add('parent_id', 'Maximum hierarchy depth of 4 levels exceeded');
                }
            }
        });
    }
}