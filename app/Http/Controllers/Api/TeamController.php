<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreTeamRequest;
use App\Http\Requests\Team\UpdateTeamRequest;
use App\Models\User;
use App\Models\Location;
use App\Mail\TeamInvitationMail;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    /**
     * Get all team members for the authenticated user's company
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $teams = $user->company->users()
            ->where('user_type', 'team')
            ->with(['roles.permissions', 'locations:id,name,parent_id'])
            ->get();

        // Attach has_full_location_access and assigned work order count
        $teams->transform(function ($t) {
            $t->setAttribute('has_full_location_access', $t->locations->count() === 0);
            
            // Count assigned work orders for this team member
            $assignedCount = \App\Models\WorkOrderAssignment::where('user_id', $t->id)
                ->where('status', 'assigned')
                ->count();
            $t->setAttribute('assigned_work_orders_count', $assignedCount);
            
            return $t;
        });

        return response()->json([
            'success' => true,
            'data' => $teams
        ]);
    }

    /**
     * Store a newly created team member (invite)
     */
    public function store(StoreTeamRequest $request): JsonResponse
    {
        $user = $request->user();

        // Validate that the role belongs to the user's company and load permissions
        $role = $user->company->roles()->with('permissions')->find($request->role_id);
        if (!$role) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid role for this company'
            ], 422);
        }

        // Generate a random password
        $password = Str::random(12);

        // Create the team member (user with user_type = 'team')
        $teamMember = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($password),
            'user_type' => 'team',
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'hourly_rate' => $request->input('hourly_rate'),
        ]);

        // Assign the role to the team member
        $teamMember->roles()->attach($role->id);

        // Apply location scoping based on role's location access
        if ($role->has_location_access) {
            $this->syncUserLocationScope(
                $teamMember,
                $request->input('location_ids'),
                $request->boolean('expand_descendants', true)
            );
        } else {
            // Full access (empty pivot)
            $teamMember->locations()->sync([]);
        }

        // Send invitation email
        $this->sendInvitationEmail($teamMember, $password);

        $teamMember->load(['roles.permissions', 'locations:id,name,parent_id']);

        return response()->json([
            'success' => true,
            'message' => 'Team member invited successfully',
            'data' => array_merge($teamMember->toArray(), [
                'has_full_location_access' => $teamMember->hasFullLocationAccess(),
            ]),
        ], 201);
    }

    /**
     * Display the specified team member
     */
    public function show(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $teamMember = $user->company->users()
            ->where('user_type', 'team')
            ->where('id', $id)
            ->with(['roles.permissions', 'locations:id,name,parent_id'])
            ->first();

        if (!$teamMember) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => array_merge($teamMember->toArray(), [
                'has_full_location_access' => $teamMember->hasFullLocationAccess(),
            ]),
        ]);
    }

    /**
     * Update the specified team member
     */
    public function update(UpdateTeamRequest $request, $id): JsonResponse
    {
        $user = $request->user();
        $teamMember = $user->company->users()
            ->where('user_type', 'team')
            ->where('id', $id)
            ->first();

        if (!$teamMember) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found'
            ], 404);
        }

        // Update basic info
        $teamMember->update($request->only(['first_name', 'last_name', 'email', 'hourly_rate']));

        // Determine current role
        $role = $teamMember->roles()->with('permissions')->first();

        // Update role if provided
        if ($request->has('role_id')) {
            $newRole = $user->company->roles()->with('permissions')->find($request->role_id);
            if (!$newRole) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid role for this company'
                ], 422);
            }
            $teamMember->roles()->sync([$newRole->id]);
            $role = $newRole;
        }

        // Apply/refresh location scope
        if ($role && $role->has_location_access && $request->hasAny(['location_ids','expand_descendants'])) {
            $this->syncUserLocationScope(
                $teamMember,
                $request->input('location_ids'),
                $request->boolean('expand_descendants', true)
            );
        } elseif ($role && !$role->has_location_access) {
            // Full access
            $teamMember->locations()->sync([]);
        }

        $teamMember->load(['roles.permissions', 'locations:id,name,parent_id']);

        return response()->json([
            'success' => true,
            'message' => 'Team member updated successfully',
            'data' => array_merge($teamMember->toArray(), [
                'has_full_location_access' => $teamMember->hasFullLocationAccess(),
            ]),
        ]);
    }

    protected function syncUserLocationScope(User $user, $ids, bool $expand): void
    {
        if (is_array($ids) && count($ids) > 0) {
            if ($expand) {
                $ids = app(\App\Services\LocationScopeService::class)
                    ->expandWithDescendants($ids, $user->company_id);
            }
            $user->locations()->sync($ids);
        } else {
            $user->locations()->sync([]);
        }
    }

    public function locationTree(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $all = Location::where('company_id', $companyId)
            ->select('id','name','parent_id','location_type_id')
            ->orderBy('name')
            ->get();

        $byParent = $all->groupBy('parent_id');
        $build = function($parentId) use (&$build, $byParent) {
            return ($byParent[$parentId] ?? collect())->map(function($n) use ($build) {
                return [
                    'id' => $n->id,
                    'name' => $n->name,
                    'type' => $n->location_type_id,
                    'children' => $build($n->id),
                ];
            })->values();
        };

        return response()->json(['success' => true, 'data' => $build(null)]);
    }

    /**
     * Remove the specified team member
     */
    public function destroy(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $teamMember = $user->company->users()
            ->where('user_type', 'team')
            ->where('id', $id)
            ->first();

        if (!$teamMember) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found'
            ], 404);
        }

        $teamMember->delete();

        return response()->json([
            'success' => true,
            'message' => 'Team member removed successfully'
        ]);
    }

    /**
     * Resend invitation email to team member
     */
    public function resendInvitation(Request $request, $id): JsonResponse
    {
        $user = $request->user();
        $teamMember = $user->company->users()
            ->where('user_type', 'team')
            ->where('id', $id)
            ->first();

        if (!$teamMember) {
            return response()->json([
                'success' => false,
                'message' => 'Team member not found'
            ], 404);
        }

        // Generate a new password
        $password = Str::random(12);
        $teamMember->update(['password' => Hash::make($password)]);

        // Send invitation email
        $this->sendInvitationEmail($teamMember, $password);

        return response()->json([
            'success' => true,
            'message' => 'Invitation email sent successfully'
        ]);
    }

    /**
     * Get team member statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $user = $request->user();
        $company = $user->company;

        $totalTeamMembers = $company->users()->where('user_type', 'team')->count();
        $activeTeamMembers = $company->users()->where('user_type', 'team')->where('email_verified_at', '!=', null)->count();
        $pendingTeamMembers = $company->users()->where('user_type', 'team')->where('email_verified_at', null)->count();

        return response()->json([
            'success' => true,
            'data' => [
                'total_team_members' => $totalTeamMembers,
                'active_team_members' => $activeTeamMembers,
                'pending_team_members' => $pendingTeamMembers,
            ]
        ]);
    }

    /**
     * Get available roles for the company
     */
    public function getAvailableRoles(Request $request): JsonResponse
    {
        $user = $request->user();
        $roles = $user->company->roles()->get();

        return response()->json([
            'success' => true,
            'data' => $roles
        ]);
    }

    /**
     * Send invitation email
     */
    private function sendInvitationEmail($user, $password)
    {
        try {
            // Send the invitation email
            Mail::to($user->email)->send(new TeamInvitationMail($user, $password));
            
            // Log successful email sending
            \Log::info('Team member invitation email sent', [
                'user_id' => $user->id,
                'email' => $user->email,
                'company' => $user->company->name,
            ]);
        } catch (\Exception $e) {
            // Log email sending error
            \Log::error('Failed to send team invitation email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
            ]);
            
            // You might want to throw an exception here or handle it differently
            // For now, we'll just log the error
        }
    }
} 