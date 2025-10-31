<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AIAnalyticsSnapshotResource;
use App\Services\AIAnalyticsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Exception;

class AIAnalyticsController extends Controller
{
    public function __construct(private AIAnalyticsService $analyticsService) {}

    /**
     * Get latest analytics and history
     */
    public function index(): JsonResponse
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $result = $this->analyticsService->getAnalytics();
            
            // Transform latest snapshot using Resource if available
            if ($result['latest']) {
                // The service already formats it, but we'll ensure it uses Resource transformation
                // For now, keep the existing format since formatAnalyticsSnapshot is already called
                // In future, we can refactor to use Resource directly
            }
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to fetch analytics', [
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => config('app.debug')
                    ? 'Failed to fetch analytics: ' . $e->getMessage()
                    : 'Failed to fetch analytics. Please try again later.'
            ], 500);
        }
    }

    /**
     * Generate new analytics
     */
    public function generate(): JsonResponse
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $result = $this->analyticsService->generateAnalytics();
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to generate analytics', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => config('app.debug')
                    ? 'Failed to generate analytics: ' . $e->getMessage()
                    : 'Failed to generate analytics. Please try again later.'
            ], 500);
        }
    }

    /**
     * Export analytics to CSV
     */
    public function export(): JsonResponse|StreamedResponse
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $csv = $this->analyticsService->exportAnalytics();
            
            if (empty($csv)) {
                return response()->json([
                    'success' => false,
                    'error' => 'No analytics data to export'
                ], 404);
            }
            
            $filename = 'ai_analytics_' . date('Y-m-d_H-i-s') . '.csv';
            
            return new StreamedResponse(function () use ($csv) {
                echo $csv;
            }, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
                
        } catch (Exception $e) {
            Log::error('Failed to export analytics', [
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => config('app.debug')
                    ? 'Failed to export analytics: ' . $e->getMessage()
                    : 'Failed to export analytics. Please try again later.'
            ], 500);
        }
    }

    /**
     * Get schedule settings
     */
    public function getSchedule(): JsonResponse
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $settings = $this->analyticsService->getScheduleSettings();
            
            return response()->json([
                'success' => true,
                'data' => $settings
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to fetch schedule settings', [
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => config('app.debug')
                    ? 'Failed to fetch schedule settings: ' . $e->getMessage()
                    : 'Failed to fetch schedule settings. Please try again later.'
            ], 500);
        }
    }

    /**
     * Update schedule settings
     */
    public function updateSchedule(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'enabled' => 'sometimes|boolean',
            'frequency' => 'sometimes|in:daily,weekly,monthly',
            'hourUTC' => 'sometimes|integer|min:0|max:23'
        ], [
            'enabled.boolean' => 'The enabled field must be a boolean value.',
            'frequency.in' => 'The frequency must be one of: daily, weekly, monthly.',
            'hourUTC.integer' => 'The hour UTC must be an integer.',
            'hourUTC.min' => 'The hour UTC must be at least 0.',
            'hourUTC.max' => 'The hour UTC must not exceed 23.'
        ]);
        
        $companyId = Auth::user()->company_id;
        
        try {
            $settings = $this->analyticsService->updateScheduleSettings($request->all());
            
            return response()->json([
                'success' => true,
                'data' => $settings
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to update schedule settings', [
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => config('app.debug')
                    ? 'Failed to update schedule settings: ' . $e->getMessage()
                    : 'Failed to update schedule settings. Please try again later.'
            ], 500);
        }
    }
}
