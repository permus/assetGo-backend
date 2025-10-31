<?php

namespace App\Http\Controllers\Api\Maintenance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\StoreScheduleMaintenanceRequest;
use App\Http\Requests\Maintenance\UpdateScheduleMaintenanceRequest;
use App\Http\Resources\ScheduleMaintenanceResource;
use App\Models\MaintenancePlan;
use App\Models\ScheduleMaintenance;
use App\Services\Maintenance\DueDateService;
use App\Services\NotificationService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ScheduleMaintenanceController extends Controller
{
    protected $notificationService;

    public function __construct(private DueDateService $dueDateService, NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function index(Request $request)
    {
        $perPage = min((int)$request->get('per_page', 15), 100);
        $query = ScheduleMaintenance::query();

        if ($planId = $request->get('plan_id')) {
            $query->where('maintenance_plan_id', $planId);
        }
        if ($status = $request->get('status')) {
            $query->where('status', $status);
        }
        if ($dueFrom = $request->get('due_from')) {
            $query->whereDate('due_date', '>=', $dueFrom);
        }
        if ($dueTo = $request->get('due_to')) {
            $query->whereDate('due_date', '<=', $dueTo);
        }
        if ($planType = $request->get('plan_type')) {
            // Validate plan_type parameter
            $validPlanTypes = ['preventive', 'predictive', 'condition_based'];
            if (in_array($planType, $validPlanTypes)) {
                $query->whereHas('plan', function ($q) use ($planType) {
                    $q->where('plan_type', $planType);
                });
            }
        }

        $query->orderBy('due_date')->orderBy('id');
        $items = $query->with('plan.priority')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => ScheduleMaintenanceResource::collection($items->items()),
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

    public function store(StoreScheduleMaintenanceRequest $request)
    {
        $validated = $request->validated();
        $plan = MaintenancePlan::findOrFail($validated['maintenance_plan_id']);

        $start = isset($validated['start_date']) ? Carbon::parse($validated['start_date']) : null;
        $dueDate = $this->dueDateService->calculateDueDate($plan, $start);

        $schedule = ScheduleMaintenance::create(array_merge($validated, [
            'due_date' => $dueDate?->toDateString(),
        ]));

        // Send notifications to admins and company owners
        $creator = auth()->user();
        try {
            $this->notificationService->createForAdminsAndOwners(
                $creator->company_id,
                [
                    'type' => 'maintenance',
                    'action' => 'create_schedule',
                    'title' => 'Maintenance Schedule Created',
                    'message' => $this->notificationService->formatMaintenanceMessage('create_schedule', $plan->name),
                    'data' => [
                        'scheduleId' => $schedule->id,
                        'planId' => $plan->id,
                        'planName' => $plan->name,
                        'createdBy' => [
                            'id' => $creator->id,
                            'name' => $creator->first_name . ' ' . $creator->last_name,
                            'userType' => $creator->user_type,
                        ],
                    ],
                    'created_by' => $creator->id,
                ],
                $creator->id
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to send maintenance schedule creation notifications', [
                'schedule_id' => $schedule->id,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => new ScheduleMaintenanceResource($schedule),
        ], 201);
    }

    public function show(ScheduleMaintenance $scheduleMaintenance)
    {
        $scheduleMaintenance->load('assignees');
        return response()->json(['success' => true, 'data' => new ScheduleMaintenanceResource($scheduleMaintenance)]);
    }

    public function update(UpdateScheduleMaintenanceRequest $request, ScheduleMaintenance $scheduleMaintenance)
    {
        $validated = $request->validated();

        // Recompute due_date if plan frequency or start_date potentially changed
        $plan = isset($validated['maintenance_plan_id'])
            ? MaintenancePlan::findOrFail($validated['maintenance_plan_id'])
            : $scheduleMaintenance->plan;

        $startDate = $validated['start_date'] ?? $scheduleMaintenance->start_date?->toDateString();
        $start = $startDate ? Carbon::parse($startDate) : null;
        $dueDate = $this->dueDateService->calculateDueDate($plan, $start);

        $scheduleMaintenance->update(array_merge($validated, [
            'due_date' => $dueDate?->toDateString(),
        ]));

        // Send notifications to admins and company owners
        $creator = auth()->user();
        try {
            $this->notificationService->createForAdminsAndOwners(
                $creator->company_id,
                [
                    'type' => 'maintenance',
                    'action' => 'edit_schedule',
                    'title' => 'Maintenance Schedule Updated',
                    'message' => $this->notificationService->formatMaintenanceMessage('edit_schedule', $plan->name),
                    'data' => [
                        'scheduleId' => $scheduleMaintenance->id,
                        'planId' => $plan->id,
                        'planName' => $plan->name,
                        'createdBy' => [
                            'id' => $creator->id,
                            'name' => $creator->first_name . ' ' . $creator->last_name,
                            'userType' => $creator->user_type,
                        ],
                    ],
                    'created_by' => $creator->id,
                ],
                $creator->id
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to send maintenance schedule update notifications', [
                'schedule_id' => $scheduleMaintenance->id,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json(['success' => true, 'data' => new ScheduleMaintenanceResource($scheduleMaintenance)]);
    }

    public function destroy(ScheduleMaintenance $scheduleMaintenance)
    {
        $plan = $scheduleMaintenance->plan;
        $planName = $plan ? $plan->name : 'Unknown Plan';
        $companyId = $scheduleMaintenance->company_id ?? auth()->user()->company_id;
        $creator = auth()->user();
        
        $scheduleMaintenance->delete();

        // Send notifications to admins and company owners
        try {
            $this->notificationService->createForAdminsAndOwners(
                $companyId,
                [
                    'type' => 'maintenance',
                    'action' => 'delete_schedule',
                    'title' => 'Maintenance Schedule Deleted',
                    'message' => $this->notificationService->formatMaintenanceMessage('delete_schedule', $planName),
                    'data' => [
                        'planName' => $planName,
                        'createdBy' => [
                            'id' => $creator->id,
                            'name' => $creator->first_name . ' ' . $creator->last_name,
                            'userType' => $creator->user_type,
                        ],
                    ],
                    'created_by' => $creator->id,
                ],
                $creator->id
            );
        } catch (\Exception $e) {
            \Log::warning('Failed to send maintenance schedule deletion notifications', [
                'plan_name' => $planName,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json(['success' => true]);
    }
}


