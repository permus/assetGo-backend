<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\Permission;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class RoleController extends Controller
{
    /**
     * Get all roles for the authenticated user's company
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $roles = $user->company->roles()->with('permissions')->get();

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    /**
     * Store a newly created role
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        
        // Create the role
        $role = Role::create([
            'name' => $request->name,
            'description' => $request->description,
            'company_id' => $user->company_id,
        ]);

        // Create permissions
        Permission::create([
            'role_id' => $role->id,
            'permissions' => $request->permissions,
        ]);

        $role->load('permissions');

        return response()->json([
            'success' => true,
            'message' => 'Role created successfully',
            'data' => $role
        ], 201);
    }

    /**
     * Display the specified role
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $role = $user->company->roles()->with('permissions')->find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $role
        ]);
    }

    /**
     * Update the specified role
     */
    public function update(Request $request, $id): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'permissions' => 'sometimes|required|array',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $role = $user->company->roles()->find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        }

        // Update role
        $role->update($request->only(['name', 'description']));

        // Update permissions if provided
        if ($request->has('permissions')) {
            $role->permissions()->updateOrCreate(
                ['role_id' => $role->id],
                ['permissions' => $request->permissions]
            );
        }

        $role->load('permissions');

        return response()->json([
            'success' => true,
            'message' => 'Role updated successfully',
            'data' => $role
        ]);
    }

    /**
     * Remove the specified role
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $role = $user->company->roles()->find($id);

        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Role not found'
            ], 404);
        }

        $role->delete();

        return response()->json([
            'success' => true,
            'message' => 'Role deleted successfully'
        ]);
    }

    /**
     * Assign role to user
     */
    public function assignToUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $role = $user->company->roles()->find($request->role_id);
        $targetUser = $user->company->users()->find($request->user_id);

        if (!$role || !$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'Role or user not found'
            ], 404);
        }

        $targetUser->roles()->syncWithoutDetaching([$role->id]);

        return response()->json([
            'success' => true,
            'message' => 'Role assigned successfully'
        ]);
    }

    /**
     * Remove role from user
     */
    public function removeFromUser(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role_id' => 'required|exists:roles,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();
        $role = $user->company->roles()->find($request->role_id);
        $targetUser = $user->company->users()->find($request->user_id);

        if (!$role || !$targetUser) {
            return response()->json([
                'success' => false,
                'message' => 'Role or user not found'
            ], 404);
        }

        $targetUser->roles()->detach($role->id);

        return response()->json([
            'success' => true,
            'message' => 'Role removed successfully'
        ]);
    }

    /**
     * Get available permission modules and actions
     */
    public function getAvailablePermissions(): JsonResponse
    {
        $permissions = [
            'location' => [
                'can_view' => false,
                'can_create' => false,
                'can_edit' => false,
                'can_delete' => false,
                'can_export' => false,
            ],
            'assets' => [
                'can_view' => false,
                'can_create' => false,
                'can_edit' => false,
                'can_delete' => false,
                'can_export' => false,
            ],
            'users' => [
                'can_view' => false,
                'can_create' => false,
                'can_edit' => false,
                'can_delete' => false,
                'can_export' => false,
            ],
            'roles' => [
                'can_view' => false,
                'can_create' => false,
                'can_edit' => false,
                'can_delete' => false,
                'can_export' => false,
            ],
            'reports' => [
                'can_view' => false,
                'can_create' => false,
                'can_edit' => false,
                'can_delete' => false,
                'can_export' => false,
            ],
        ];

        return response()->json([
            'success' => true,
            'data' => $permissions
        ]);
    }
} 