<?php

namespace App\Http\Controllers\Api\Maintenance;

use App\Http\Controllers\Controller;
use App\Models\ScheduleMaintenance;
use App\Models\Asset;
use App\Models\WorkOrder;
use App\Models\WorkOrderPart;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenanceStatsController extends Controller
{
    /**
     * Get history statistics for completed maintenance schedules
     */
    public function history(Request $request)
    {
        $companyId = auth()->user()->company_id;

        // Get all completed schedules with their plans
        $completedSchedules = ScheduleMaintenance::where('status', 'completed')
            ->whereHas('plan', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->with('plan')
            ->get();

        $totalActivities = $completedSchedules->count();

        // Count preventive schedules
        $preventive = $completedSchedules->filter(function ($schedule) {
            return $schedule->plan && $schedule->plan->plan_type === 'preventive';
        })->count();

        // Calculate preventive percentage
        $preventivePercentage = $totalActivities > 0 
            ? round(($preventive / $totalActivities) * 100, 1) 
            : 0.0;

        // Calculate total cost from work orders linked to completed schedules
        $totalCost = 0;
        $workOrderIds = [];
        foreach ($completedSchedules as $schedule) {
            if (is_array($schedule->auto_generated_wo_ids) && !empty($schedule->auto_generated_wo_ids)) {
                $workOrderIds = array_merge($workOrderIds, $schedule->auto_generated_wo_ids);
            }
        }
        $workOrderIds = array_unique($workOrderIds);
        
        if (!empty($workOrderIds)) {
            // Get work orders with assigned users
            $workOrders = WorkOrder::whereIn('id', $workOrderIds)
                ->where('company_id', $companyId)
                ->with('assignedTo')
                ->get();
            
            // Get all parts for these work orders in one query
            $workOrderParts = WorkOrderPart::whereIn('work_order_id', $workOrderIds)
                ->get()
                ->groupBy('work_order_id');
            
            foreach ($workOrders as $wo) {
                // Calculate labor cost (actual_hours * hourly_rate from assigned user)
                $laborCost = 0;
                if ($wo->actual_hours && $wo->assignedTo && $wo->assignedTo->hourly_rate) {
                    $laborCost = (float) $wo->actual_hours * (float) $wo->assignedTo->hourly_rate;
                }
                
                // Calculate parts cost
                $partsCost = 0;
                $parts = $workOrderParts->get($wo->id, collect());
                foreach ($parts as $part) {
                    $partsCost += (float) ($part->qty ?? 0) * (float) ($part->unit_cost ?? 0);
                }
                
                $totalCost += $laborCost + $partsCost;
            }
        }

        // Calculate average duration from actual schedule duration (start_date to due_date)
        // or use actual_hours from work orders if available
        $durations = [];
        foreach ($completedSchedules as $schedule) {
            if ($schedule->start_date && $schedule->due_date) {
                // Calculate duration in hours
                $start = \Carbon\Carbon::parse($schedule->start_date);
                $due = \Carbon\Carbon::parse($schedule->due_date);
                $durationHours = $start->diffInHours($due);
                if ($durationHours > 0) {
                    $durations[] = (float) $durationHours;
                }
            }
        }
        
        // If no durations from schedules, try to get from work orders
        if (empty($durations) && !empty($workOrderIds)) {
            $workOrders = WorkOrder::whereIn('id', $workOrderIds)
                ->where('company_id', $companyId)
                ->whereNotNull('actual_hours')
                ->pluck('actual_hours')
                ->filter()
                ->toArray();
            
            $durations = array_map('floatval', $workOrders);
        }
        
        // Fallback to estimated duration from plans if no actual durations available
        if (empty($durations)) {
            foreach ($completedSchedules as $schedule) {
                if ($schedule->plan && $schedule->plan->estimeted_duration) {
                    $durations[] = (float) $schedule->plan->estimeted_duration;
                }
            }
        }
        
        $avgDuration = count($durations) > 0 
            ? round(array_sum($durations) / count($durations), 1) 
            : 0.0;

        return response()->json([
            'success' => true,
            'data' => [
                'totalActivities' => $totalActivities,
                'preventive' => $preventive,
                'preventivePercentage' => $preventivePercentage,
                'totalCost' => (float) $totalCost,
                'avgDuration' => $avgDuration,
            ],
        ]);
    }

    /**
     * Get analytics statistics for all maintenance schedules
     */
    public function analytics(Request $request)
    {
        $companyId = auth()->user()->company_id;

        // Get all schedules with their plans
        $allSchedules = ScheduleMaintenance::whereHas('plan', function ($query) use ($companyId) {
                $query->where('company_id', $companyId);
            })
            ->with('plan')
            ->get();

        $totalMaintenance = $allSchedules->count();

        // Count preventive schedules
        $preventiveCount = $allSchedules->filter(function ($schedule) {
            return $schedule->plan && $schedule->plan->plan_type === 'preventive';
        })->count();

        // Calculate preventive ratio
        $preventiveRatio = $totalMaintenance > 0 
            ? round(($preventiveCount / $totalMaintenance) * 100, 1) 
            : 0.0;

        // Calculate average cost from all schedules' assets
        $totalCost = 0;
        $assetCount = 0;
        $assetIds = [];
        foreach ($allSchedules as $schedule) {
            if (is_array($schedule->asset_ids) && !empty($schedule->asset_ids)) {
                $assetIds = array_merge($assetIds, $schedule->asset_ids);
            }
        }
        $assetIds = array_unique($assetIds);
        
        if (!empty($assetIds)) {
            $assets = Asset::whereIn('id', $assetIds)
                ->where('company_id', $companyId)
                ->select('purchase_price')
                ->get();
            
            $totalCost = $assets->sum('purchase_price') ?? 0;
            $assetCount = $assets->count();
        }
        
        $avgCost = $assetCount > 0 
            ? round($totalCost / $assetCount, 2) 
            : 0.0;

        // Calculate average duration from all plans
        $durations = [];
        foreach ($allSchedules as $schedule) {
            if ($schedule->plan && $schedule->plan->estimeted_duration) {
                $durations[] = (float) $schedule->plan->estimeted_duration;
            }
        }
        $avgDuration = count($durations) > 0 
            ? round(array_sum($durations) / count($durations), 1) 
            : 0.0;

        return response()->json([
            'success' => true,
            'data' => [
                'totalMaintenance' => $totalMaintenance,
                'preventiveCount' => $preventiveCount,
                'preventiveRatio' => $preventiveRatio,
                'avgCost' => $avgCost,
                'avgDuration' => $avgDuration,
            ],
        ]);
    }
}

