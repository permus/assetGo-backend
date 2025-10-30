<?php

namespace App\Http\Controllers\Api\Maintenance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\StoreScheduleMaintenanceRequest;
use App\Http\Requests\Maintenance\UpdateScheduleMaintenanceRequest;
use App\Http\Resources\ScheduleMaintenanceResource;
use App\Models\MaintenancePlan;
use App\Models\ScheduleMaintenance;
use App\Services\Maintenance\DueDateService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ScheduleMaintenanceController extends Controller
{
    public function __construct(private DueDateService $dueDateService)
    {
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

        return response()->json(['success' => true, 'data' => new ScheduleMaintenanceResource($scheduleMaintenance)]);
    }

    public function destroy(ScheduleMaintenance $scheduleMaintenance)
    {
        $scheduleMaintenance->delete();
        return response()->json(['success' => true]);
    }
}


