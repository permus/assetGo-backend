<?php

namespace App\Http\Requests\Location;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Location;
use App\Models\LocationType;

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
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'location_type_id' => 'required|exists:location_types,id',
            'parent_id' => 'nullable|exists:locations,id',
            'address' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:1000',
            'slug' => 'nullable|string|max:255|unique:locations,slug',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            // Validate hierarchy depth
            if ($this->parent_id) {
                $parent = Location::find($this->parent_id);
                if ($parent && $parent->hierarchy_level >= 3) {
                    $validator->errors()->add('parent_id', 'Maximum hierarchy depth (4 levels) would be exceeded.');
                }

                // Validate company ownership
                if ($parent && $parent->company_id !== $this->user()->company_id) {
                    $validator->errors()->add('parent_id', 'Parent location must belong to your company.');
                }
            }

            // Validate type compatibility with parent (based on actual location level, not type level)
            if ($this->parent_id && $this->location_type_id) {
                $parent = Location::find($this->parent_id);
                $type = LocationType::find($this->location_type_id);
                
                // Check if child type's hierarchy level matches parent location's level + 1
                if ($parent && $type && $type->hierarchy_level !== ($parent->hierarchy_level + 1)) {
                    $validator->errors()->add('location_type_id', 'This location type (Level ' . $type->hierarchy_level . ') cannot be created under a Level ' . $parent->hierarchy_level . ' location. Expected Level ' . ($parent->hierarchy_level + 1) . ' type.');
                }
            }

            // Validate root level type
            if (!$this->parent_id && $this->location_type_id) {
                $type = LocationType::find($this->location_type_id);
                if ($type && $type->hierarchy_level !== 0) {
                    $validator->errors()->add('location_type_id', 'Only top-level location types can be created without a parent.');
                }
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Location name is required',
            'location_type_id.required' => 'Location type is required',
            'location_type_id.exists' => 'Selected location type does not exist',
            'parent_id.exists' => 'Selected parent location does not exist',
            'slug.unique' => 'This slug is already taken',
        ];
    }
}