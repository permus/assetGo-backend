<?php

namespace App\Http\Controllers\Api\Maintenance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\StoreScheduleMaintenanceAssignedRequest;
use App\Http\Requests\Maintenance\UpdateScheduleMaintenanceAssignedRequest;
use App\Http\Resources\ScheduleMaintenanceAssignedResource;
use App\Models\ScheduleMaintenanceAssigned;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class ScheduleMaintenanceAssignedController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    public function index(Request $request)
    {
        $perPage = min((int)$request->get('per_page', 15), 100);
        $query = ScheduleMaintenanceAssigned::query();
        if ($scheduleId = $request->get('schedule_maintenance_id')) {
            $query->where('schedule_maintenance_id', $scheduleId);
        }
        $items = $query->paginate($perPage);
        return response()->json([
            'success' => true,
            'data' => ScheduleMaintenanceAssignedResource::collection($items->items()),
            'pagination' => [
                'current_page' => $items->currentPage(),
                'last_page' => $items->lastPage(),
                'per_page' => $items->perPage(),
                'total' => $items->total(),
                'from' => $items->firstItem(),
                'to' => $items->lastItem(),
            ],
        ]);
    }

    public function store(StoreScheduleMaintenanceAssignedRequest $request)
    {
        $item = ScheduleMaintenanceAssigned::create($request->validated());
        
        // Send notification to the assigned team member
        $creator = auth()->user();
        $schedule = $item->schedule()->with('plan')->first();
        
        if ($schedule && $schedule->plan) {
            try {
                $this->notificationService->createForUsers(
                    [$request->team_id],
                    [
                        'company_id' => $creator->company_id,
                        'type' => 'maintenance',
                        'action' => 'assigned',
                        'title' => 'Maintenance Schedule Assigned to You',
                        'message' => "You have been assigned to maintenance schedule for '{$schedule->plan->name}'",
                        'data' => [
                            'scheduleId' => $schedule->id,
                            'planId' => $schedule->plan->id,
                            'planName' => $schedule->plan->name,
                            'assignedBy' => [
                                'id' => $creator->id,
                                'name' => $creator->first_name . ' ' . $creator->last_name,
                            ],
                        ],
                        'created_by' => $creator->id,
                    ]
                );
            } catch (\Exception $e) {
                \Log::warning('Failed to send maintenance assignment notification', [
                    'schedule_id' => $schedule->id,
                    'team_id' => $request->team_id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Load relationships before returning resource to prevent MissingValue error
        $item->load('schedule.plan.checklists', 'responses', 'user');
        
        return response()->json(['success' => true, 'data' => new ScheduleMaintenanceAssignedResource($item)], 201);
    }

    public function show(ScheduleMaintenanceAssigned $scheduleMaintenanceAssigned)
    {
        // Load relationships before returning resource
        $scheduleMaintenanceAssigned->load('schedule.plan.checklists', 'responses', 'user');
        return response()->json(['success' => true, 'data' => new ScheduleMaintenanceAssignedResource($scheduleMaintenanceAssigned)]);
    }

    public function update(UpdateScheduleMaintenanceAssignedRequest $request, ScheduleMaintenanceAssigned $scheduleMaintenanceAssigned)
    {
        $scheduleMaintenanceAssigned->update($request->validated());
        // Load relationships before returning resource
        $scheduleMaintenanceAssigned->load('schedule.plan.checklists', 'responses', 'user');
        return response()->json(['success' => true, 'data' => new ScheduleMaintenanceAssignedResource($scheduleMaintenanceAssigned)]);
    }

    public function destroy(ScheduleMaintenanceAssigned $scheduleMaintenanceAssigned)
    {
        $scheduleMaintenanceAssigned->delete();
        return response()->json(['success' => true]);
    }

    /**
     * Get current user's assigned maintenance tasks
     */
    public function myAssignments(Request $request)
    {
        $userId = $request->user()->id;
        
        $assignments = ScheduleMaintenanceAssigned::where('team_id', $userId)
            ->with([
                'schedule.plan.checklists' => function ($query) {
                    $query->orderBy('order')->orderBy('id');
                },
                'responses',
                'user'
            ])
            ->get();

        return response()->json([
            'success' => true,
            'data' => ScheduleMaintenanceAssignedResource::collection($assignments)
        ]);
    }

    /**
     * Get users who can be assigned to maintenance schedules
     */
    public function getAssignableUsers(Request $request)
    {
        $companyId = $request->user()->company_id;
        $scheduleId = $request->get('schedule_id');
        
        // Get all users from the same company
        $query = \App\Models\User::where('company_id', $companyId)
            ->where('active', true)
            ->select('id', 'first_name', 'last_name', 'email', 'user_type');
        
        // If schedule_id provided, exclude users already assigned to this schedule
        if ($scheduleId) {
            $assignedUserIds = ScheduleMaintenanceAssigned::where('schedule_maintenance_id', $scheduleId)
                ->pluck('team_id')
                ->toArray();
            
            if (!empty($assignedUserIds)) {
                $query->whereNotIn('id', $assignedUserIds);
            }
        }
        
        $users = $query->orderBy('first_name')->orderBy('last_name')->get();
        
        return response()->json([
            'success' => true,
            'data' => $users
        ]);
    }

    /**
     * Get all assignments for a specific schedule
     */
    public function getScheduleAssignments(Request $request, $scheduleId)
    {
        $companyId = $request->user()->company_id;
        
        // Verify schedule belongs to user's company
        $schedule = \App\Models\ScheduleMaintenance::find($scheduleId);
        if (!$schedule) {
            return response()->json([
                'success' => false,
                'message' => 'Schedule not found'
            ], 404);
        }
        
        $assignments = ScheduleMaintenanceAssigned::where('schedule_maintenance_id', $scheduleId)
            ->with(['user', 'responses'])
            ->get();
        
        // Format the response with completion status
        $formattedAssignments = $assignments->map(function ($assignment) use ($schedule) {
            $totalItems = $schedule->plan->checklists->count();
            $completedItems = $assignment->responses->count();
            $completionPercentage = $totalItems > 0 ? round(($completedItems / $totalItems) * 100) : 0;
            
            return [
                'id' => $assignment->id,
                'schedule_maintenance_id' => $assignment->schedule_maintenance_id,
                'team_id' => $assignment->team_id,
                'user' => [
                    'id' => $assignment->user->id,
                    'first_name' => $assignment->user->first_name,
                    'last_name' => $assignment->user->last_name,
                    'email' => $assignment->user->email,
                    'user_type' => $assignment->user->user_type,
                ],
                'completion_percentage' => $completionPercentage,
                'completed_items' => $completedItems,
                'total_items' => $totalItems,
                'is_completed' => $completionPercentage === 100,
                'created_at' => $assignment->created_at,
                'updated_at' => $assignment->updated_at,
            ];
        });
        
        return response()->json([
            'success' => true,
            'data' => $formattedAssignments
        ]);
    }
}


