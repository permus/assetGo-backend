<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PredictiveMaintenanceResource extends JsonResource
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
            'assetId' => $this->asset_id,
            'assetName' => $this->asset?->name ?? 'Unknown Asset',
            'riskLevel' => $this->risk_level,
            'predictedFailureDate' => $this->predicted_failure_date?->toISOString(),
            'confidence' => (float) $this->confidence,
            'recommendedAction' => $this->recommended_action,
            'estimatedCost' => (float) $this->estimated_cost,
            'preventiveCost' => (float) $this->preventive_cost,
            'savings' => (float) $this->savings,
            'factors' => $this->factors ?? [],
            'timeline' => $this->timeline ?? [],
            'createdAt' => $this->created_at?->toISOString(),
            'updatedAt' => $this->updated_at?->toISOString(),
        ];
    }
}

