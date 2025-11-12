<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ScheduleMaintenanceAssignedResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Check if relationships are actually loaded (not MissingValue)
        $schedule = $this->relationLoaded('schedule') ? $this->schedule : null;
        $plan = ($schedule && $schedule->relationLoaded('plan')) ? $schedule->plan : null;
        $checklist_items = ($plan && $plan->relationLoaded('checklists')) ? $plan->checklists : null;
        $responses = $this->relationLoaded('responses') ? $this->responses : null;

        return [
            'id' => $this->id,
            'schedule_maintenance_id' => $this->schedule_maintenance_id,
            'team_id' => $this->team_id,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            
            // Include schedule details when loaded
            'schedule' => $this->when($schedule, function () use ($schedule) {
                return [
                    'id' => $schedule->id,
                    'start_date' => $schedule->start_date,
                    'due_date' => $schedule->due_date,
                    'status' => $schedule->status,
                    'priority_id' => $schedule->priority_id,
                    'asset_ids' => $schedule->asset_ids,
                ];
            }),
            
            // Include plan details when loaded
            'plan' => $this->when($plan, function () use ($plan) {
                return [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'descriptions' => $plan->descriptions,
                    'instractions' => $plan->instractions,
                    'safety_notes' => $plan->safety_notes,
                    'estimeted_duration' => $plan->estimeted_duration,
                    'priority_id' => $plan->priority_id,
                ];
            }),
            
            // Include checklist items when loaded
            'checklist_items' => $this->when($checklist_items, function () use ($checklist_items) {
                return $checklist_items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'title' => $item->title,
                        'type' => $item->type,
                        'description' => $item->description,
                        'is_required' => $item->is_required,
                        'is_safety_critical' => $item->is_safety_critical,
                        'is_photo_required' => $item->is_photo_required,
                        'order' => $item->order,
                    ];
                });
            }),
            
            // Include responses when loaded
            'responses' => $this->when($responses, function () use ($responses) {
                return $responses->map(function ($response) {
                    return [
                        'id' => $response->id,
                        'checklist_item_id' => $response->checklist_item_id,
                        'response_type' => $response->response_type,
                        'response_value' => $response->response_value,
                        'photo_url' => $response->photo_url,
                        'completed_at' => $response->completed_at,
                    ];
                });
            }),
            
            // User details
            'user' => $this->when($this->relationLoaded('user'), function () {
                if ($this->user) {
                    return [
                        'id' => $this->user->id,
                        'first_name' => $this->user->first_name,
                        'last_name' => $this->user->last_name,
                        'email' => $this->user->email,
                    ];
                }
                return null;
            }),
        ];
    }
}


