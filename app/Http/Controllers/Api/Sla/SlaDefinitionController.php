<?php

namespace App\Http\Controllers\Api\Sla;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sla\StoreSlaDefinitionRequest;
use App\Http\Requests\Sla\UpdateSlaDefinitionRequest;
use App\Http\Resources\SlaDefinitionResource;
use App\Models\SlaDefinition;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class SlaDefinitionController extends Controller
{
    /**
     * Display a listing of SLA definitions.
     */
    public function index(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $query = SlaDefinition::where('company_id', $companyId)
            ->with('creator');

        // Filters
        if ($appliesTo = $request->get('applies_to')) {
            if ($appliesTo === 'work_orders') {
                $query->forWorkOrders();
            } elseif ($appliesTo === 'maintenance') {
                $query->forMaintenance();
            }
        }

        if (!is_null($request->get('is_active'))) {
            $isActive = filter_var($request->get('is_active'), FILTER_VALIDATE_BOOLEAN);
            if ($isActive) {
                $query->active();
            } else {
                $query->where('is_active', false);
            }
        }

        if ($priorityLevel = $request->get('priority_level')) {
            $query->where('priority_level', $priorityLevel);
        }

        if ($search = $request->get('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('category', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        // Pagination
        $perPage = min((int)$request->get('per_page', 15), 100);
        $definitions = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => [
                'definitions' => SlaDefinitionResource::collection($definitions->items()),
            ],
            'pagination' => [
                'current_page' => $definitions->currentPage(),
                'last_page' => $definitions->lastPage(),
                'per_page' => $definitions->perPage(),
                'total' => $definitions->total(),
                'from' => $definitions->firstItem(),
                'to' => $definitions->lastItem(),
            ],
        ]);
    }

    /**
     * Store a newly created SLA definition.
     */
    public function store(StoreSlaDefinitionRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $validated['company_id'] = $request->user()->company_id;
        $validated['created_by'] = $request->user()->id;
        $validated['is_active'] = $validated['is_active'] ?? true;

        $definition = SlaDefinition::create($validated);
        $definition->load('creator');

        return response()->json([
            'success' => true,
            'data' => new SlaDefinitionResource($definition),
        ], 201);
    }

    /**
     * Display the specified SLA definition.
     */
    public function show(SlaDefinition $slaDefinition): JsonResponse
    {
        // Ensure user can only access definitions from their company
        if ($slaDefinition->company_id !== auth()->user()->company_id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized access to SLA definition.',
            ], 403);
        }

        $slaDefinition->load('creator');

        return response()->json([
            'success' => true,
            'data' => new SlaDefinitionResource($slaDefinition),
        ]);
    }

    /**
     * Update the specified SLA definition.
     */
    public function update(UpdateSlaDefinitionRequest $request, SlaDefinition $slaDefinition): JsonResponse
    {
        // Ensure user can only update definitions from their company
        if ($slaDefinition->company_id !== auth()->user()->company_id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized access to SLA definition.',
            ], 403);
        }

        $validated = $request->validated();
        $slaDefinition->update($validated);
        $slaDefinition->load('creator');

        return response()->json([
            'success' => true,
            'data' => new SlaDefinitionResource($slaDefinition),
        ]);
    }

    /**
     * Remove the specified SLA definition.
     */
    public function destroy(SlaDefinition $slaDefinition): JsonResponse
    {
        // Ensure user can only delete definitions from their company
        if ($slaDefinition->company_id !== auth()->user()->company_id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized access to SLA definition.',
            ], 403);
        }

        $slaDefinition->delete();

        return response()->json([
            'success' => true,
            'message' => 'SLA definition deleted successfully.',
        ]);
    }

    /**
     * Toggle active status of the specified SLA definition.
     */
    public function toggleActive(SlaDefinition $slaDefinition): JsonResponse
    {
        // Ensure user can only toggle definitions from their company
        if ($slaDefinition->company_id !== auth()->user()->company_id) {
            return response()->json([
                'success' => false,
                'error' => 'Unauthorized access to SLA definition.',
            ], 403);
        }

        $slaDefinition->is_active = !$slaDefinition->is_active;
        $slaDefinition->save();
        $slaDefinition->load('creator');

        return response()->json([
            'success' => true,
            'data' => new SlaDefinitionResource($slaDefinition),
        ]);
    }
}
