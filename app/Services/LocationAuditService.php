<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class LocationAuditService
{
    public function logCreated(int $locationId, string $locationName, int $userId, ?string $ipAddress = null): void
    {
        Log::info('Location created', [
            'location_id' => $locationId,
            'location_name' => $locationName,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    public function logUpdated(int $locationId, string $locationName, array $changes, int $userId, ?string $ipAddress = null): void
    {
        Log::info('Location updated', [
            'location_id' => $locationId,
            'location_name' => $locationName,
            'changes' => $changes,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    public function logDeleted(int $locationId, string $locationName, int $userId, ?string $ipAddress = null): void
    {
        Log::info('Location deleted', [
            'location_id' => $locationId,
            'location_name' => $locationName,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    public function logMoved(int $locationId, string $locationName, ?int $oldParentId, ?int $newParentId, int $userId, ?string $ipAddress = null): void
    {
        Log::info('Location moved', [
            'location_id' => $locationId,
            'location_name' => $locationName,
            'old_parent_id' => $oldParentId,
            'new_parent_id' => $newParentId,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }

    public function logBulkCreated(int $count, int $userId, ?string $ipAddress = null): void
    {
        Log::info('Bulk location created', [
            'count' => $count,
            'user_id' => $userId,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toDateTimeString(),
        ]);
    }
}

