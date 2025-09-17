<?php

namespace App\Services;

use App\Models\AIAnalyticsHistory;
use Illuminate\Support\Facades\Auth;

class AIAssetAnalyticsService {
    public function __construct(private OpenAIService $openAI) {}

    public function analyzePortfolio(array $assetContext, array $dataUrls = []): array {
        // Basic validation
        if (count($assetContext) < 1) {
            abort(422, 'Provide at least one asset for analysis');
        }
        
        if (count($dataUrls) > 5) {
            abort(422, 'Maximum 5 images allowed');
        }

        // Validate base64 images if provided
        foreach ($dataUrls as $url) {
            if (str_starts_with($url, 'data:image/')) {
                $base64 = explode(',', $url)[1] ?? '';
                if (base64_decode($base64, true) === false) {
                    abort(422, 'Invalid base64 image data');
                }
            }
        }

        // Use the existing analyzeImages method with analytics prompt
        $result = $this->openAI->analyzeImages($dataUrls, $this->analyticsPrompt($assetContext));

        // Store analytics history
        $this->storeAnalyticsHistory($assetContext, $dataUrls, $result);

        return $result;
    }

    private function analyticsPrompt(array $assetContext): string {
        $assetData = json_encode($assetContext, JSON_PRETTY_PRINT);
        
        return <<<TEXT
You are an AI Asset Analytics Assistant. 
We provide structured asset portfolio data and asset images. 
Analyze both sources together and respond in JSON only.

Asset Portfolio Data:
{$assetData}

Tasks:
1. Calculate overall asset health score (0–100) based on asset age, maintenance status, and visible image condition.
2. Identify up to 5 high-risk assets with specific reasons (e.g., cracks, corrosion, overdue maintenance) and confidence levels.
3. Suggest 3 performance insights with actionable recommendations.
4. Suggest 3 cost optimization opportunities with estimated savings in AED.
5. If images are unclear or missing, mark reason as "condition uncertain" but still use metadata.

IMPORTANT: Return ONLY valid JSON with no extra text.

Format:
{
  "healthScore": 82,
  "riskAssets": [
    {"name": "Boiler Pump A", "riskLevel": "high", "reason": "Visible corrosion on casing", "confidence": 92}
  ],
  "insights": [
    {"title": "Preventive Maintenance Optimization", "description": "Detected uneven wear in fan blades", "impact": "High", "action": "Schedule inspection"}
  ],
  "optimizations": [
    {"title": "Replace Inefficient Units", "description": "Old HVAC units consuming excess energy", "estimatedSavings": 45000, "paybackPeriod": "12 months", "confidence": 85}
  ]
}
TEXT;
    }

    private function storeAnalyticsHistory(array $assetContext, array $dataUrls, array $result): void {
        try {
            $history = new AIAnalyticsHistory();
            $history->user_id = Auth::id();
            $history->company_id = Auth::user()->company_id ?? null;
            $history->asset_count = count($assetContext);
            $history->image_count = count($dataUrls);
            $history->analytics_result = $result;
            $history->health_score = $result['healthScore'] ?? null;
            $history->save();
        } catch (\Exception $e) {
            // Log but don't fail the request
            \Log::warning('Failed to store analytics history', [
                'error' => $e->getMessage(),
                'user_id' => Auth::id()
            ]);
        }
    }
}
