<?php

namespace App\Http\Controllers\Api\Maintenance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\StoreMaintenancePlanRequest;
use App\Http\Requests\Maintenance\UpdateMaintenancePlanRequest;
use App\Http\Resources\MaintenancePlanResource;
use App\Models\MaintenancePlan;
use App\Models\MaintenancePlanChecklist;
use App\Models\Asset;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenancePlansController extends Controller
{
    public function index(Request $request)
    {
        $perPage = min((int)$request->get('per_page', 15), 100);
        $query = MaintenancePlan::query()
            ->where('company_id', auth()->user()->company_id)
            ->withCount(['schedules as scheduled_count']);

        if ($name = $request->get('name')) {
            $query->where('name', 'like', "%$name%");
        }
        if ($planType = $request->get('plan_type')) {
            $query->where('plan_type', $planType);
        }
        if (!is_null($request->get('is_active'))) {
            $query->where('is_active', filter_var($request->get('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $query->orderBy('sort')->orderBy('id');
        $plans = $query->paginate($perPage);

        $response = [
            'success' => true,
            'data' => [
                'plans' => MaintenancePlanResource::collection($plans->items()),
            ],
            'pagination' => [
                'current_page' => $plans->currentPage(),
                'last_page' => $plans->lastPage(),
                'per_page' => $plans->perPage(),
                'total' => $plans->total(),
                'from' => $plans->firstItem(),
                'to' => $plans->lastItem(),
            ],
        ];

        if ($request->get('include') === 'meta') {
            $response['meta'] = [
                'active_plans_count' => MaintenancePlan::active()->count(),
            ];
        }

        return response()->json($response);
    }

    public function store(StoreMaintenancePlanRequest $request)
    {
        $validated = $request->validated();
        $checklistItems = $validated['checklist_items'] ?? [];

        $plan = DB::transaction(function () use ($validated, $checklistItems) {
            unset($validated['checklist_items']);
            // Automatically set company_id from authenticated user
            $validated['company_id'] = auth()->user()->company_id;
            $plan = MaintenancePlan::create($validated);

            $normalized = [];
            foreach (array_values($checklistItems) as $index => $item) {
                $normalized[] = array_merge([
                    'order' => $item['order'] ?? $index,
                ], $item);
            }
            $plan->checklists()->createMany($normalized);
            return $plan->load('checklists');
        });

        return response()->json([
            'success' => true,
            'data' => new MaintenancePlanResource($plan),
        ], 201);
    }

    public function show(MaintenancePlan $maintenancePlan)
    {
        // Ensure user can only access plans from their company
        if ($maintenancePlan->company_id !== auth()->user()->company_id) {
            abort(403, 'Unauthorized access to maintenance plan.');
        }
        
        $maintenancePlan->load(['checklists', 'priority', 'category']);

        // Attach assets data based on asset_ids
        $assetIds = is_array($maintenancePlan->asset_ids) ? $maintenancePlan->asset_ids : [];
        $assetsData = [];
        if (!empty($assetIds)) {
            $assets = Asset::query()
                ->forCompany(auth()->user()->company_id)
                ->whereIn('id', $assetIds)
                ->get(['id', 'name', 'serial_number', 'description']);

            $assetsData = $assets->map(function ($asset) {
                return [
                    'id' => $asset->id,
                    'name' => $asset->name,
                    'serial_number' => $asset->serial_number,
                    'description' => $asset->description,
                ];
            })->values();
        }

        $maintenancePlan->setAttribute('assets_data', $assetsData);
        return response()->json([
            'success' => true,
            'data' => [
                'plan' => new MaintenancePlanResource($maintenancePlan),
            ],
        ]);
    }

    public function update(UpdateMaintenancePlanRequest $request, MaintenancePlan $maintenancePlan)
    {
        // Ensure user can only update plans from their company
        if ($maintenancePlan->company_id !== auth()->user()->company_id) {
            abort(403, 'Unauthorized access to maintenance plan.');
        }
        
        $validated = $request->validated();
        $incomingChecklist = array_key_exists('checklist_items', $validated) ? ($validated['checklist_items'] ?? []) : null;

        DB::transaction(function () use ($maintenancePlan, $validated, $incomingChecklist) {
            unset($validated['checklist_items']);
            $maintenancePlan->update($validated);

            if (is_array($incomingChecklist)) {
                // Replace strategy: soft-delete old, add new
                MaintenancePlanChecklist::where('maintenance_plan_id', $maintenancePlan->id)->delete();

                $normalized = [];
                foreach (array_values($incomingChecklist) as $index => $item) {
                    $normalized[] = array_merge([
                        'order' => $item['order'] ?? $index,
                    ], $item);
                }
                if (!empty($normalized)) {
                    $maintenancePlan->checklists()->createMany($normalized);
                }
            }
        });

        // If plan is activated ensure at least one checklist exists (in DB or payload just handled)
        $isActive = $maintenancePlan->is_active;
        if (array_key_exists('is_active', $validated)) {
            $isActive = (bool)$validated['is_active'];
        }

        if ($isActive && $maintenancePlan->checklists()->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Active plans must have at least one checklist item.',
                'errors' => [
                    'checklist_items' => ['At least one checklist item is required when activating a plan.']
                ]
            ], 422);
        }

        $maintenancePlan->load('checklists');
        return response()->json([
            'success' => true,
            'data' => new MaintenancePlanResource($maintenancePlan),
        ]);
    }

    public function destroy(MaintenancePlan $maintenancePlan)
    {
        // Ensure user can only delete plans from their company
        if ($maintenancePlan->company_id !== auth()->user()->company_id) {
            abort(403, 'Unauthorized access to maintenance plan.');
        }
        
        $maintenancePlan->delete();
        return response()->json(['success' => true]);
    }

    public function toggleActive(MaintenancePlan $maintenancePlan)
    {
        // Ensure user can only toggle plans from their company
        if ($maintenancePlan->company_id !== auth()->user()->company_id) {
            abort(403, 'Unauthorized access to maintenance plan.');
        }
        
        $maintenancePlan->is_active = !$maintenancePlan->is_active;
        // Enforce checklist rule if toggling to active
        if ($maintenancePlan->is_active && $maintenancePlan->checklists()->count() === 0) {
            return response()->json([
                'success' => false,
                'message' => 'Active plans must have at least one checklist item.',
            ], 422);
        }
        $maintenancePlan->save();
        return response()->json([
            'success' => true,
            'data' => new MaintenancePlanResource($maintenancePlan->fresh('checklists')),
        ]);
    }
}


