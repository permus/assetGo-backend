<?php

namespace App\Services\Maintenance;

use App\Models\WorkOrder;
use App\Models\WorkOrderPart;
use App\Models\WorkOrderStatus;
use App\Models\MaintenancePlan;
use App\Models\ScheduleMaintenance;
use App\Models\Asset;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WorkOrderGenerationService
{
    public function generateWorkOrdersFromSchedule(ScheduleMaintenance $schedule): array
    {
        $plan = $schedule->plan;
        if (!$plan || $plan->frequency_type !== 'time') {
            return [];
        }

        $workOrderIds = [];
        $startDate = $schedule->start_date ? Carbon::parse($schedule->start_date) : Carbon::now();

        // Generate multiple due dates based on frequency (default: 12 months ahead)
        $dueDates = $this->generateMultipleDueDates($plan, $startDate, 12);
        
        if (empty($dueDates)) {
            return [];
        }

        DB::beginTransaction();
        try {
            foreach ($dueDates as $dueDate) {
                $workOrder = $this->createWorkOrderFromSchedule($schedule, $plan, $dueDate);
                if ($workOrder) {
                    $workOrderIds[] = $workOrder->id;

                    // Add parts from maintenance plan
                    $this->addPartsToWorkOrder($workOrder, $plan);
                }
            }

            // Update schedule with generated work order IDs
            $schedule->update([
                'auto_generated_wo_ids' => $workOrderIds
            ]);

            DB::commit();
            return $workOrderIds;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to generate work orders from schedule', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    protected function calculateNextDueDate(MaintenancePlan $plan, Carbon $startDate): ?Carbon
    {
        $value = (int)($plan->frequency_value ?? 0);
        $unit = $plan->frequency_unit;

        if ($value <= 0 || !$unit) {
            return null;
        }

        $dueDate = match ($unit) {
            'days' => $startDate->copy()->addDays($value),
            'weeks' => $startDate->copy()->addWeeks($value),
            'months' => $startDate->copy()->addMonths($value),
            'years' => $startDate->copy()->addYears($value),
            default => null,
        };

        return $dueDate;
    }

    protected function generateMultipleDueDates(MaintenancePlan $plan, Carbon $startDate, int $monthsAhead = 12): array
    {
        $dueDates = [];
        $currentDate = $startDate->copy();
        $endDate = Carbon::now()->addMonths($monthsAhead);
        $maxOccurrences = 100; // safety limit
        
        $value = (int)($plan->frequency_value ?? 0);
        $unit = $plan->frequency_unit;
        
        if ($value <= 0 || !$unit) {
            return [];
        }
        
        while ($currentDate->lte($endDate) && count($dueDates) < $maxOccurrences) {
            $nextDate = $this->calculateNextDueDate($plan, $currentDate);
            if (!$nextDate) {
                break;
            }
            
            if ($nextDate->lte($endDate)) {
                $dueDates[] = $nextDate->copy();
            }
            $currentDate = $nextDate;
        }
        
        return $dueDates;
    }

    protected function createWorkOrderFromSchedule(
        ScheduleMaintenance $schedule,
        MaintenancePlan $plan,
        Carbon $dueDate
    ): ?WorkOrder {
        // Get first asset from schedule (or use plan's first asset)
        $assetIds = $schedule->asset_ids ?? $plan->asset_ids ?? [];
        $assetId = !empty($assetIds) ? $assetIds[0] : null;

        // Get asset to get location
        $asset = $assetId ? Asset::find($assetId) : null;
        $locationId = $asset ? $asset->location_id : null;

        // Determine assigned user (schedule > plan)
        $assignedUserId = $schedule->assigned_user_id ?? $plan->assigned_user_id ?? null;

        // Get default 'open' status
        $openStatus = WorkOrderStatus::where('slug', 'open')->first();
        $statusId = $openStatus ? $openStatus->id : null;

        // Create work order
        // Note: assigned_by and created_by can be null for auto-generated work orders (e.g., from cron)
        $workOrder = WorkOrder::create([
            'title' => "PPM: {$plan->name} - " . $dueDate->format('Y-m-d'),
            'description' => $plan->descriptions ?? "Preventive maintenance scheduled for {$plan->name}",
            'type' => 'ppm',
            'priority_id' => $plan->priority_id,
            'category_id' => $plan->category_id,
            'status_id' => $statusId,
            'due_date' => $dueDate,
            'asset_id' => $assetId,
            'location_id' => $locationId,
            'assigned_to' => $assignedUserId,
            'assigned_by' => auth()->check() ? auth()->id() : null,
            'created_by' => auth()->check() ? auth()->id() : null,
            'company_id' => $plan->company_id,
            'estimated_hours' => $plan->estimeted_duration,
            'notes' => "Auto-generated from maintenance schedule #{$schedule->id}",
            'meta' => [
                'schedule_id' => $schedule->id,
                'plan_id' => $plan->id,
                'auto_generated' => true,
            ],
        ]);

        return $workOrder;
    }

    protected function addPartsToWorkOrder(WorkOrder $workOrder, MaintenancePlan $plan): void
    {
        $parts = $plan->parts()->with('part')->get();

        foreach ($parts as $planPart) {
            if (!$planPart->part) {
                continue;
            }

            WorkOrderPart::create([
                'work_order_id' => $workOrder->id,
                'part_id' => $planPart->part_id,
                'qty' => $planPart->default_qty ?? 1,
                'unit_cost' => $planPart->part->unit_cost ?? null,
                'status' => 'reserved',
            ]);
        }
    }

    /**
     * Extend work orders for a schedule starting from a specific date
     * This method generates new work orders while avoiding duplicates
     */
    public function extendWorkOrdersForSchedule(ScheduleMaintenance $schedule, Carbon $startFromDate): array
    {
        $plan = $schedule->plan;
        if (!$plan || $plan->frequency_type !== 'time') {
            return [];
        }

        // Generate due dates starting from the provided start date
        $dueDates = $this->generateMultipleDueDates($plan, $startFromDate, 12);
        
        if (empty($dueDates)) {
            return [];
        }

        // Filter out due dates that already have work orders
        $newDueDates = [];
        foreach ($dueDates as $dueDate) {
            $exists = WorkOrder::where('meta->schedule_id', $schedule->id)
                ->whereDate('due_date', $dueDate->toDateString())
                ->exists();
            
            if (!$exists) {
                $newDueDates[] = $dueDate;
            }
        }

        if (empty($newDueDates)) {
            return [];
        }

        $workOrderIds = [];
        $existingWorkOrderIds = $schedule->auto_generated_wo_ids ?? [];

        DB::beginTransaction();
        try {
            foreach ($newDueDates as $dueDate) {
                $workOrder = $this->createWorkOrderFromSchedule($schedule, $plan, $dueDate);
                if ($workOrder) {
                    $workOrderIds[] = $workOrder->id;

                    // Add parts from maintenance plan
                    $this->addPartsToWorkOrder($workOrder, $plan);
                }
            }

            // Merge new work order IDs with existing ones
            $allWorkOrderIds = array_unique(array_merge($existingWorkOrderIds, $workOrderIds));

            // Update schedule with merged work order IDs
            $schedule->update([
                'auto_generated_wo_ids' => array_values($allWorkOrderIds)
            ]);

            DB::commit();
            return $workOrderIds;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to extend work orders for schedule', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

}

