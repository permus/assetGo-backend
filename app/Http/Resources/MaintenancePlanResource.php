<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenancePlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $scheduledCount = $this->when(isset($this->scheduled_count), (int) $this->scheduled_count, fn () => $this->schedules()->count());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'priority_id' => $this->priority_id,
            'sort' => (int)($this->sort ?? 0),
            'descriptions' => $this->descriptions,
            'category_id' => $this->category_id,
            'plan_type' => $this->plan_type,
            'estimeted_duration' => $this->estimeted_duration,
            'instractions' => $this->instractions,
            'safety_notes' => $this->safety_notes,
            'asset_ids' => $this->asset_ids,
            'frequency_type' => $this->frequency_type,
            'frequency_value' => $this->frequency_value,
            'frequency_unit' => $this->frequency_unit,
            'is_active' => (bool)$this->is_active,
            'scheduled_count' => (int)$scheduledCount,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'checklists' => MaintenancePlanChecklistResource::collection($this->whenLoaded('checklists')),
        ];
    }
}


