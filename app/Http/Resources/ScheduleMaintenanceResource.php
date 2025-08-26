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
            'asset_ids' => $this->asset_ids,
            'start_date' => $this->start_date,
            'due_date' => $this->due_date,
            'status' => $this->status,
            'priority_id' => $this->priority_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'assignees' => ScheduleMaintenanceAssignedResource::collection($this->whenLoaded('assignees')),
        ];
    }
}


