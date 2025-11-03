<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\AccountSuspendedNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminUsersController extends Controller
{
    /**
     * Display a listing of users.
     */
    public function index(Request $request)
    {
        $query = User::with('company');

        // Search
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%$search%")
                  ->orWhere('last_name', 'like', "%$search%")
                  ->orWhere('email', 'like', "%$search%");
            });
        }

        // Filter by active status
        if ($request->filled('active')) {
            $query->where('active', $request->boolean('active'));
        }

        // Filter by user type
        if ($request->filled('user_type')) {
            $query->where('user_type', $request->user_type);
    }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $users = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'users' => $users->items(),
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'last_page' => $users->lastPage(),
                    'per_page' => $users->perPage(),
                    'total' => $users->total(),
                    'from' => $users->firstItem(),
                    'to' => $users->lastItem(),
                ],
            ]
        ]);
    }

    /**
     * Display the specified user.
     */
    public function show(User $user)
    {
        // Load company with all fields and roles
        $user->load('company', 'roles');
        
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $user,
            ]
        ]);
    }

    /**
     * Get paginated teams created by the specified user.
     */
    public function getCreatedTeams(User $user, Request $request)
    {
        $query = User::where('created_by', $user->id)
            ->where('user_type', 'team')
            ->with(['roles' => function($query) {
                $query->limit(1); // Only get first role as roles[0]
            }]);

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min($request->get('per_page', 15), 100);
        $teams = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'teams' => $teams->items(),
                'pagination' => [
                    'current_page' => $teams->currentPage(),
                    'last_page' => $teams->lastPage(),
                    'per_page' => $teams->perPage(),
                    'total' => $teams->total(),
                    'from' => $teams->firstItem(),
                    'to' => $teams->lastItem(),
                ],
            ]
        ]);
    }

    /**
     * Update the specified user.
     */
    public function update(Request $request, User $user)
    {
        $request->validate([
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'email' => 'sometimes|email|unique:users,email,' . $user->id,
            'password' => 'sometimes|string|min:8',
            'active' => 'sometimes|boolean',
            'user_type' => 'sometimes|string',
        ]);

        // Store original active status to detect suspension
        $wasActive = $user->active;
        $data = $request->only(['first_name', 'last_name', 'email', 'active', 'user_type']);
        
        if ($request->filled('password')) {
            $data['password'] = Hash::make($request->password);
        }

        // Check if user is being suspended (active changed from true to false)
        $isBeingSuspended = isset($data['active']) && $wasActive && !$data['active'];

        $user->update($data);

        // Handle suspension: revoke tokens and send email
        if ($isBeingSuspended) {
            // Revoke all user tokens to log them out
            $user->tokens()->delete();

            // Send suspension email notification
            try {
                $user->notify(new AccountSuspendedNotification());
            } catch (\Exception $e) {
                // Log error but don't fail the update
                \Log::error('Failed to send account suspension email', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'User updated successfully',
            'data' => [
                'user' => $user->fresh()->load('company'),
            ]
        ]);
    }

    /**
     * Remove the specified user.
     */
    public function destroy(User $user)
    {
        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'User deleted successfully',
        ]);
    }
}
