<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleMaintenanceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'maintenance_plan_id' => $this->maintenance_plan_id,
            'plan_name' => $this->whenLoaded('plan', function () {
                return $this->plan->name ?? null;
            }),
            'plan_type' => $this->whenLoaded('plan', function () {
                return $this->plan->plan_type;
            }),
            'asset_ids' => $this->asset_ids,
            'assets' => $this->when(isset($this->assets_data), $this->assets_data),
            'start_date' => $this->start_date,
            'due_date' => $this->due_date,
            'status' => $this->status,
            'priority_id' => $this->priority_id,
            // Include priority object when the plan is loaded; priority may be null
            'priority' => $this->whenLoaded('plan', function () {
                if ($this->plan->priority) {
                    return [
                        'id' => $this->plan->priority->id,
                        'name' => $this->plan->priority->name,
                    ];
                }
                return null;
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'assignees' => ScheduleMaintenanceAssignedResource::collection($this->whenLoaded('assignees')),
        ];
    }
}


