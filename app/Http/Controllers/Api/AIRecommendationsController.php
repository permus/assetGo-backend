<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AIRecommendationsService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class AIRecommendationsController extends Controller
{
    public function __construct(private AIRecommendationsService $recService) {}

    /**
     * Get recommendations with filters and pagination
     */
    public function index(Request $request)
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $filters = $request->only(['type', 'priority', 'impact', 'search', 'minConfidence']);
            $page = $request->get('page', 1);
            $pageSize = $request->get('pageSize', 10);
            
            $result = $this->recService->getRecommendations($filters, $page, $pageSize);
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to fetch recommendations', [
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch recommendations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate new recommendations
     */
    public function generate(Request $request)
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $result = $this->recService->generateRecommendations();
            
            return response()->json([
                'success' => true,
                'data' => $result
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to generate recommendations', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate recommendations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Toggle implementation status
     */
    public function toggleImplementation(Request $request, string $id)
    {
        $request->validate([
            'implemented' => 'required|boolean'
        ]);
        
        $companyId = Auth::user()->company_id;
        
        try {
            $recommendation = $this->recService->toggleImplementation($id, $request->implemented);
            
            return response()->json([
                'success' => true,
                'data' => $recommendation
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to toggle recommendation implementation', [
                'company_id' => $companyId,
                'recommendation_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to update recommendation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export recommendations to CSV
     */
    public function export(Request $request)
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $filters = $request->only(['type', 'priority', 'impact', 'search', 'minConfidence']);
            
            // Get all recommendations (no pagination for export)
            $result = $this->recService->getRecommendations($filters, 1, 1000);
            $recommendations = $result['recommendations'];
            
            // Convert to CSV
            $csv = $this->arrayToCsv($recommendations);
            
            $filename = 'ai_recommendations_' . date('Y-m-d_H-i-s') . '.csv';
            
            return response($csv)
                ->header('Content-Type', 'text/csv')
                ->header('Content-Disposition', 'attachment; filename="' . $filename . '"');
                
        } catch (Exception $e) {
            Log::error('Failed to export recommendations', [
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to export recommendations: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get summary statistics
     */
    public function summary(Request $request)
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $summary = $this->recService->getSummary($companyId);
            
            return response()->json([
                'success' => true,
                'data' => $summary
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to fetch recommendations summary', [
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to fetch summary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Convert array to CSV
     */
    private function arrayToCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }
        
        $keys = array_keys($data[0]);
        $header = implode(',', $keys);
        
        $rows = array_map(function($row) use ($keys) {
            return implode(',', array_map(function($value) {
                if (is_array($value)) {
                    return '"' . implode('; ', $value) . '"';
                }
                return '"' . str_replace('"', '""', $value ?? '') . '"';
            }, array_values(array_intersect_key($row, array_flip($keys)))));
        }, $data);
        
        return $header . "\n" . implode("\n", $rows);
    }
}
