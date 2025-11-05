<?php

namespace App\Http\Requests\Location;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Location;
use App\Models\LocationType;

class UpdateLocationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $location = $this->route('location');
        return $location && $location->company_id === $this->user()->company_id;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $locationId = $this->route('location')->id;
        
        return [
            'name' => 'sometimes|required|string|max:255',
            'location_code' => 'sometimes|required|string|max:255|unique:locations,location_code,' . $locationId . ',id,user_id,' . $this->user()->id,
            'location_type_id' => 'sometimes|required|exists:location_types,id',
            'parent_id' => 'nullable|exists:locations,id',
            'address' => 'nullable|string|max:500',
            'description' => 'nullable|string|max:1000',
            'slug' => 'nullable|string|max:255|unique:locations,slug,' . $locationId,
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $location = $this->route('location');

            // Prevent self-parenting
            if ($this->parent_id == $location->id) {
                $validator->errors()->add('parent_id', 'A location cannot be its own parent.');
            }

            // Validate circular reference
            if ($this->parent_id && $location->wouldCreateCircularReference($this->parent_id)) {
                $validator->errors()->add('parent_id', 'This would create a circular reference in the hierarchy.');
            }

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

            // Check if location has children and type is being changed
            if ($this->location_type_id && $location->children()->exists()) {
                $newType = LocationType::find($this->location_type_id);
                $currentType = $location->type;
                
                if ($newType && $currentType && $newType->id !== $currentType->id) {
                    // Check if existing children are compatible with new type
                    $incompatibleChildren = $location->children()
                        ->whereHas('type', function ($query) use ($newType) {
                            $query->where('hierarchy_level', '!=', $newType->hierarchy_level + 1);
                        })->exists();
                    
                    if ($incompatibleChildren) {
                        $validator->errors()->add('location_type_id', 'Cannot change type: existing child locations would become incompatible.');
                    }
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
            'location_code.required' => 'Location code is required',
            'location_code.unique' => 'This location code is already in use. Please use a different code.',
            'location_type_id.required' => 'Location type is required',
            'location_type_id.exists' => 'Selected location type does not exist',
            'parent_id.exists' => 'Selected parent location does not exist',
            'slug.unique' => 'This slug is already taken',
        ];
    }
}