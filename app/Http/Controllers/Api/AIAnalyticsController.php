<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AIAnalyticsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class AIAnalyticsController extends Controller
{
    public function __construct(private AIAnalyticsService $analyticsService) {}

    /**
     * Get latest analytics and history
     */
    public function index()
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $result = $this->analyticsService->getAnalytics();
            
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
                'error' => 'Failed to fetch analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate new analytics
     */
    public function generate()
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
                'error' => 'Failed to generate analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export analytics to CSV
     */
    public function export()
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
            
            return response($csv)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
                
        } catch (Exception $e) {
            Log::error('Failed to export analytics', [
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to export analytics: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get schedule settings
     */
    public function getSchedule()
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
                'error' => 'Failed to fetch schedule settings: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update schedule settings
     */
    public function updateSchedule(Request $request)
    {
        $request->validate([
            'enabled' => 'boolean',
            'frequency' => 'in:daily,weekly,monthly',
            'hourUTC' => 'integer|min:0|max:23'
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
                'error' => 'Failed to update schedule settings: ' . $e->getMessage()
            ], 500);
        }
    }
}
