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
        
        return response()->json(['success' => true, 'data' => new ScheduleMaintenanceAssignedResource($item)], 201);
    }

    public function show(ScheduleMaintenanceAssigned $scheduleMaintenanceAssigned)
    {
        return response()->json(['success' => true, 'data' => new ScheduleMaintenanceAssignedResource($scheduleMaintenanceAssigned)]);
    }

    public function update(UpdateScheduleMaintenanceAssignedRequest $request, ScheduleMaintenanceAssigned $scheduleMaintenanceAssigned)
    {
        $scheduleMaintenanceAssigned->update($request->validated());
        return response()->json(['success' => true, 'data' => new ScheduleMaintenanceAssignedResource($scheduleMaintenanceAssigned)]);
    }

    public function destroy(ScheduleMaintenanceAssigned $scheduleMaintenanceAssigned)
    {
        $scheduleMaintenanceAssigned->delete();
        return response()->json(['success' => true]);
    }
}


