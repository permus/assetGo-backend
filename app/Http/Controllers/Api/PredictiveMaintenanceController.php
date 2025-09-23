<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PredictiveMaintenanceService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;

class PredictiveMaintenanceController extends Controller
{
    public function __construct(private PredictiveMaintenanceService $service) {}

    /**
     * Get predictions with optional filtering.
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $filters = $request->only(['risk_level', 'min_confidence', 'date_from', 'date_to']);
            
            $result = $this->service->getPredictions($filters);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch predictive maintenance data', [
                'user_id' => $request->user()->id,
                'company_id' => $request->user()->company_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch predictions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate new predictions using AI.
     */
    public function generate(Request $request): JsonResponse
    {
        $request->validate([
            'asset_ids' => 'sometimes|array',
            'asset_ids.*' => 'uuid|exists:assets,id',
            'force_refresh' => 'boolean'
        ]);

        try {
            $assetIds = $request->input('asset_ids', []);
            $forceRefresh = $request->boolean('force_refresh', false);

            $result = $this->service->generatePredictions($assetIds, $forceRefresh);

            Log::info('Predictive maintenance predictions generated', [
                'user_id' => $request->user()->id,
                'company_id' => $request->user()->company_id,
                'prediction_count' => count($result['predictions']),
                'force_refresh' => $forceRefresh
            ]);

            return response()->json([
                'success' => true,
                'data' => $result
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to generate predictive maintenance predictions', [
                'user_id' => $request->user()->id,
                'company_id' => $request->user()->company_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to generate predictions: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export predictions to CSV.
     */
    public function export(Request $request): JsonResponse
    {
        $request->validate([
            'format' => 'required|in:csv,excel',
            'filters' => 'sometimes|array',
            'filters.risk_level' => 'sometimes|in:high,medium,low',
            'filters.min_confidence' => 'sometimes|numeric|min:0|max:100',
            'filters.date_from' => 'sometimes|date',
            'filters.date_to' => 'sometimes|date|after_or_equal:filters.date_from'
        ]);

        try {
            $filters = $request->input('filters', []);
            $format = $request->input('format', 'csv');

            if ($format === 'csv') {
                $csv = $this->service->exportToCsv($filters);
                
                $filename = 'predictive_maintenance_' . now()->format('Y-m-d_H-i-s') . '.csv';
                
                return Response::streamDownload(function () use ($csv) {
                    echo $csv;
                }, $filename, [
                    'Content-Type' => 'text/csv',
                    'Content-Disposition' => 'attachment; filename="' . $filename . '"'
                ]);
            }

            // For Excel export, you would implement similar logic
            return response()->json([
                'success' => false,
                'message' => 'Excel export not yet implemented'
            ], 501);

        } catch (\Exception $e) {
            Log::error('Failed to export predictive maintenance data', [
                'user_id' => $request->user()->id,
                'company_id' => $request->user()->company_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to export data: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get summary statistics.
     */
    public function summary(Request $request): JsonResponse
    {
        try {
            $companyId = $request->user()->company_id;
            
            $summary = $this->service->calculateSummary($companyId);

            return response()->json([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to fetch predictive maintenance summary', [
                'user_id' => $request->user()->id,
                'company_id' => $request->user()->company_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch summary: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear all predictions for the company.
     */
    public function clear(Request $request): JsonResponse
    {
        try {
            $companyId = $request->user()->company_id;
            
            \App\Models\PredictiveMaintenance::where('company_id', $companyId)->delete();

            Log::info('Predictive maintenance predictions cleared', [
                'user_id' => $request->user()->id,
                'company_id' => $companyId
            ]);

            return response()->json([
                'success' => true,
                'message' => 'All predictions have been cleared'
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to clear predictive maintenance predictions', [
                'user_id' => $request->user()->id,
                'company_id' => $request->user()->company_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to clear predictions: ' . $e->getMessage()
            ], 500);
        }
    }
}
