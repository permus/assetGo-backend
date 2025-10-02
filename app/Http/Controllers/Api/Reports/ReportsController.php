<?php

namespace App\Http\Controllers\Api\Reports;

use App\Http\Controllers\Controller;
use App\Services\ReportsService;
use Illuminate\Http\Request;

class ReportsController extends Controller
{
    public function __construct(private ReportsService $service)
    {
        $this->middleware(['auth:sanctum']);
    }

    // ====== Assets ======
    public function assetsAvailable(): array
    {
        return [
            'asset_summary' => [ 'key' => 'asset_summary', 'name' => 'Asset Summary' ],
            'asset_utilization' => [ 'key' => 'asset_utilization', 'name' => 'Asset Utilization' ],
            'asset_depreciation' => [ 'key' => 'asset_depreciation', 'name' => 'Asset Depreciation' ],
            'asset_warranty' => [ 'key' => 'asset_warranty', 'name' => 'Asset Warranty' ],
            'asset_compliance' => [ 'key' => 'asset_compliance', 'name' => 'Asset Compliance' ],
        ];
    }

    public function assetsSummary(Request $request)
    {
        $companyId = $request->user()->company_id;
        $payload = $this->service->assetSummary($companyId, $request);
        return response()->json([ 'success' => true, 'data' => $payload ]);
    }

    public function assetsUtilization(Request $request)
    {
        $companyId = $request->user()->company_id;
        $payload = $this->service->assetUtilization($companyId, $request);
        return response()->json([ 'success' => true, 'data' => $payload ]);
    }

    public function assetsDepreciation(Request $request)
    {
        $companyId = $request->user()->company_id;
        $payload = $this->service->assetDepreciation($companyId, $request);
        return response()->json([ 'success' => true, 'data' => $payload ]);
    }

    public function assetsWarranty(Request $request)
    {
        $companyId = $request->user()->company_id;
        $payload = $this->service->assetWarranty($companyId, $request);
        return response()->json([ 'success' => true, 'data' => $payload ]);
    }

    public function assetsCompliance(Request $request)
    {
        $companyId = $request->user()->company_id;
        $payload = $this->service->assetCompliance($companyId, $request);
        return response()->json([ 'success' => true, 'data' => $payload ]);
    }

    // ====== Maintenance ======
    public function maintenanceAvailable(): array
    {
        return [
            'maintenance_summary' => [ 'key' => 'maintenance_summary', 'name' => 'Maintenance Summary' ],
            'maintenance_compliance' => [ 'key' => 'maintenance_compliance', 'name' => 'Maintenance Compliance' ],
            'maintenance_costs' => [ 'key' => 'maintenance_costs', 'name' => 'Maintenance Costs' ],
            'maintenance_downtime' => [ 'key' => 'maintenance_downtime', 'name' => 'Maintenance Downtime' ],
            'maintenance_failure_analysis' => [ 'key' => 'maintenance_failure_analysis', 'name' => 'Failure Analysis' ],
            'maintenance_technician_performance' => [ 'key' => 'maintenance_technician_performance', 'name' => 'Technician Performance' ],
        ];
    }

    public function maintenanceSummary(Request $request)
    {
        $companyId = $request->user()->company_id;
        $payload = $this->service->maintenanceSummary($companyId, $request);
        return response()->json([ 'success' => true, 'data' => $payload ]);
    }

    public function maintenanceCompliance(Request $request)
    {
        $companyId = $request->user()->company_id;
        $payload = $this->service->maintenanceCompliance($companyId, $request);
        return response()->json([ 'success' => true, 'data' => $payload ]);
    }

    public function maintenanceCosts(Request $request)
    {
        $companyId = $request->user()->company_id;
        $payload = $this->service->maintenanceCosts($companyId, $request);
        return response()->json([ 'success' => true, 'data' => $payload ]);
    }

    public function maintenanceDowntime(Request $request)
    {
        $companyId = $request->user()->company_id;
        $payload = $this->service->maintenanceDowntime($companyId, $request);
        return response()->json([ 'success' => true, 'data' => $payload ]);
    }

    public function maintenanceFailure(Request $request)
    {
        $companyId = $request->user()->company_id;
        $payload = $this->service->maintenanceFailureAnalysis($companyId, $request);
        return response()->json([ 'success' => true, 'data' => $payload ]);
    }

    public function maintenanceTechnicianPerformance(Request $request)
    {
        $companyId = $request->user()->company_id;
        $payload = $this->service->maintenanceTechnicianPerformance($companyId, $request);
        return response()->json([ 'success' => true, 'data' => $payload ]);
    }
}


