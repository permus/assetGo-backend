<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Team\StoreTeamRequest;
use App\Http\Requests\Team\UpdateTeamRequest;
use App\Models\User;
use App\Models\WorkOrderAssignment;
use App\Models\WorkOrder;
use App\Models\WorkOrderTimeLog;
use App\Models\Location;
use App\Mail\TeamInvitationMail;
use App\Services\TeamCacheService;
use App\Services\TeamAuditService;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class TeamController extends Controller
{
    protected TeamCacheService $cacheService;
    protected TeamAuditService $auditService;
    protected NotificationService $notificationService;

    public function __construct(TeamCacheService $cacheService, TeamAuditService $auditService, NotificationService $notificationService)
    {
        $this->cacheService = $cacheService;
        $this->auditService = $auditService;
        $this->notificationService = $notificationService;
    }

    /**
     * Get all team members for the authenticated user's company
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $perPage = min((int) $request->get('per_page', 15), 100);

        $query = $user->company->users()
            ->where('user_type', 'team')
            ->with(['roles.permissions', 'locations:id,name,parent_id']);

        // Search filter (by first/last name, full name, or email)
        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'like', "%{$search}%");
            });
        }

        // Role filter (by id or name)
        if ($request->filled('role_id')) {
            $roleId = (int) $request->get('role_id');
            $query->whereHas('roles', function ($q) use ($roleId) {
                $q->where('roles.id', $roleId);
            });
        } elseif ($request->filled('role_name')) {
            $roleName = $request->get('role_name');
            $query->whereHas('roles', function ($q) use ($roleName) {
                $q->where('roles.name', 'like', "%{$roleName}%");
            });
        }

        // Status filter (active = verified, inactive = not verified)
        if ($status = $request->get('status')) {
            if (strtolower($status) === 'active') {
                $query->whereNotNull('email_verified_at');
            } elseif (strtolower($status) === 'inactive') {
                $query->whereNull('email_verified_at');
            }
        }

        // Active status filter (for the new active column)
        if ($request->has('active')) {
            $active = $request->boolean('active');
            $query->where('active', $active);
        }

        // Type filter (maps 'technician' to 'team') - kept for forward compatibility
        if ($type = $request->get('type')) {
            $type = strtolower($type);
            if ($type === 'technician') {
                // Already constrained to user_type = 'team'
            }
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = strtolower($request->get('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
        switch ($sortBy) {
            case 'name':
                $query->orderBy('first_name', $sortDir)->orderBy('last_name', $sortDir);
                break;
            case 'email':
                $query->orderBy('email', $sortDir);
                break;
            case 'role_name':
                $query->orderByRaw("(SELECT name FROM roles WHERE roles.id = (SELECT role_id FROM user_roles WHERE user_roles.user_id = users.id LIMIT 1)) {$sortDir}");
                break;
            case 'created_at':
            default:
                $query->orderBy('created_at', $sortDir);
                break;
        }

        $paginator = $query->paginate($perPage);
        $teamItems = collect($paginator->items());

        $teamIds = $teamItems->pluck('id')->all();

        $countsByUser = collect();
        if (!empty($teamIds)) {
            $countsByUser = WorkOrderAssignment::select(
                    'user_id',
                    DB::raw('COUNT(*) as total_count'),
                    DB::raw("SUM(CASE WHEN status IN ('assigned', 'accepted') THEN 1 ELSE 0 END) as active_count"),
                    DB::raw("SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_count"),
                    DB::raw("SUM(CASE WHEN status = 'assigned' THEN 1 ELSE 0 END) as assigned_only_count")
                )
                ->whereIn('user_id', $teamIds)
                ->groupBy('user_id')
                ->get()
                ->keyBy('user_id');
        }

        // Attach has_full_location_access and assignment counts
        $teamItems->transform(function ($t) use ($countsByUser) {
            $t->setAttribute('has_full_location_access', $t->locations->count() === 0);

            $counts = $countsByUser->get($t->id);
            $total = $counts->total_count ?? 0;
            $active = $counts->active_count ?? 0;
            $completed = $counts->completed_count ?? 0;
            $assignedOnly = $counts->assigned_only_count ?? 0; // backward compatibility

            $t->setAttribute('assigned_work_orders_total_count', (int) $total);
            $t->setAttribute('assigned_work_orders_active_count', (int) $active);
            $t->setAttribute('assigned_work_orders_completed_count', (int) $completed);
            // Keep original field for compatibility (counts only status = 'assigned')
            $t->setAttribute('assigned_work_orders_count', (int) $assignedOnly);

            return $t;
        });

        return response()->json([
            'success' => true,
            'data' => [
                'teams' => $teamItems->values(),
                'pagination' => [
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'from' => $paginator->firstItem(),
                    'to' => $paginator->lastItem(),
                ],
                'filters' => [
                    'search' => $request->get('search'),
                    'role_id' => $request->get('role_id'),
                    'role_name' => $request->get('role_name'),
                    'status' => $request->get('status'),
                    'active' => $request->get('active'),
                    'type' => $request->get('type'),
                ],
                'sorting' => [
                    'sort_by' => $sortBy,
                    'sort_dir' => $sortDir,
                ],
            ],
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

        // Handle password: use provided password or generate random one
        $plainPassword = null;
        if ($request->filled('password')) {
            // Use provided password
            $plainPassword = $request->password;
            $hashedPassword = Hash::make($plainPassword);
        } else {
            // Generate a random password
            $plainPassword = Str::random(12);
            $hashedPassword = Hash::make($plainPassword);
        }

        // Create the team member (user with user_type = 'team')
        $teamMember = User::create([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'email' => $request->email,
            'password' => $hashedPassword,
            'user_type' => 'team',
            'company_id' => $user->company_id,
            'created_by' => $user->id,
            'hourly_rate' => $request->input('hourly_rate'),
            'active' => true, // Default to active
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
        $this->sendInvitationEmail($teamMember, $plainPassword, $request->filled('password'));

        $teamMember->load(['roles.permissions', 'locations:id,name,parent_id']);

        // Audit logging
        $this->auditService->logCreated($teamMember, $user, $request->ip());

        // Send notifications to admins and company owners
        try {
            $this->notificationService->createForAdminsAndOwners(
                $user->company_id,
                [
                    'type' => 'team',
                    'action' => 'invite_member',
                    'title' => 'Team Member Invited',
                    'message' => $this->notificationService->formatTeamMessage('invite_member', $teamMember->first_name . ' ' . $teamMember->last_name),
                    'data' => [
                        'teamMemberId' => $teamMember->id,
                        'teamMemberName' => $teamMember->first_name . ' ' . $teamMember->last_name,
                        'teamMemberEmail' => $teamMember->email,
                        'createdBy' => [
                            'id' => $user->id,
                            'name' => $user->first_name . ' ' . $user->last_name,
                            'userType' => $user->user_type,
                        ],
                    ],
                    'created_by' => $user->id,
                ],
                $user->id
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to send team member invitation notifications', [
                'team_member_id' => $teamMember->id,
                'error' => $e->getMessage()
            ]);
        }

        // Clear cache
        $this->cacheService->clearCompanyCache($user->company_id);

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

        // Capture original state for audit
        $originalData = $teamMember->toArray();

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

        // Track changes for audit
        $changes = [];
        $updatedData = $teamMember->toArray();
        foreach (['first_name', 'last_name', 'email', 'hourly_rate'] as $field) {
            if (isset($originalData[$field], $updatedData[$field]) && $originalData[$field] != $updatedData[$field]) {
                $changes[$field] = [$originalData[$field], $updatedData[$field]];
            }
        }

        // Audit logging
        $this->auditService->logUpdated($teamMember, $changes, $user, $request->ip());

        // Clear cache
        $this->cacheService->clearCompanyCache($user->company_id);

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

        // Audit logging before deletion
        $this->auditService->logDeleted($teamMember, $user, $request->ip());

        $teamMemberName = $teamMember->first_name . ' ' . $teamMember->last_name;
        $teamMember->delete();

        // Send notifications to admins and company owners
        try {
            $this->notificationService->createForAdminsAndOwners(
                $user->company_id,
                [
                    'type' => 'team',
                    'action' => 'remove_member',
                    'title' => 'Team Member Removed',
                    'message' => $this->notificationService->formatTeamMessage('remove_member', $teamMemberName),
                    'data' => [
                        'teamMemberName' => $teamMemberName,
                        'createdBy' => [
                            'id' => $user->id,
                            'name' => $user->first_name . ' ' . $user->last_name,
                            'userType' => $user->user_type,
                        ],
                    ],
                    'created_by' => $user->id,
                ],
                $user->id
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to send team member removal notifications', [
                'team_member_name' => $teamMemberName,
                'error' => $e->getMessage()
            ]);
        }

        // Clear cache
        $this->cacheService->clearCompanyCache($user->company_id);

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
        $this->sendInvitationEmail($teamMember, $password, false);

        // Audit logging
        $this->auditService->logInvitationResent($teamMember, $user, $request->ip());

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
        
        // Use cached statistics
        $statistics = $this->cacheService->getStatistics($user->company_id);

        return response()->json([
            'success' => true,
            'data' => $statistics
        ]);
    }

    /**
     * Team analytics for dashboard widgets
     * Route: GET /api/teams/analytics
     * Query: date_range (days, default 30)
     */
    public function analytics(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $days = (int) $request->get('date_range', 30);

        // Use cached analytics
        $analytics = $this->cacheService->getAnalytics($companyId, $days);

        return response()->json([
            'success' => true,
            'data' => $analytics,
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
     * Toggle team member active/inactive status
     */
    public function toggleStatus(Request $request, $id): JsonResponse
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

        // Toggle the active status
        $wasActive = $teamMember->active;
        $teamMember->update(['active' => !$teamMember->active]);

        // Audit logging
        $this->auditService->logUpdated($teamMember, [
            'active' => [$wasActive ? 'active' : 'inactive', $teamMember->active ? 'active' : 'inactive']
        ], $user, $request->ip());

        // Send notifications to admins and company owners
        try {
            $this->notificationService->createForAdminsAndOwners(
                $user->company_id,
                [
                    'type' => 'team',
                    'action' => $teamMember->active ? 'activate_member' : 'deactivate_member',
                    'title' => $teamMember->active ? 'Team Member Activated' : 'Team Member Deactivated',
                    'message' => $this->notificationService->formatTeamMessage(
                        $teamMember->active ? 'activate_member' : 'deactivate_member',
                        $teamMember->first_name . ' ' . $teamMember->last_name
                    ),
                    'data' => [
                        'teamMemberId' => $teamMember->id,
                        'teamMemberName' => $teamMember->first_name . ' ' . $teamMember->last_name,
                        'isActive' => $teamMember->active,
                        'createdBy' => [
                            'id' => $user->id,
                            'name' => $user->first_name . ' ' . $user->last_name,
                            'userType' => $user->user_type,
                        ],
                    ],
                    'created_by' => $user->id,
                ],
                $user->id
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to send team member status change notifications', [
                'team_member_id' => $teamMember->id,
                'error' => $e->getMessage()
            ]);
        }

        // Clear cache
        $this->cacheService->clearCompanyCache($user->company_id);

        return response()->json([
            'success' => true,
            'message' => 'Team member status updated successfully',
            'data' => [
                'id' => $teamMember->id,
                'active' => $teamMember->active,
                'status' => $teamMember->active ? 'active' : 'inactive'
            ]
        ]);
    }

    /**
     * Update team member active status (set specific status)
     */
    public function updateStatus(Request $request, $id): JsonResponse
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

        $request->validate([
            'active' => 'required|boolean'
        ]);

        $oldStatus = $teamMember->active;
        $teamMember->update(['active' => $request->active]);

        // Audit logging
        $this->auditService->logUpdated($teamMember, [
            'active' => [$oldStatus ? 'active' : 'inactive', $teamMember->active ? 'active' : 'inactive']
        ], $user, $request->ip());

        // Send notifications to admins and company owners
        try {
            $this->notificationService->createForAdminsAndOwners(
                $user->company_id,
                [
                    'type' => 'team',
                    'action' => $teamMember->active ? 'activate_member' : 'deactivate_member',
                    'title' => $teamMember->active ? 'Team Member Activated' : 'Team Member Deactivated',
                    'message' => $this->notificationService->formatTeamMessage(
                        $teamMember->active ? 'activate_member' : 'deactivate_member',
                        $teamMember->first_name . ' ' . $teamMember->last_name
                    ),
                    'data' => [
                        'teamMemberId' => $teamMember->id,
                        'teamMemberName' => $teamMember->first_name . ' ' . $teamMember->last_name,
                        'isActive' => $teamMember->active,
                        'createdBy' => [
                            'id' => $user->id,
                            'name' => $user->first_name . ' ' . $user->last_name,
                            'userType' => $user->user_type,
                        ],
                    ],
                    'created_by' => $user->id,
                ],
                $user->id
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to send team member status change notifications', [
                'team_member_id' => $teamMember->id,
                'error' => $e->getMessage()
            ]);
        }

        // Clear cache
        $this->cacheService->clearCompanyCache($user->company_id);

        return response()->json([
            'success' => true,
            'message' => 'Team member status updated successfully',
            'data' => [
                'id' => $teamMember->id,
                'active' => $teamMember->active,
                'status' => $teamMember->active ? 'active' : 'inactive'
            ]
        ]);
    }

    /**
     * Send invitation email
     */
    private function sendInvitationEmail($user, $password, $isCustomPassword = false)
    {
        try {
            // Send the invitation email
            Mail::to($user->email)->send(new TeamInvitationMail($user, $password, $isCustomPassword));
            
            // Log successful email sending
            \Log::info('Team member invitation email sent', [
                'user_id' => $user->id,
                'email' => $user->email,
                'company' => $user->company->name,
                'custom_password' => $isCustomPassword,
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