<?php

namespace App\Http\Controllers\Api\Maintenance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\StoreMaintenancePlanChecklistRequest;
use App\Http\Requests\Maintenance\UpdateMaintenancePlanChecklistRequest;
use App\Http\Resources\MaintenancePlanChecklistResource;
use App\Models\MaintenancePlanChecklist;
use Illuminate\Http\Request;

class MaintenancePlansChecklistsController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int)$request->get('per_page', 15), 100);
        $query = MaintenancePlanChecklist::query();
        if ($planId = $request->get('maintenance_plan_id')) {
            $query->where('maintenance_plan_id', $planId);
        }
        $query->orderBy('order')->orderBy('id');
        $items = $query->paginate($perPage);
        return response()->json([
            'success' => true,
            'data' => MaintenancePlanChecklistResource::collection($items->items()),
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

    public function store(StoreMaintenancePlanChecklistRequest $request)
    {
        $item = MaintenancePlanChecklist::create($request->validated());
        return response()->json(['success' => true, 'data' => new MaintenancePlanChecklistResource($item)], 201);
    }

    public function show(MaintenancePlanChecklist $maintenancePlanChecklist)
    {
        return response()->json(['success' => true, 'data' => new MaintenancePlanChecklistResource($maintenancePlanChecklist)]);
    }

    public function update(UpdateMaintenancePlanChecklistRequest $request, MaintenancePlanChecklist $maintenancePlanChecklist)
    {
        $maintenancePlanChecklist->update($request->validated());
        return response()->json(['success' => true, 'data' => new MaintenancePlanChecklistResource($maintenancePlanChecklist)]);
    }

    public function destroy(MaintenancePlanChecklist $maintenancePlanChecklist)
    {
        $maintenancePlanChecklist->delete();
        return response()->json(['success' => true]);
    }
}


