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
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
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
        $this->sendInvitationEmail($teamMember, $password, false);

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
        $activeTeamMembers = $company->users()->where('user_type', 'team')->whereNotNull('email_verified_at')->count();
        $pendingTeamMembers = $company->users()->where('user_type', 'team')->whereNull('email_verified_at')->count();

        // Aggregate work order assignment counts for this company
        $assignmentAggregates = \App\Models\WorkOrderAssignment::join('work_orders', 'work_order_assignments.work_order_id', '=', 'work_orders.id')
            ->where('work_orders.company_id', $user->company_id)
            ->selectRaw('COUNT(*) as total_count')
            ->selectRaw("SUM(CASE WHEN work_order_assignments.status IN ('assigned','accepted') THEN 1 ELSE 0 END) as active_count")
            ->selectRaw("SUM(CASE WHEN work_order_assignments.status = 'completed' THEN 1 ELSE 0 END) as completed_count")
            ->first();

        $totalAssignments = (int) ($assignmentAggregates->total_count ?? 0);
        $activeAssignments = (int) ($assignmentAggregates->active_count ?? 0);
        $completedAssignments = (int) ($assignmentAggregates->completed_count ?? 0);
        $completionRate = $totalAssignments > 0 ? round(($completedAssignments / $totalAssignments) * 100, 2) : 0;

        return response()->json([
            'success' => true,
            'data' => [
                'total_team_members' => $totalTeamMembers,
                'active_team_members' => $activeTeamMembers,
                'pending_team_members' => $pendingTeamMembers,
                'assigned_work_orders_total_count' => $totalAssignments,
                'assigned_work_orders_active_count' => $activeAssignments,
                'assigned_work_orders_completed_count' => $completedAssignments,
                'completion_rate' => $completionRate,
            ]
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
        $end = now();
        $start = (clone $end)->subDays(max(1, $days));

        // Productivity: completed assignments / total assignments in range
        $totalAssigned = WorkOrderAssignment::join('work_orders', 'work_order_assignments.work_order_id', '=', 'work_orders.id')
            ->where('work_orders.company_id', $companyId)
            ->whereBetween('work_order_assignments.created_at', [$start, $end])
            ->count();

        $completedAssigned = WorkOrderAssignment::join('work_orders', 'work_order_assignments.work_order_id', '=', 'work_orders.id')
            ->where('work_orders.company_id', $companyId)
            ->where('work_order_assignments.status', 'completed')
            ->whereBetween('work_order_assignments.updated_at', [$start, $end])
            ->count();

        $productivity = $totalAssigned > 0 ? round(($completedAssigned / $totalAssigned) * 100, 2) : 0.0;

        // Avg completion time (days) for work orders completed in range
        $avgCompletionDays = (float) (WorkOrder::where('company_id', $companyId)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$start, $end])
            ->selectRaw('AVG(TIMESTAMPDIFF(DAY, created_at, completed_at)) as avg_days')
            ->value('avg_days') ?? 0);
        $avgCompletionDays = round($avgCompletionDays, 1);

        // On-time rate: completed by due_date among completed in range
        $completedInRange = WorkOrder::where('company_id', $companyId)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$start, $end]);
        $completedCount = (int) $completedInRange->count();
        $onTimeCount = (int) WorkOrder::where('company_id', $companyId)
            ->whereNotNull('completed_at')
            ->whereNotNull('due_date')
            ->whereBetween('completed_at', [$start, $end])
            ->whereColumn('completed_at', '<=', 'due_date')
            ->count();
        $onTimeRate = $completedCount > 0 ? round(($onTimeCount / $completedCount) * 100, 2) : 0.0;

        // Labor cost from time logs in range
        $laborCost = (float) WorkOrderTimeLog::where('company_id', $companyId)
            ->whereBetween('start_time', [$start, $end])
            ->selectRaw('COALESCE(SUM(COALESCE(total_cost, (duration_minutes/60)*hourly_rate)), 0) as total')
            ->value('total');

        // Top performers by completed assignments
        $topPerformers = WorkOrderAssignment::join('work_orders', 'work_order_assignments.work_order_id', '=', 'work_orders.id')
            ->join('users', 'users.id', '=', 'work_order_assignments.user_id')
            ->where('work_orders.company_id', $companyId)
            ->where('work_order_assignments.status', 'completed')
            ->whereBetween('work_order_assignments.updated_at', [$start, $end])
            ->groupBy('work_order_assignments.user_id', 'users.first_name', 'users.last_name')
            ->select([
                'work_order_assignments.user_id as user_id',
                DB::raw('users.first_name as first_name'),
                DB::raw('users.last_name as last_name'),
                DB::raw('COUNT(*) as completed_count'),
            ])
            ->orderByDesc('completed_count')
            ->limit(5)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'date_range_days' => $days,
                'productivity_rate_percent' => $productivity,
                'on_time_rate_percent' => $onTimeRate,
                'avg_completion_days' => $avgCompletionDays,
                'labor_cost_total' => round($laborCost, 2),
                'top_performers' => $topPerformers,
            ],
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