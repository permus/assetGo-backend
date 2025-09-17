<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Services\AIAssetAnalyticsService;
use Illuminate\Support\Facades\Log;

class AIAssetAnalyticsController extends Controller
{
    public function __construct(private AIAssetAnalyticsService $svc)
    {
    }

    public function analyze(Request $request)
    {
        // Check request size early
        $contentLength = $request->header('Content-Length');
        if ($contentLength && $contentLength > 20 * 1024 * 1024) {
            return response()->json([
                'success' => false,
                'message' => 'Request too large. Please use smaller images.'
            ], 413);
        }

        $data = $request->validate([
            'assetContext' => ['required', 'array', 'min:1'],
            'assetImages' => ['sometimes', 'array', 'max:5'],
            'assetImages.*' => ['string', 'max:10485760', 'regex:/^[A-Za-z0-9+\/=]+$/'] // Clean base64 only
        ]);

        try {
            $result = $this->svc->analyzePortfolio($data['assetContext'], $data['assetImages'] ?? []);

            // Log successful analysis (without sensitive data)
            Log::info('AI Asset Analytics completed', [
                'user_id' => $request->user()->id,
                'company_id' => $request->user()->company_id,
                'asset_count' => count($data['assetContext']),
                'image_count' => count($data['assetImages'] ?? []),
                'health_score' => $result['healthScore'] ?? null
            ]);

            return response()->json(['success' => true, 'data' => $result]);

        } catch (\Exception $e) {
            // Log error without sensitive data
            Log::error('AI Asset Analytics failed', [
                'user_id' => $request->user()->id,
                'company_id' => $request->user()->company_id,
                'error' => $e->getMessage(),
                'asset_count' => count($data['assetContext'] ?? []),
                'image_count' => count($data['assetImages'] ?? [])
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

}
