<?php

namespace App\Http\Controllers\Api\Maintenance;

use App\Http\Controllers\Controller;
use App\Http\Requests\Maintenance\StoreMaintenancePlanRequest;
use App\Http\Requests\Maintenance\UpdateMaintenancePlanRequest;
use App\Http\Resources\MaintenancePlanResource;
use App\Models\MaintenancePlan;
use App\Models\MaintenancePlanChecklist;
use App\Models\MaintenancePlanPart;
use App\Models\Asset;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MaintenancePlansController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    public function index(Request $request)
    {
        $perPage = min((int)$request->get('per_page', 15), 100);
        $query = MaintenancePlan::query()
            ->where('company_id', auth()->user()->company_id)
            ->with('priority')
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
            $companyId = auth()->user()->company_id;
            $response['meta'] = [
                'active_plans_count' => MaintenancePlan::where('company_id', $companyId)->active()->count(),
                'critical_plans_count' => MaintenancePlan::where('company_id', $companyId)
                    ->whereHas('priority', function ($q) {
                        $q->where('slug', 'critical');
                    })
                    ->count(),
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

        // Send notifications to admins and company owners
        $creator = auth()->user();
        try {
            $this->notificationService->createForAdminsAndOwners(
                $creator->company_id,
                [
                    'type' => 'maintenance',
                    'action' => 'create_plan',
                    'title' => 'Maintenance Plan Created',
                    'message' => $this->notificationService->formatMaintenanceMessage('create_plan', $plan->name),
                    'data' => [
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
            \Log::warning('Failed to send maintenance plan creation notifications', [
                'plan_id' => $plan->id,
                'error' => $e->getMessage()
            ]);
        }

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

        // Send notifications to admins and company owners
        $creator = auth()->user();
        try {
            $this->notificationService->createForAdminsAndOwners(
                $creator->company_id,
                [
                    'type' => 'maintenance',
                    'action' => 'edit_plan',
                    'title' => 'Maintenance Plan Updated',
                    'message' => $this->notificationService->formatMaintenanceMessage('edit_plan', $maintenancePlan->name),
                    'data' => [
                        'planId' => $maintenancePlan->id,
                        'planName' => $maintenancePlan->name,
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
            \Log::warning('Failed to send maintenance plan update notifications', [
                'plan_id' => $maintenancePlan->id,
                'error' => $e->getMessage()
            ]);
        }

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
        
        $planName = $maintenancePlan->name;
        $companyId = $maintenancePlan->company_id;
        $creator = auth()->user();
        $maintenancePlan->delete();

        // Send notifications to admins and company owners
        try {
            $this->notificationService->createForAdminsAndOwners(
                $companyId,
                [
                    'type' => 'maintenance',
                    'action' => 'delete_plan',
                    'title' => 'Maintenance Plan Deleted',
                    'message' => $this->notificationService->formatMaintenanceMessage('delete_plan', $planName),
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
            \Log::warning('Failed to send maintenance plan deletion notifications', [
                'plan_name' => $planName,
                'error' => $e->getMessage()
            ]);
        }

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

    // Plan Parts Management
    public function getParts(MaintenancePlan $maintenancePlan)
    {
        if ($maintenancePlan->company_id !== auth()->user()->company_id) {
            abort(403, 'Unauthorized access to maintenance plan.');
        }

        $parts = $maintenancePlan->parts()->with('part')->get();
        return response()->json([
            'success' => true,
            'data' => $parts,
        ]);
    }

    public function addParts(Request $request, MaintenancePlan $maintenancePlan)
    {
        if ($maintenancePlan->company_id !== auth()->user()->company_id) {
            abort(403, 'Unauthorized access to maintenance plan.');
        }

        $validated = $request->validate([
            'parts' => 'required|array|min:1',
            'parts.*.part_id' => 'required|exists:inventory_parts,id',
            'parts.*.default_qty' => 'nullable|numeric|min:0',
            'parts.*.is_required' => 'nullable|boolean',
        ]);

        $created = [];
        foreach ($validated['parts'] as $partData) {
            $created[] = MaintenancePlanPart::create([
                'maintenance_plan_id' => $maintenancePlan->id,
                'part_id' => $partData['part_id'],
                'default_qty' => $partData['default_qty'] ?? null,
                'is_required' => $partData['is_required'] ?? true,
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => MaintenancePlanPart::whereIn('id', collect($created)->pluck('id'))->with('part')->get(),
        ], 201);
    }

    public function updatePart(Request $request, MaintenancePlan $maintenancePlan, MaintenancePlanPart $part)
    {
        if ($maintenancePlan->company_id !== auth()->user()->company_id) {
            abort(403, 'Unauthorized access to maintenance plan.');
        }

        if ($part->maintenance_plan_id !== $maintenancePlan->id) {
            abort(404, 'Part not found in this plan.');
        }

        $validated = $request->validate([
            'default_qty' => 'nullable|numeric|min:0',
            'is_required' => 'nullable|boolean',
        ]);

        $part->update($validated);

        return response()->json([
            'success' => true,
            'data' => $part->load('part'),
        ]);
    }

    public function removePart(MaintenancePlan $maintenancePlan, MaintenancePlanPart $part)
    {
        if ($maintenancePlan->company_id !== auth()->user()->company_id) {
            abort(403, 'Unauthorized access to maintenance plan.');
        }

        if ($part->maintenance_plan_id !== $maintenancePlan->id) {
            abort(404, 'Part not found in this plan.');
        }

        $part->delete();

        return response()->json([
            'success' => true,
        ]);
    }

    public function getAssetParts(Request $request)
    {
        // Handle array parameter (asset_ids[] or asset_ids)
        $assetIds = $request->input('asset_ids', []);
        if (!is_array($assetIds)) {
            $assetIds = [$assetIds];
        }

        $validated = validator([
            'asset_ids' => $assetIds
        ], [
            'asset_ids' => 'required|array|min:1',
            'asset_ids.*' => 'required|exists:assets,id',
        ])->validate();

        $companyId = auth()->user()->company_id;
        $assetIds = $validated['asset_ids'];

        // Get all unique parts linked to these assets
        $parts = DB::table('asset_inventory_part')
            ->join('inventory_parts', 'asset_inventory_part.inventory_part_id', '=', 'inventory_parts.id')
            ->whereIn('asset_inventory_part.asset_id', $assetIds)
            ->where('inventory_parts.company_id', $companyId)
            ->where('inventory_parts.is_archived', false)
            ->select('inventory_parts.*')
            ->distinct()
            ->get()
            ->map(function ($part) {
                return (array) $part;
            });

        return response()->json([
            'success' => true,
            'data' => $parts,
        ]);
    }
}


