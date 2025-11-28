<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SlaDefinitionResource extends JsonResource
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
            'companyId' => $this->company_id,
            'name' => $this->name,
            'description' => $this->description,
            'appliesTo' => $this->applies_to,
            'priorityLevel' => $this->priority_level,
            'category' => $this->category,
            'responseTimeHours' => (float) $this->response_time_hours,
            'containmentTimeHours' => $this->containment_time_hours ? (float) $this->containment_time_hours : null,
            'completionTimeHours' => (float) $this->completion_time_hours,
            'businessHoursOnly' => (bool) $this->business_hours_only,
            'workingDays' => $this->working_days ?? [],
            'isActive' => (bool) $this->is_active,
            'createdBy' => $this->created_by,
            'creator' => $this->whenLoaded('creator', function () {
                return [
                    'id' => $this->creator->id,
                    'name' => $this->creator->first_name . ' ' . $this->creator->last_name,
                    'email' => $this->creator->email,
                ];
            }),
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}
