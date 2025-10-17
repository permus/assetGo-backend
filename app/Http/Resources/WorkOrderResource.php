<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class WorkOrderResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'notes' => $this->notes,
            
            // Dates
            'due_date' => $this->due_date?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            
            // Hours
            'estimated_hours' => $this->estimated_hours,
            'actual_hours' => $this->actual_hours,
            
            // Computed attributes
            'is_overdue' => $this->is_overdue,
            'days_until_due' => $this->days_until_due,
            'days_since_created' => $this->days_since_created,
            'resolution_time_days' => $this->resolution_time_days,
            
            // IDs
            'asset_id' => $this->asset_id,
            'location_id' => $this->location_id,
            'assigned_to' => $this->assigned_to,
            'assigned_by' => $this->assigned_by,
            'created_by' => $this->created_by,
            'company_id' => $this->company_id,
            'priority_id' => $this->priority_id,
            'status_id' => $this->status_id,
            'category_id' => $this->category_id,
            
            // Relationships
            'asset' => $this->whenLoaded('asset', function () {
                return [
                    'id' => $this->asset->id,
                    'name' => $this->asset->name,
                    'asset_id' => $this->asset->asset_id,
                ];
            }),
            
            'location' => $this->whenLoaded('location', function () {
                return [
                    'id' => $this->location->id,
                    'name' => $this->location->name,
                    'full_path' => $this->location->full_path ?? null,
                ];
            }),
            
            'status' => $this->whenLoaded('status', function () {
                return [
                    'id' => $this->status->id,
                    'name' => $this->status->name,
                    'slug' => $this->status->slug,
                ];
            }),
            
            'priority' => $this->whenLoaded('priority', function () {
                return [
                    'id' => $this->priority->id,
                    'name' => $this->priority->name,
                    'slug' => $this->priority->slug,
                ];
            }),
            
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'name' => $this->category->name,
                    'slug' => $this->category->slug,
                ];
            }),
            
            'assigned_to_user' => $this->whenLoaded('assignedTo', function () {
                return [
                    'id' => $this->assignedTo->id,
                    'first_name' => $this->assignedTo->first_name,
                    'last_name' => $this->assignedTo->last_name,
                    'email' => $this->assignedTo->email,
                ];
            }),
            
            'assigned_by_user' => $this->whenLoaded('assignedBy', function () {
                return [
                    'id' => $this->assignedBy->id,
                    'first_name' => $this->assignedBy->first_name,
                    'last_name' => $this->assignedBy->last_name,
                    'email' => $this->assignedBy->email,
                ];
            }),
            
            'created_by_user' => $this->whenLoaded('createdBy', function () {
                return [
                    'id' => $this->createdBy->id,
                    'first_name' => $this->createdBy->first_name,
                    'last_name' => $this->createdBy->last_name,
                    'email' => $this->createdBy->email,
                ];
            }),
            
            'company' => $this->whenLoaded('company', function () {
                return [
                    'id' => $this->company->id,
                    'name' => $this->company->name,
                ];
            }),
            
            // Meta
            'meta' => $this->meta,
        ];
    }
}

