<?php

namespace App\Traits;

use Illuminate\Http\JsonResponse;

trait HasPermissions
{
    /**
     * Check if the authenticated user has a specific permission
     */
    protected function checkPermission(string $module, string $action): bool
    {
        $user = auth()->user();
        return $user ? $user->hasPermission($module, $action) : false;
    }

    /**
     * Return a permission denied response
     */
    protected function permissionDenied(string $message = 'Insufficient permissions'): JsonResponse
    {
        return response()->json([
            'success' => false,
            'message' => $message
        ], 403);
    }

    /**
     * Check permission and return response if denied
     */
    protected function requirePermission(string $module, string $action): ?JsonResponse
    {
        if (!$this->checkPermission($module, $action)) {
            return $this->permissionDenied();
        }

        return null;
    }

    /**
     * Get all permissions for the authenticated user
     */
    protected function getUserPermissions(): array
    {
        $user = auth()->user();
        return $user ? $user->getAllPermissions() : [];
    }

    /**
     * Check if user has any of the specified permissions
     */
    protected function hasAnyPermission(array $permissions): bool
    {
        foreach ($permissions as $module => $actions) {
            if (is_string($actions)) {
                if ($this->checkPermission($module, $actions)) {
                    return true;
                }
            } else {
                foreach ($actions as $action) {
                    if ($this->checkPermission($module, $action)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Check if user has all of the specified permissions
     */
    protected function hasAllPermissions(array $permissions): bool
    {
        foreach ($permissions as $module => $actions) {
            if (is_string($actions)) {
                if (!$this->checkPermission($module, $actions)) {
                    return false;
                }
            } else {
                foreach ($actions as $action) {
                    if (!$this->checkPermission($module, $action)) {
                        return false;
                    }
                }
            }
        }

        return true;
    }
} 