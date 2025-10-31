<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class AIAnalyticsSnapshotResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request): array
    {
        $payload = $this->payload ?? [];
        
        return [
            'id' => (string) $this->id,
            'companyId' => (string) $this->company_id,
            'createdAt' => $this->created_at?->toISOString(),
            'healthScore' => (float) $this->health_score,
            'riskAssets' => $payload['riskAssets'] ?? [],
            'performanceInsights' => $payload['performanceInsights'] ?? [],
            'costOptimizations' => $payload['costOptimizations'] ?? [],
            'trends' => $payload['trends'] ?? [],
            'avgAssetAge' => $payload['avgAssetAge'] ?? null, // Added if provided by service
        ];
    }
}

