<?php

namespace App\Http\Requests\Location;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Location;
use App\Models\LocationType;

class BulkCreateLocationRequest extends FormRequest
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
            'locations' => 'required|array|min:1|max:5',
            'locations.*.name' => 'required|string|max:255',
            'locations.*.location_type_id' => 'required|exists:location_types,id',
            'locations.*.parent_id' => 'nullable|exists:locations,id',
            'locations.*.address' => 'nullable|string|max:500',
            'locations.*.description' => 'nullable|string|max:1000',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $locations = $this->input('locations', []);
            
            foreach ($locations as $index => $locationData) {
                $parentId = $locationData['parent_id'] ?? null;
                $typeId = $locationData['location_type_id'] ?? null;

                // Validate hierarchy depth
                if ($parentId) {
                    $parent = Location::find($parentId);
                    if ($parent && $parent->hierarchy_level >= 3) {
                        $validator->errors()->add("locations.{$index}.parent_id", 'Maximum hierarchy depth (4 levels) would be exceeded.');
                    }

                    // Validate company ownership
                    if ($parent && $parent->company_id !== $this->user()->company_id) {
                        $validator->errors()->add("locations.{$index}.parent_id", 'Parent location must belong to your company.');
                    }
                }

                // Validate type compatibility with parent
                if ($parentId && $typeId) {
                    $parent = Location::find($parentId);
                    $type = LocationType::find($typeId);
                    
                    if ($parent && $type && !$type->canBeChildOf($parent->type)) {
                        $validator->errors()->add("locations.{$index}.location_type_id", 'This location type cannot be a child of the selected parent type.');
                    }
                }

                // Validate root level type
                if (!$parentId && $typeId) {
                    $type = LocationType::find($typeId);
                    if ($type && $type->hierarchy_level !== 0) {
                        $validator->errors()->add("locations.{$index}.location_type_id", 'Only top-level location types can be created without a parent.');
                    }
                }
            }

            // Check for duplicate names in the same batch
            $names = collect($locations)->pluck('name')->filter();
            $duplicates = $names->duplicates();
            
            if ($duplicates->isNotEmpty()) {
                // Log warning about duplicates but don't add validation errors
                // The controller will handle duplicate names by adding "copy" suffix
                \Log::info('Bulk location creation with duplicate names detected. Names will be automatically suffixed with "copy".', [
                    'duplicates' => $duplicates->toArray(),
                    'user_id' => $this->user()->id ?? null
                ]);
            }
        });
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'locations.required' => 'At least one location is required',
            'locations.max' => 'Maximum 5 locations can be created at once',
            'locations.*.name.required' => 'Location name is required',
            'locations.*.location_type_id.required' => 'Location type is required',
            'locations.*.location_type_id.exists' => 'Selected location type does not exist',
            'locations.*.parent_id.exists' => 'Selected parent location does not exist',
        ];
    }
}