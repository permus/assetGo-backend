<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AIRecommendationResource;
use App\Services\AIRecommendationsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Exception;

class AIRecommendationsController extends Controller
{
    public function __construct(private AIRecommendationsService $recService) {}

    /**
     * Get recommendations with filters and pagination
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $validated = $request->validate([
                'type' => 'sometimes|in:cost_optimization,maintenance,efficiency,compliance',
                'priority' => 'sometimes|in:low,medium,high',
                'impact' => 'sometimes|in:low,medium,high',
                'search' => 'sometimes|string|max:255',
                'minConfidence' => 'sometimes|numeric|min:0|max:100',
                'page' => 'sometimes|integer|min:1',
                'pageSize' => 'sometimes|integer|min:1|max:100'
            ]);
            
            $filters = array_filter([
                'type' => $validated['type'] ?? null,
                'priority' => $validated['priority'] ?? null,
                'impact' => $validated['impact'] ?? null,
                'search' => $validated['search'] ?? null,
                'minConfidence' => $validated['minConfidence'] ?? null,
            ]);
            $page = $validated['page'] ?? 1;
            $pageSize = $validated['pageSize'] ?? 10;
            
            $result = $this->recService->getRecommendations($filters, $page, $pageSize);
            
            $recommendationsCollection = AIRecommendationResource::collection($result['recommendations']);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'recommendations' => $recommendationsCollection->collection->map(function ($resource) {
                        return $resource->resolve();
                    })->all(),
                    'summary' => $result['summary'],
                    'pagination' => $result['pagination']
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to fetch recommendations', [
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => config('app.debug')
                    ? 'Failed to fetch recommendations: ' . $e->getMessage()
                    : 'Failed to fetch recommendations. Please try again later.'
            ], 500);
        }
    }

    /**
     * Generate new recommendations
     */
    public function generate(Request $request): JsonResponse
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $result = $this->recService->generateRecommendations();
            
            $recommendationsCollection = AIRecommendationResource::collection($result['recommendations']);
            
            return response()->json([
                'success' => true,
                'data' => [
                    'recommendations' => $recommendationsCollection->collection->map(function ($resource) {
                        return $resource->resolve();
                    })->all(),
                    'summary' => $result['summary']
                ]
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to generate recommendations', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => config('app.debug')
                    ? 'Failed to generate recommendations: ' . $e->getMessage()
                    : 'Failed to generate recommendations. Please try again later.'
            ], 500);
        }
    }

    /**
     * Toggle implementation status
     */
    public function toggleImplementation(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'implemented' => 'required|boolean'
        ]);
        
        $companyId = Auth::user()->company_id;
        
        try {
            $recommendation = $this->recService->toggleImplementation($id, $request->implemented);
            
            return response()->json([
                'success' => true,
                'data' => new AIRecommendationResource($recommendation)
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to toggle recommendation implementation', [
                'company_id' => $companyId,
                'recommendation_id' => $id,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => config('app.debug')
                    ? 'Failed to update recommendation: ' . $e->getMessage()
                    : 'Failed to update recommendation. Please try again later.'
            ], 500);
        }
    }

    /**
     * Export recommendations to CSV
     */
    public function export(Request $request): JsonResponse|StreamedResponse
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $validated = $request->validate([
                'type' => 'sometimes|in:cost_optimization,maintenance,efficiency,compliance',
                'priority' => 'sometimes|in:low,medium,high',
                'impact' => 'sometimes|in:low,medium,high',
                'search' => 'sometimes|string|max:255',
                'minConfidence' => 'sometimes|numeric|min:0|max:100'
            ]);
            
            $filters = array_filter([
                'type' => $validated['type'] ?? null,
                'priority' => $validated['priority'] ?? null,
                'impact' => $validated['impact'] ?? null,
                'search' => $validated['search'] ?? null,
                'minConfidence' => $validated['minConfidence'] ?? null,
            ]);
            
            // Get all recommendations (no pagination for export)
            $result = $this->recService->getRecommendations($filters, 1, 1000);
            $recommendations = $result['recommendations'];
            
            // Convert Eloquent collection to array format for CSV
            $recommendationsArray = $recommendations->map(function ($rec) {
                return [
                    'Title' => $rec->title,
                    'Type' => ucfirst(str_replace('_', ' ', $rec->rec_type)),
                    'Description' => $rec->description,
                    'Priority' => ucfirst($rec->priority),
                    'Impact' => ucfirst($rec->impact),
                    'Estimated Savings (AED)' => $rec->estimated_savings ? number_format($rec->estimated_savings, 2) : 'N/A',
                    'Implementation Cost (AED)' => $rec->implementation_cost ? number_format($rec->implementation_cost, 2) : 'N/A',
                    'ROI (%)' => $rec->roi ? number_format($rec->roi, 2) : 'N/A',
                    'Payback Period' => $rec->payback_period ?? 'N/A',
                    'Timeline' => $rec->timeline,
                    'Actions' => is_array($rec->actions) ? implode('; ', $rec->actions) : ($rec->actions ?? 'N/A'),
                    'Confidence (%)' => number_format($rec->confidence, 2),
                    'Implemented' => $rec->implemented ? 'Yes' : 'No',
                    'Created At' => $rec->created_at?->format('Y-m-d H:i:s') ?? 'N/A',
                ];
            })->toArray();
            
            // Convert to CSV
            $csv = $this->arrayToCsv($recommendationsArray);
            
            $filename = 'ai_recommendations_' . date('Y-m-d_H-i-s') . '.csv';
            
            return new StreamedResponse(function () use ($csv) {
                echo $csv;
            }, 200, [
                'Content-Type' => 'text/csv',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]);
                
        } catch (Exception $e) {
            Log::error('Failed to export recommendations', [
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => config('app.debug')
                    ? 'Failed to export recommendations: ' . $e->getMessage()
                    : 'Failed to export recommendations. Please try again later.'
            ], 500);
        }
    }

    /**
     * Get summary statistics
     */
    public function summary(Request $request): JsonResponse
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
                'error' => config('app.debug')
                    ? 'Failed to fetch summary: ' . $e->getMessage()
                    : 'Failed to fetch summary. Please try again later.'
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
