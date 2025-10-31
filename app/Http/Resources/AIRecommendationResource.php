<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AIRecommendationResource extends JsonResource
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
            'id' => (string) $this->id,
            'type' => $this->rec_type,
            'title' => $this->title,
            'description' => $this->description,
            'impact' => $this->impact,
            'priority' => $this->priority,
            'estimatedSavings' => $this->estimated_savings ? (float) $this->estimated_savings : null,
            'implementationCost' => $this->implementation_cost ? (float) $this->implementation_cost : null,
            'roi' => $this->roi ? (float) $this->roi : null,
            'paybackPeriod' => $this->payback_period,
            'timeline' => $this->timeline,
            'actions' => $this->actions ?? [],
            'confidence' => (float) $this->confidence,
            'implemented' => (bool) $this->implemented,
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}

