<?php

namespace App\Services;

use App\Models\{Asset, WorkOrder, ScheduleMaintenance, AssetMaintenanceSchedule, AssetTransfer};
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardService
{
    public function getDashboardData(int $companyId, Request $request): array
    {
        $today = Carbon::today();
        $in30 = Carbon::today()->addDays(30);
        $weekStart = Carbon::today()->startOfWeek();
        $weekEnd = Carbon::today()->endOfWeek();

        // Assets summary (no 'archived' column; use is_active and archive_reason/soft deletes)
        $totalAssets = Asset::where('company_id', $companyId)->count();
        $activeAssets = Asset::where('company_id', $companyId)
            ->where(function ($q) {
                $q->where('is_active', 1)->orWhereNull('is_active');
            })
            ->whereNull('deleted_at')
            ->count();
        // Consider archived if soft-deleted with is_active = 2
        $archivedAssets = Asset::onlyTrashed()
            ->where('company_id', $companyId)
            ->where('is_active', 2)
            ->count();

        // Placeholder for alerts/investments/utilization until dedicated tables exist
        $criticalAlerts = 0;
        $monthlyInvestment = 0;
        $assetUtilization = null;

        // Work orders
        $activeWorkOrders = WorkOrder::where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereHas('status', function ($q) {
                $q->whereNotIn('slug', ['completed', 'cancelled']);
            })->count();

        $overdue = WorkOrder::where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereHas('status', function ($q) {
                $q->whereNotIn('slug', ['completed', 'cancelled']);
            })
            ->whereDate('due_date', '<', $today)->count();

        $scheduledThisWeek = WorkOrder::where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereHas('status', function ($q) {
                $q->whereNotIn('slug', ['completed', 'cancelled']);
            })
            ->whereBetween('due_date', [$weekStart, $weekEnd])
            ->count();

        // Completion metrics based on work orders, not assets
        $avgDaysToComplete = 0; $completionRate = 0;
        $totalWos = WorkOrder::where('company_id', $companyId)->whereNull('deleted_at')->count();
        $completedWos = WorkOrder::where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->whereHas('status', function ($q) { $q->where('slug', 'completed'); })
            ->select('created_at','completed_at')
            ->get();
        if ($totalWos > 0) {
            $completionRate = round(($completedWos->count() / $totalWos) * 100, 1);
        }
        if ($completedWos->count() > 0) {
            $avgDaysToComplete = round($completedWos->avg(function ($wo) {
                if (!$wo->completed_at) return 0; return Carbon::parse($wo->created_at)->diffInDays(Carbon::parse($wo->completed_at));
            }), 1);
        }

        // Scheduled maintenance next 30 days
        // Count AssetMaintenanceSchedule (schedules created directly on assets)
        $assetMaintenanceCount = AssetMaintenanceSchedule::whereHas('asset', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->whereBetween('next_due', [$today, $in30])
            ->whereNull('deleted_at')
            ->count();
        
        // Count ScheduleMaintenance (schedules from maintenance plans)
        $scheduleMaintenanceCount = ScheduleMaintenance::whereHas('plan', function ($q) use ($companyId) {
                $q->where('company_id', $companyId);
            })
            ->whereBetween('due_date', [$today, $in30])
            ->count();
        
        $scheduledMaintenance = $assetMaintenanceCount + $scheduleMaintenanceCount;

        // Transfers pending approvals (assume status = pending)
        $transferApprovals = AssetTransfer::whereNull('deleted_at')
            ->where('status', 'pending')
            ->count();

        // Asset health
        $averageHealthScore = (float) round((float) (Asset::where('company_id', $companyId)->avg('health_score') ?? 0));
        $uncategorized = Asset::where('company_id', $companyId)->whereNull('category_id')->count();

        return [
            'total_assets' => $totalAssets,
            'active_assets' => $activeAssets,
            'critical_alerts' => $criticalAlerts,
            'monthly_investment' => $monthlyInvestment,
            'asset_utilization' => $assetUtilization,
            'scheduled_maintenance' => $scheduledMaintenance,
            'active_work_orders' => $activeWorkOrders,
            'archived_assets' => $archivedAssets,
            'asset_health' => [
                'total' => $totalAssets,
                'active' => $activeAssets,
                'uncategorized' => $uncategorized,
                'average_health_score' => $averageHealthScore,
            ],
            'maintenance_insights' => [
                'completion_rate' => $completionRate,
                'avg_days_to_complete' => $avgDaysToComplete,
                'total_work_orders' => $totalWos,
                'scheduled_this_week' => $scheduledThisWeek,
                'overdue' => $overdue,
            ],
            'transfer_approvals' => $transferApprovals,
            'ai_insights' => 'AI Active',
        ];
    }
}


