<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MaintenancePlanChecklistResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'maintenance_plan_id' => $this->maintenance_plan_id,
            'title' => $this->title,
            'type' => $this->type,
            'description' => $this->description,
            'is_required' => (bool)$this->is_required,
            'is_safety_critical' => (bool)$this->is_safety_critical,
            'is_photo_required' => (bool)$this->is_photo_required,
            'order' => (int)($this->order ?? 0),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}


