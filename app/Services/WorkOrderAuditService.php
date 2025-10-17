<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class WorkOrderAuditService
{
    /**
     * Log work order creation
     *
     * @param int $workOrderId
     * @param string $title
     * @param int $userId
     * @param string|null $ipAddress
     * @return void
     */
    public function logCreated(int $workOrderId, string $title, int $userId, ?string $ipAddress = null): void
    {
        Log::info('Work order created', [
            'work_order_id' => $workOrderId,
            'title' => $title,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Log work order update
     *
     * @param int $workOrderId
     * @param string $title
     * @param array $changes
     * @param int $userId
     * @param string|null $ipAddress
     * @return void
     */
    public function logUpdated(int $workOrderId, string $title, array $changes, int $userId, ?string $ipAddress = null): void
    {
        Log::info('Work order updated', [
            'work_order_id' => $workOrderId,
            'title' => $title,
            'changes' => $changes,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Log work order deletion
     *
     * @param int $workOrderId
     * @param string $title
     * @param int $userId
     * @param string|null $ipAddress
     * @return void
     */
    public function logDeleted(int $workOrderId, string $title, int $userId, ?string $ipAddress = null): void
    {
        Log::info('Work order deleted', [
            'work_order_id' => $workOrderId,
            'title' => $title,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Log work order status change
     *
     * @param int $workOrderId
     * @param string $title
     * @param mixed $oldStatus
     * @param mixed $newStatus
     * @param int $userId
     * @param string|null $ipAddress
     * @return void
     */
    public function logStatusChanged(int $workOrderId, string $title, $oldStatus, $newStatus, int $userId, ?string $ipAddress = null): void
    {
        Log::info('Work order status changed', [
            'work_order_id' => $workOrderId,
            'title' => $title,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}

