<?php

namespace App\Services;

use App\Models\MaintenancePlan;
use App\Models\ScheduleMaintenance;
use App\Models\User;
use Illuminate\Support\Facades\Log;

class MaintenanceAuditService
{
    /**
     * Log maintenance plan creation
     */
    public function logPlanCreated(MaintenancePlan $plan, User $creator, string $ipAddress): void
    {
        Log::info('Maintenance plan created', [
            'action' => 'create',
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'plan_type' => $plan->plan_type,
            'is_active' => $plan->is_active,
            'frequency_type' => $plan->frequency_type,
            'frequency_value' => $plan->frequency_value,
            'frequency_unit' => $plan->frequency_unit,
            'asset_count' => is_array($plan->asset_ids) ? count($plan->asset_ids) : 0,
            'created_by_user_id' => $creator->id,
            'created_by_email' => $creator->email,
            'company_id' => $plan->company_id,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log maintenance plan update
     */
    public function logPlanUpdated(MaintenancePlan $plan, array $changes, User $updater, string $ipAddress): void
    {
        $sanitizedChanges = $this->sanitizeChanges($changes);

        if (empty($sanitizedChanges)) {
            return;
        }

        Log::info('Maintenance plan updated', [
            'action' => 'update',
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'changes' => $sanitizedChanges,
            'updated_by_user_id' => $updater->id,
            'updated_by_email' => $updater->email,
            'company_id' => $plan->company_id,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log maintenance plan deletion
     */
    public function logPlanDeleted(MaintenancePlan $plan, User $deleter, string $ipAddress): void
    {
        Log::info('Maintenance plan deleted', [
            'action' => 'delete',
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'plan_type' => $plan->plan_type,
            'was_active' => $plan->is_active,
            'scheduled_count' => $plan->schedules()->count(),
            'deleted_by_user_id' => $deleter->id,
            'deleted_by_email' => $deleter->email,
            'company_id' => $plan->company_id,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log plan activation/deactivation toggle
     */
    public function logPlanToggled(MaintenancePlan $plan, bool $wasActive, User $user, string $ipAddress): void
    {
        $action = $plan->is_active ? 'activated' : 'deactivated';
        
        Log::info("Maintenance plan {$action}", [
            'action' => 'toggle_active',
            'plan_id' => $plan->id,
            'plan_name' => $plan->name,
            'previous_state' => $wasActive ? 'active' : 'inactive',
            'new_state' => $plan->is_active ? 'active' : 'inactive',
            'toggled_by_user_id' => $user->id,
            'toggled_by_email' => $user->email,
            'company_id' => $plan->company_id,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log schedule creation
     */
    public function logScheduleCreated(ScheduleMaintenance $schedule, User $creator, string $ipAddress): void
    {
        Log::info('Maintenance schedule created', [
            'action' => 'create',
            'schedule_id' => $schedule->id,
            'plan_id' => $schedule->maintenance_plan_id,
            'status' => $schedule->status,
            'due_date' => $schedule->due_date?->toDateString(),
            'start_date' => $schedule->start_date?->toDateString(),
            'created_by_user_id' => $creator->id,
            'created_by_email' => $creator->email,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log schedule update
     */
    public function logScheduleUpdated(ScheduleMaintenance $schedule, array $changes, User $updater, string $ipAddress): void
    {
        $sanitizedChanges = $this->sanitizeChanges($changes);

        if (empty($sanitizedChanges)) {
            return;
        }

        Log::info('Maintenance schedule updated', [
            'action' => 'update',
            'schedule_id' => $schedule->id,
            'plan_id' => $schedule->maintenance_plan_id,
            'changes' => $sanitizedChanges,
            'updated_by_user_id' => $updater->id,
            'updated_by_email' => $updater->email,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log schedule deletion
     */
    public function logScheduleDeleted(ScheduleMaintenance $schedule, User $deleter, string $ipAddress): void
    {
        Log::info('Maintenance schedule deleted', [
            'action' => 'delete',
            'schedule_id' => $schedule->id,
            'plan_id' => $schedule->maintenance_plan_id,
            'status' => $schedule->status,
            'deleted_by_user_id' => $deleter->id,
            'deleted_by_email' => $deleter->email,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Sanitize changes for logging
     */
    private function sanitizeChanges(array $changes): array
    {
        $sanitized = [];

        foreach ($changes as $field => $value) {
            // Format the change for logging
            if (is_array($value) && isset($value[0], $value[1])) {
                $sanitized[$field] = [
                    'old' => $value[0],
                    'new' => $value[1],
                ];
            } else {
                $sanitized[$field] = $value;
            }
        }

        return $sanitized;
    }
}

