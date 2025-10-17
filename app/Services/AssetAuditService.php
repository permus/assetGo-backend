<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class AssetAuditService
{
    /**
     * Log asset creation
     */
    public function logCreated(int $assetId, string $assetName, int $userId, ?string $ipAddress = null): void
    {
        $this->log('asset_created', $assetId, $assetName, null, ['name' => $assetName], $userId, $ipAddress);
    }

    /**
     * Log asset update
     */
    public function logUpdated(int $assetId, string $assetName, array $oldData, array $newData, int $userId, ?string $ipAddress = null): void
    {
        $changes = $this->getChanges($oldData, $newData);
        $this->log('asset_updated', $assetId, $assetName, $oldData, $newData, $userId, $ipAddress, ['changes' => $changes]);
    }

    /**
     * Log asset deletion
     */
    public function logDeleted(int $assetId, string $assetName, ?string $reason, int $userId, ?string $ipAddress = null): void
    {
        $this->log('asset_deleted', $assetId, $assetName, null, ['reason' => $reason], $userId, $ipAddress);
    }

    /**
     * Log asset archive
     */
    public function logArchived(int $assetId, string $assetName, ?string $reason, int $userId, ?string $ipAddress = null): void
    {
        $this->log('asset_archived', $assetId, $assetName, null, ['reason' => $reason], $userId, $ipAddress);
    }

    /**
     * Log asset restore
     */
    public function logRestored(int $assetId, string $assetName, int $userId, ?string $ipAddress = null): void
    {
        $this->log('asset_restored', $assetId, $assetName, null, null, $userId, $ipAddress);
    }

    /**
     * Log asset transfer
     */
    public function logTransferred(int $assetId, string $assetName, array $transferData, int $userId, ?string $ipAddress = null): void
    {
        $this->log('asset_transferred', $assetId, $assetName, null, $transferData, $userId, $ipAddress);
    }

    /**
     * Log bulk operation
     */
    public function logBulkOperation(string $operation, array $assetIds, int $userId, ?string $ipAddress = null): void
    {
        Log::info("Asset bulk operation: {$operation}", [
            'operation' => $operation,
            'asset_ids' => $assetIds,
            'count' => count($assetIds),
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Log asset import
     */
    public function logImport(int $totalRows, int $successCount, int $failCount, int $userId, ?string $ipAddress = null): void
    {
        Log::info('Asset import completed', [
            'total_rows' => $totalRows,
            'success_count' => $successCount,
            'fail_count' => $failCount,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Main logging method
     */
    private function log(
        string $action,
        int $assetId,
        string $assetName,
        $oldValue,
        $newValue,
        int $userId,
        ?string $ipAddress = null,
        array $additionalData = []
    ): void {
        $logData = [
            'action' => $action,
            'asset_id' => $assetId,
            'asset_name' => $assetName,
            'old_value' => $this->sanitize($oldValue),
            'new_value' => $this->sanitize($newValue),
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ];

        if (!empty($additionalData)) {
            $logData = array_merge($logData, $additionalData);
        }

        Log::channel('single')->info("Asset action: {$action}", $logData);
    }

    /**
     * Get changes between old and new data
     */
    private function getChanges(array $oldData, array $newData): array
    {
        $changes = [];
        foreach ($newData as $key => $value) {
            if (isset($oldData[$key]) && $oldData[$key] != $value) {
                $changes[$key] = [
                    'old' => $oldData[$key],
                    'new' => $value
                ];
            }
        }
        return $changes;
    }

    /**
     * Sanitize data for logging
     */
    private function sanitize($data)
    {
        if (is_array($data)) {
            unset($data['password'], $data['token']);
            return $data;
        }
        return $data;
    }
}

