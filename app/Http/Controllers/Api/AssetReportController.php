<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AssetReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class AssetReportController extends Controller
{
    public function __construct(private AssetReportService $assetReportService) {}

    /**
     * Generate asset summary report
     */
    public function summary(Request $request)
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $params = $request->only([
                'date_from', 'date_to', 'location_ids', 'asset_ids', 
                'status', 'category_id', 'page', 'page_size'
            ]);
            
            $result = $this->assetReportService->generateSummary($params);
            
            return response()->json([
                'success' => true,
                'data' => $result['data']
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to generate asset summary report', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'params' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate asset summary report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate asset utilization report
     */
    public function utilization(Request $request)
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $params = $request->only([
                'date_from', 'date_to', 'location_ids', 'asset_ids', 
                'page', 'page_size'
            ]);
            
            $result = $this->assetReportService->generateUtilization($params);
            
            return response()->json([
                'success' => true,
                'data' => $result['data']
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to generate asset utilization report', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'params' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate asset utilization report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate asset depreciation report
     */
    public function depreciation(Request $request)
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $params = $request->only([
                'date_from', 'date_to', 'location_ids', 'asset_ids', 
                'page', 'page_size'
            ]);
            
            $result = $this->assetReportService->generateDepreciation($params);
            
            return response()->json([
                'success' => true,
                'data' => $result['data']
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to generate asset depreciation report', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'params' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate asset depreciation report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate asset warranty report
     */
    public function warranty(Request $request)
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $params = $request->only([
                'date_from', 'date_to', 'location_ids', 'asset_ids', 
                'page', 'page_size'
            ]);
            
            $result = $this->assetReportService->generateWarranty($params);
            
            return response()->json([
                'success' => true,
                'data' => $result['data']
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to generate asset warranty report', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'params' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate asset warranty report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate asset compliance report
     */
    public function compliance(Request $request)
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $params = $request->only([
                'date_from', 'date_to', 'location_ids', 'asset_ids', 
                'page', 'page_size'
            ]);
            
            $result = $this->assetReportService->generateCompliance($params);
            
            return response()->json([
                'success' => true,
                'data' => $result['data']
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to generate asset compliance report', [
                'company_id' => $companyId,
                'error' => $e->getMessage(),
                'params' => $request->all()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to generate asset compliance report: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available asset reports
     */
    public function available()
    {
        $companyId = Auth::user()->company_id;
        
        try {
            $reports = $this->assetReportService->getAvailableReports();
            
            return response()->json([
                'success' => true,
                'data' => $reports
            ]);
            
        } catch (Exception $e) {
            Log::error('Failed to get available asset reports', [
                'company_id' => $companyId,
                'error' => $e->getMessage()
            ]);
            
            return response()->json([
                'success' => false,
                'error' => 'Failed to get available asset reports: ' . $e->getMessage()
            ], 500);
        }
    }
}
