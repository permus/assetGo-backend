<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TeamMemberResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'user_type' => $this->user_type,
            'company_id' => $this->company_id,
            'created_by' => $this->created_by,
            'hourly_rate' => $this->hourly_rate,
            'email_verified_at' => $this->email_verified_at,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            
            // Relationships
            'roles' => $this->whenLoaded('roles', function () {
                return $this->roles->map(function ($role) {
                    return [
                        'id' => $role->id,
                        'name' => $role->name,
                        'description' => $role->description,
                        'has_location_access' => $role->has_location_access,
                        'permissions' => $role->whenLoaded('permissions', function () use ($role) {
                            return $role->permissions;
                        }),
                    ];
                });
            }),
            
            'locations' => $this->whenLoaded('locations', function () {
                return $this->locations->map(function ($location) {
                    return [
                        'id' => $location->id,
                        'name' => $location->name,
                        'parent_id' => $location->parent_id,
                    ];
                });
            }),
            
            // Computed attributes
            'has_full_location_access' => $this->when(
                $this->relationLoaded('locations'),
                fn() => $this->hasFullLocationAccess()
            ),
            
            // Work order assignment counts (if available)
            'assigned_work_orders_count' => $this->when(
                isset($this->assigned_work_orders_count),
                $this->assigned_work_orders_count ?? 0
            ),
            'assigned_work_orders_total_count' => $this->when(
                isset($this->assigned_work_orders_total_count),
                $this->assigned_work_orders_total_count ?? 0
            ),
            'assigned_work_orders_active_count' => $this->when(
                isset($this->assigned_work_orders_active_count),
                $this->assigned_work_orders_active_count ?? 0
            ),
            'assigned_work_orders_completed_count' => $this->when(
                isset($this->assigned_work_orders_completed_count),
                $this->assigned_work_orders_completed_count ?? 0
            ),
        ];
    }
}

