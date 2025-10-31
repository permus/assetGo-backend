<?php

namespace App\Services;

use App\Models\Notification;
use App\Models\User;
use App\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Broadcast;
use Exception;

class NotificationService
{
    /**
     * Create a single notification
     */
    public function create(array $data): Notification
    {
        try {
            $notification = Notification::create([
                'company_id' => $data['company_id'],
                'user_id' => $data['user_id'],
                'type' => $data['type'],
                'action' => $data['action'],
                'title' => $data['title'],
                'message' => $data['message'],
                'data' => $data['data'] ?? null,
                'created_by' => $data['created_by'] ?? null,
            ]);

            // Broadcast notification via Pusher
            $this->broadcastNotification($notification);

            return $notification;
        } catch (Exception $e) {
            Log::error('Failed to create notification', [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
            throw $e;
        }
    }

    /**
     * Create notifications for multiple users
     */
    public function createForUsers(array $userIds, array $data): array
    {
        $notifications = [];
        
        foreach ($userIds as $userId) {
            try {
                $notification = $this->create([
                    'company_id' => $data['company_id'],
                    'user_id' => $userId,
                    'type' => $data['type'],
                    'action' => $data['action'],
                    'title' => $data['title'],
                    'message' => $data['message'],
                    'data' => $data['data'] ?? null,
                    'created_by' => $data['created_by'] ?? null,
                ]);
                $notifications[] = $notification;
            } catch (Exception $e) {
                Log::error('Failed to create notification for user', [
                    'user_id' => $userId,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $notifications;
    }

    /**
     * Create notifications for all company users
     */
    public function createForCompany(int $companyId, array $data, ?array $excludeUserIds = null): array
    {
        $userIds = User::where('company_id', $companyId)
            ->where('active', true)
            ->when($excludeUserIds, function ($query) use ($excludeUserIds) {
                return $query->whereNotIn('id', $excludeUserIds);
            })
            ->pluck('id')
            ->toArray();

        if (empty($userIds)) {
            return [];
        }

        return $this->createForUsers($userIds, array_merge($data, ['company_id' => $companyId]));
    }

    /**
     * Create notifications for team members (users with user_type = 'team')
     * Note: In this app, team members are users with user_type='team', not a separate team entity
     */
    public function createForTeam(int $companyId, array $data): array
    {
        // Get all team members (users with user_type='team') in the company
        $userIds = User::where('company_id', $companyId)
            ->where('user_type', 'team')
            ->where('active', true)
            ->pluck('id')
            ->toArray();

        if (empty($userIds)) {
            return [];
        }

        return $this->createForUsers($userIds, array_merge($data, ['company_id' => $companyId]));
    }

    /**
     * Create notifications for admins and company owners
     * Excludes the creator if provided
     */
    public function createForAdminsAndOwners(int $companyId, array $data, ?int $excludeUserId = null): array
    {
        $company = \App\Models\Company::find($companyId);
        if (!$company) {
            return [];
        }

        $query = User::where('company_id', $companyId)
            ->where('active', true)
            ->where(function ($q) use ($company) {
                // Include admins
                $q->where('user_type', 'admin')
                  // Include company owner
                  ->orWhere('id', $company->owner_id);
            });

        if ($excludeUserId) {
            $query->where('id', '!=', $excludeUserId);
        }

        $userIds = $query->pluck('id')->toArray();

        if (empty($userIds)) {
            return [];
        }

        return $this->createForUsers($userIds, array_merge($data, ['company_id' => $companyId]));
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $notificationId, int $userId): bool
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            return false;
        }

        $notification->update([
            'read' => true,
            'read_at' => now(),
        ]);

        // Broadcast updated unread count
        $this->broadcastUnreadCount($userId);

        return true;
    }

    /**
     * Mark all notifications as read for a user
     */
    public function markAllAsRead(int $userId): int
    {
        $count = Notification::where('user_id', $userId)
            ->where('read', false)
            ->update([
                'read' => true,
                'read_at' => now(),
            ]);

        // Broadcast updated unread count
        $this->broadcastUnreadCount($userId);

        return $count;
    }

    /**
     * Delete a notification
     */
    public function delete(int $notificationId, int $userId): bool
    {
        $notification = Notification::where('id', $notificationId)
            ->where('user_id', $userId)
            ->first();

        if (!$notification) {
            return false;
        }

        $notification->delete();

        // Broadcast updated unread count
        $this->broadcastUnreadCount($userId);

        return true;
    }

    /**
     * Get unread count for a user
     */
    public function getUnreadCount(int $userId): int
    {
        return Notification::where('user_id', $userId)
            ->where('read', false)
            ->count();
    }

    /**
     * Broadcast notification via Pusher
     */
    private function broadcastNotification(Notification $notification): void
    {
        try {
            broadcast(new \App\Events\NotificationCreated($notification))
                ->toOthers();
        } catch (Exception $e) {
            Log::warning('Failed to broadcast notification', [
                'notification_id' => $notification->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Broadcast unread count update
     */
    public function broadcastUnreadCount(int $userId): void
    {
        try {
            $count = $this->getUnreadCount($userId);
            broadcast(new \App\Events\UnreadCountUpdated($userId, $count))
                ->toOthers();
        } catch (Exception $e) {
            Log::warning('Failed to broadcast unread count', [
                'user_id' => $userId,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Format asset notification message
     */
    public function formatAssetMessage(string $action, string $assetName): string
    {
        $messages = [
            'created' => "Asset '{$assetName}' was created",
            'updated' => "Asset '{$assetName}' was updated",
            'deleted' => "Asset '{$assetName}' was deleted",
            'duplicated' => "Asset '{$assetName}' was duplicated",
            'archived' => "Asset '{$assetName}' was archived",
            'restored' => "Asset '{$assetName}' was restored",
            'transferred' => "Asset '{$assetName}' was transferred",
            'maintenance_scheduled' => "Maintenance schedule was added to asset '{$assetName}'",
        ];

        return $messages[$action] ?? "Asset '{$assetName}' was {$action}";
    }

    /**
     * Format work order notification message
     */
    public function formatWorkOrderMessage(string $action, string $workOrderTitle): string
    {
        $messages = [
            'created' => "Work order '{$workOrderTitle}' was created",
            'updated' => "Work order '{$workOrderTitle}' was updated",
            'deleted' => "Work order '{$workOrderTitle}' was deleted",
            'assigned' => "Work order '{$workOrderTitle}' was assigned",
            'unassigned' => "Assignment was removed from work order '{$workOrderTitle}'",
            'status_updated' => "Work order '{$workOrderTitle}' status was updated",
            'comment_added' => "A comment was added to work order '{$workOrderTitle}'",
            'parts_added' => "Parts were added to work order '{$workOrderTitle}'",
            'time_logged' => "Time was logged for work order '{$workOrderTitle}'",
        ];

        return $messages[$action] ?? "Work order '{$workOrderTitle}' was {$action}";
    }

    /**
     * Format location notification message
     */
    public function formatLocationMessage(string $action, string $locationName): string
    {
        $messages = [
            'created' => "Location '{$locationName}' was created",
            'updated' => "Location '{$locationName}' was updated",
            'deleted' => "Location '{$locationName}' was deleted",
            'exported' => "Location '{$locationName}' QR codes were exported",
            'asset_added' => "Asset was added to location '{$locationName}'",
            'sub_location_added' => "Sub-location was added to location '{$locationName}'",
        ];

        return $messages[$action] ?? "Location '{$locationName}' was {$action}";
    }

    /**
     * Format team notification message
     */
    public function formatTeamMessage(string $action, string $memberName): string
    {
        $messages = [
            'invite_member' => "Team member '{$memberName}' was invited",
            'activate_member' => "Team member '{$memberName}' was activated",
            'deactivate_member' => "Team member '{$memberName}' was deactivated",
            'remove_member' => "Team member '{$memberName}' was removed",
            'assign_work_order' => "Work order was assigned to team member '{$memberName}'",
        ];

        return $messages[$action] ?? "Team member '{$memberName}' was {$action}";
    }

    /**
     * Format maintenance notification message
     */
    public function formatMaintenanceMessage(string $action, string $planName): string
    {
        $messages = [
            'create_plan' => "Maintenance plan '{$planName}' was created",
            'edit_plan' => "Maintenance plan '{$planName}' was updated",
            'duplicate_plan' => "Maintenance plan '{$planName}' was duplicated",
            'delete_plan' => "Maintenance plan '{$planName}' was deleted",
            'create_schedule' => "Maintenance schedule was created for plan '{$planName}'",
            'edit_schedule' => "Maintenance schedule was updated for plan '{$planName}'",
            'delete_schedule' => "Maintenance schedule was deleted for plan '{$planName}'",
        ];

        return $messages[$action] ?? "Maintenance plan '{$planName}' was {$action}";
    }

    /**
     * Format inventory notification message
     */
    public function formatInventoryMessage(string $action, string $itemName): string
    {
        $messages = [
            'create_part' => "Part '{$itemName}' was created",
            'edit_part' => "Part '{$itemName}' was updated",
            'delete_part' => "Part '{$itemName}' was deleted",
            'archive_part' => "Part '{$itemName}' was archived",
            'restore_part' => "Part '{$itemName}' was restored",
            'import_parts' => "Parts were imported",
            'adjust_stock' => "Stock was adjusted for part '{$itemName}'",
            'transfer_stock' => "Stock was transferred for part '{$itemName}'",
            'reserve_stock' => "Stock was reserved for part '{$itemName}'",
            'update_stock_count' => "Stock count was updated for part '{$itemName}'",
            'create_order' => "Purchase order was created",
            'edit_order' => "Purchase order was updated",
            'receive_items' => "Items were received for purchase order",
        ];

        return $messages[$action] ?? "Inventory item '{$itemName}' was {$action}";
    }

    /**
     * Format report notification message
     */
    public function formatReportMessage(string $action, string $reportName = 'Report'): string
    {
        $messages = [
            'generate_report' => "Report '{$reportName}' was generated",
        ];

        return $messages[$action] ?? "Report '{$reportName}' was {$action}";
    }

    /**
     * Format settings notification message
     */
    public function formatSettingsMessage(string $action, string $settingName = ''): string
    {
        $messages = [
            'update_currency' => "Currency settings were updated",
            'update_language' => "Language settings were updated",
            'toggle_rtl' => "RTL/LTR direction was changed",
            'manage_modules' => "Module settings were updated",
            'update_company_info' => "Company information was updated",
            'update_preferences' => "User preferences were updated",
            'update_date_time_format' => "Date/time format was updated",
            'change_password' => "Password was changed",
        ];

        return $messages[$action] ?? "Settings were {$action}";
    }
}

