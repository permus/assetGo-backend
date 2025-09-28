<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\MaintenanceReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class MaintenanceReportController extends Controller
{
    public function __construct(private MaintenanceReportService $maintenanceReportService) {}

    /**
     * Generate maintenance summary report
     */
    public function summary(Request $request)
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $params = $request->only([
                'date_from', 'date_to', 'location_ids', 'asset_ids', 
                'status_id', 'priority_id', 'assigned_to', 'page', 'page_size'
            ]);
            
            $result = $this->maintenanceReportService->generateSummary($params);
            
            return response()->json([
                'success' => true,
                'data' => $result['data']
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to generate maintenance summary report', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'params' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate maintenance summary report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate maintenance compliance report
     */
    public function compliance(Request $request)
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $params = $request->only([
                'date_from', 'date_to', 'location_ids', 'asset_ids', 
                'page', 'page_size'
            ]);
            
            $result = $this->maintenanceReportService->generateCompliance($params);
            
            return response()->json([
                'success' => true,
                'data' => $result['data']
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to generate maintenance compliance report', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'params' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate maintenance compliance report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate maintenance costs report
     */
    public function costs(Request $request)
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $params = $request->only([
                'date_from', 'date_to', 'location_ids', 'asset_ids', 
                'page', 'page_size'
            ]);
            
            $result = $this->maintenanceReportService->generateCosts($params);
            
            return response()->json([
                'success' => true,
                'data' => $result['data']
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to generate maintenance costs report', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'params' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate maintenance costs report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate downtime analysis report
     */
    public function downtime(Request $request)
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $params = $request->only([
                'date_from', 'date_to', 'location_ids', 'asset_ids', 
                'page', 'page_size'
            ]);
            
            $result = $this->maintenanceReportService->generateDowntime($params);
            
            return response()->json([
                'success' => true,
                'data' => $result['data']
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to generate downtime analysis report', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'params' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate downtime analysis report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate failure analysis report
     */
    public function failureAnalysis(Request $request)
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $params = $request->only([
                'date_from', 'date_to', 'location_ids', 'asset_ids', 
                'page', 'page_size'
            ]);
            
            $result = $this->maintenanceReportService->generateFailureAnalysis($params);
            
            return response()->json([
                'success' => true,
                'data' => $result['data']
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to generate failure analysis report', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'params' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate failure analysis report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate technician performance report
     */
    public function technicianPerformance(Request $request)
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $params = $request->only([
                'date_from', 'date_to', 'page', 'page_size'
            ]);
            
            $result = $this->maintenanceReportService->generateTechnicianPerformance($params);
            
            return response()->json([
                'success' => true,
                'data' => $result['data']
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to generate technician performance report', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'params' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate technician performance report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available maintenance reports
     */
    public function available()
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $reports = $this->maintenanceReportService->getAvailableReports();
            
            return response()->json([
                'success' => true,
                'data' => $reports
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to get available maintenance reports', [
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to get available maintenance reports: ' . $e->getMessage()
            ], 500);
        }
    }
}
