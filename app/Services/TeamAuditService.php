<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;

class TeamAuditService
{
    /**
     * Log team member creation
     */
    public function logCreated(User $teamMember, User $creator, string $ipAddress): void
    {
        Log::info('Team member created', [
            'action' => 'create',
            'team_member_id' => $teamMember->id,
            'email' => $teamMember->email,
            'name' => "{$teamMember->first_name} {$teamMember->last_name}",
            'role_id' => $teamMember->roles()->first()?->id,
            'role_name' => $teamMember->roles()->first()?->name,
            'hourly_rate' => $teamMember->hourly_rate,
            'created_by_user_id' => $creator->id,
            'created_by_email' => $creator->email,
            'company_id' => $teamMember->company_id,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log team member update with tracked changes
     */
    public function logUpdated(User $teamMember, array $changes, User $updater, string $ipAddress): void
    {
        // Filter out sensitive data and only log meaningful changes
        $sanitizedChanges = $this->sanitizeChanges($changes);

        if (empty($sanitizedChanges)) {
            return; // No meaningful changes to log
        }

        Log::info('Team member updated', [
            'action' => 'update',
            'team_member_id' => $teamMember->id,
            'email' => $teamMember->email,
            'name' => "{$teamMember->first_name} {$teamMember->last_name}",
            'changes' => $sanitizedChanges,
            'updated_by_user_id' => $updater->id,
            'updated_by_email' => $updater->email,
            'company_id' => $teamMember->company_id,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log team member deletion
     */
    public function logDeleted(User $teamMember, User $deleter, string $ipAddress): void
    {
        Log::info('Team member deleted', [
            'action' => 'delete',
            'team_member_id' => $teamMember->id,
            'email' => $teamMember->email,
            'name' => "{$teamMember->first_name} {$teamMember->last_name}",
            'role_id' => $teamMember->roles()->first()?->id,
            'role_name' => $teamMember->roles()->first()?->name,
            'deleted_by_user_id' => $deleter->id,
            'deleted_by_email' => $deleter->email,
            'company_id' => $teamMember->company_id,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Log invitation email resent
     */
    public function logInvitationResent(User $teamMember, User $initiator, string $ipAddress): void
    {
        Log::info('Team member invitation resent', [
            'action' => 'invitation_resent',
            'team_member_id' => $teamMember->id,
            'email' => $teamMember->email,
            'name' => "{$teamMember->first_name} {$teamMember->last_name}",
            'initiated_by_user_id' => $initiator->id,
            'initiated_by_email' => $initiator->email,
            'company_id' => $teamMember->company_id,
            'ip_address' => $ipAddress,
            'timestamp' => now()->toIso8601String(),
        ]);
    }

    /**
     * Sanitize changes to remove sensitive data and format properly
     */
    private function sanitizeChanges(array $changes): array
    {
        $sanitized = [];

        foreach ($changes as $field => $value) {
            // Skip password and other sensitive fields
            if (in_array($field, ['password', 'remember_token', 'api_token'])) {
                continue;
            }

            // Format the change for logging
            if (is_array($value) && isset($value[0], $value[1])) {
                // This is an [old, new] pair
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

