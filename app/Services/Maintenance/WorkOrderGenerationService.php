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

        // Calculate only the next due date based on frequency
        $dueDate = $this->calculateNextDueDate($plan, $startDate);
        
        if (!$dueDate) {
            return [];
        }

        DB::beginTransaction();
        try {
            $workOrder = $this->createWorkOrderFromSchedule($schedule, $plan, $dueDate);
            if ($workOrder) {
                $workOrderIds[] = $workOrder->id;

                // Add parts from maintenance plan
                $this->addPartsToWorkOrder($workOrder, $plan);
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
            'assigned_by' => auth()->id(),
            'created_by' => auth()->id(),
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

}

