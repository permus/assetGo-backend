<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class PredictiveMaintenanceSummaryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        // Handle both array and object formats
        $data = is_array($this->resource) ? $this->resource : (array) $this->resource;
        
        return [
            'totalAssets' => $data['totalAssets'] ?? 0,
            'highRiskCount' => $data['highRiskCount'] ?? 0,
            'totalSavings' => (float) ($data['totalSavings'] ?? 0),
            'averageConfidence' => (float) ($data['averageConfidence'] ?? 0),
            'lastUpdated' => $data['lastUpdated'] ?? null,
        ];
    }
}

