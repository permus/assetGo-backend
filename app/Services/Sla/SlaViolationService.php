<?php

namespace App\Services\Sla;

use App\Models\WorkOrder;
use App\Models\SlaDefinition;
use App\Models\WorkOrderSlaViolation;
use App\Models\WorkOrderStatus;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SlaViolationService
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    /**
     * Check for response time violations across all active SLA definitions
     */
    public function checkResponseTimeViolations(): void
    {
        // Get all active SLA definitions for work orders
        $slaDefinitions = SlaDefinition::active()
            ->forWorkOrders()
            ->get();

        foreach ($slaDefinitions as $sla) {
            try {
                $this->checkViolationsForSla($sla);
            } catch (\Exception $e) {
                Log::error('Error checking SLA violations', [
                    'sla_definition_id' => $sla->id,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
            }
        }
    }

    /**
     * Check violations for a specific SLA definition
     */
    protected function checkViolationsForSla(SlaDefinition $sla): void
    {
        // Get completed/cancelled status slugs
        $completedStatusSlugs = WorkOrderStatus::whereIn('slug', ['completed', 'cancelled'])
            ->pluck('id')
            ->toArray();

        // Build query for matching work orders
        $query = WorkOrder::where('company_id', $sla->company_id)
            ->whereNotIn('status_id', $completedStatusSlugs)
            ->with(['priority', 'status', 'createdBy', 'assignedTo']);

        // Filter by category if SLA has category_id
        if ($sla->category_id) {
            $query->where('category_id', $sla->category_id);
        } else {
            // If SLA has no category, only match work orders with no category
            $query->whereNull('category_id');
        }

        // Filter by priority if SLA has priority_level
        if ($sla->priority_level) {
            $query->whereHas('priority', function ($q) use ($sla) {
                $q->where('slug', $sla->priority_level);
            });
        }

        $workOrders = $query->get();

        foreach ($workOrders as $workOrder) {
            // Verify the SLA matches this work order
            if (!$sla->matchesWorkOrder($workOrder)) {
                continue;
            }

            // Check if response time has been violated
            $this->checkResponseTimeViolation($workOrder, $sla);
        }
    }

    /**
     * Check if response time has been violated for a work order
     */
    protected function checkResponseTimeViolation(WorkOrder $workOrder, SlaDefinition $sla): void
    {
        if (!$sla->response_time_hours) {
            return;
        }

        // Calculate violation time
        $violationTime = $workOrder->created_at->addHours($sla->response_time_hours);
        $now = Carbon::now();

        // Check if violation time has passed
        if ($now->lessThan($violationTime)) {
            return; // Not yet violated
        }

        // Check if violation already recorded and notified
        $existingViolation = WorkOrderSlaViolation::where('work_order_id', $workOrder->id)
            ->where('sla_definition_id', $sla->id)
            ->where('violation_type', 'response_time')
            ->whereNotNull('notified_at')
            ->first();

        if ($existingViolation) {
            return; // Already notified
        }

        // Record violation
        $violation = WorkOrderSlaViolation::firstOrCreate(
            [
                'work_order_id' => $workOrder->id,
                'sla_definition_id' => $sla->id,
                'violation_type' => 'response_time',
            ],
            [
                'violated_at' => $violationTime,
            ]
        );

        // Send notifications if not already sent
        if (!$violation->notified_at) {
            $this->sendViolationNotifications($workOrder, $sla, $violation);
        }
    }

    /**
     * Send violation notifications to created_by and assigned_to users
     */
    protected function sendViolationNotifications(
        WorkOrder $workOrder,
        SlaDefinition $sla,
        WorkOrderSlaViolation $violation
    ): void {
        $userIds = [];

        // Add created_by user
        if ($workOrder->created_by) {
            $userIds[] = $workOrder->created_by;
        }

        // Add assigned_to user
        if ($workOrder->assigned_to && !in_array($workOrder->assigned_to, $userIds)) {
            $userIds[] = $workOrder->assigned_to;
        }

        if (empty($userIds)) {
            Log::warning('No users to notify for SLA violation', [
                'work_order_id' => $workOrder->id,
                'sla_definition_id' => $sla->id,
            ]);
            return;
        }

        $message = sprintf(
            "Work order '%s' has exceeded the %.2f hour response time SLA",
            $workOrder->title,
            $sla->response_time_hours
        );

        try {
            $this->notificationService->createForUsers(
                $userIds,
                [
                    'company_id' => $workOrder->company_id,
                    'type' => 'sla_violation',
                    'action' => 'response_time_exceeded',
                    'title' => 'SLA Response Time Exceeded',
                    'message' => $message,
                    'data' => [
                        'workOrderId' => $workOrder->id,
                        'workOrderTitle' => $workOrder->title,
                        'slaDefinitionId' => $sla->id,
                        'slaDefinitionName' => $sla->name,
                        'violationType' => 'response_time',
                        'responseTimeHours' => $sla->response_time_hours,
                        'violatedAt' => $violation->violated_at->toISOString(),
                    ],
                    'created_by' => null, // System notification
                ]
            );

            // Mark violation as notified
            $violation->notified_at = now();
            $violation->save();

            Log::info('SLA violation notifications sent', [
                'work_order_id' => $workOrder->id,
                'sla_definition_id' => $sla->id,
                'user_ids' => $userIds,
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send SLA violation notifications', [
                'work_order_id' => $workOrder->id,
                'sla_definition_id' => $sla->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}

