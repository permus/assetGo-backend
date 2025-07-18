<?php

namespace App\Http\Requests\Location;

use Illuminate\Foundation\Http\FormRequest;
use App\Models\Location;
use App\Models\LocationType;

class MoveLocationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Check if user owns all locations being moved
        $locationIds = $this->input('location_ids', []);
        $userCompanyId = $this->user()->company_id;
        
        $invalidLocations = Location::whereIn('id', $locationIds)
            ->where('company_id', '!=', $userCompanyId)
            ->exists();
            
        return !$invalidLocations;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'location_ids' => 'required|array|min:1|max:10',
            'location_ids.*' => 'required|exists:locations,id',
            'new_parent_id' => 'nullable|exists:locations,id',
        ];
    }

    /**
     * Configure the validator instance.
     */
    public function withValidator($validator)
    {
        $validator->after(function ($validator) {
            $locationIds = $this->input('location_ids', []);
            $newParentId = $this->input('new_parent_id');
            
            // Validate new parent belongs to same company
            if ($newParentId) {
                $newParent = Location::find($newParentId);
                if ($newParent && $newParent->company_id !== $this->user()->company_id) {
                    $validator->errors()->add('new_parent_id', 'New parent location must belong to your company.');
                    return;
                }
            }

            foreach ($locationIds as $index => $locationId) {
                $location = Location::find($locationId);
                if (!$location) continue;

                // Check circular reference
                if ($location->wouldCreateCircularReference($newParentId)) {
                    $validator->errors()->add("location_ids.{$index}", "Moving location '{$location->name}' would create a circular reference.");
                }

                // Check type compatibility
                if (!$location->canMoveToParent($newParentId)) {
                    $validator->errors()->add("location_ids.{$index}", "Location '{$location->name}' type is not compatible with the new parent.");
                }

                // Check depth limit
                if ($newParentId) {
                    $newParent = Location::find($newParentId);
                    if ($newParent && $newParent->hierarchy_level >= 3) {
                        $validator->errors()->add("location_ids.{$index}", "Moving location '{$location->name}' would exceed maximum hierarchy depth.");
                    }
                }
            }

            // Check if any location being moved is a parent of another in the same batch
            $locations = Location::whereIn('id', $locationIds)->get();
            foreach ($locations as $location) {
                $descendants = $location->descendants()->pluck('id')->flatten();
                $conflictingIds = collect($locationIds)->intersect($descendants);
                
                if ($conflictingIds->isNotEmpty()) {
                    $validator->errors()->add('location_ids', 'Cannot move a location and its descendants in the same operation.');
                    break;
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
            'location_ids.required' => 'At least one location must be selected for moving',
            'location_ids.max' => 'Maximum 10 locations can be moved at once',
            'location_ids.*.exists' => 'One or more selected locations do not exist',
            'new_parent_id.exists' => 'Selected parent location does not exist',
        ];
    }
}