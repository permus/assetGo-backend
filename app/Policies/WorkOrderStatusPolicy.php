<?php

namespace App\Policies;

use App\Models\User;
use App\Models\WorkOrderStatus;
use Illuminate\Auth\Access\HandlesAuthorization;

class WorkOrderStatusPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return true; // Everyone can view
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, WorkOrderStatus $workOrderStatus): bool
    {
        return true; // Everyone can view
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(['admin', 'company_admin']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, WorkOrderStatus $workOrderStatus): bool
    {
        return $user->hasRole(['admin', 'company_admin']);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, WorkOrderStatus $workOrderStatus): bool
    {
        if ($workOrderStatus->is_management) {
            return false; // Management items cannot be deleted
        }
        
        return $user->hasRole(['admin', 'company_admin']);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, WorkOrderStatus $workOrderStatus): bool
    {
        return $user->hasRole(['admin', 'company_admin']);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, WorkOrderStatus $workOrderStatus): bool
    {
        return false; // No force delete for statuses
    }
}
