<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkOrder\StoreWorkOrderStatusRequest;
use App\Http\Requests\WorkOrder\UpdateWorkOrderStatusRequest;
use App\Http\Requests\WorkOrder\StoreWorkOrderPriorityRequest;
use App\Http\Requests\WorkOrder\StoreWorkOrderCategoryRequest;
use App\Http\Resources\WorkOrderStatusResource;
use App\Http\Resources\WorkOrderPriorityResource;
use App\Http\Resources\WorkOrderCategoryResource;
use App\Models\WorkOrderStatus;
use App\Models\WorkOrderPriority;
use App\Models\WorkOrderCategory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MetaWorkOrderController extends Controller
{
    /**
     * Display a listing of statuses.
     */
    public function statusIndex(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $statuses = WorkOrderStatus::forCompany($companyId)->get();
        
        return response()->json(WorkOrderStatusResource::collection($statuses));
    }

    /**
     * Store a newly created status.
     */
    public function statusStore(StoreWorkOrderStatusRequest $request): JsonResponse
    {
        $status = WorkOrderStatus::create($request->validated());
        
        return response()->json(new WorkOrderStatusResource($status), 201);
    }

    /**
     * Update the specified status.
     */
    public function statusUpdate(UpdateWorkOrderStatusRequest $request, $id): JsonResponse
    {
        $status = WorkOrderStatus::findOrFail($id);
        $this->authorize('update', $status);
        
        $status->update($request->validated());
        
        return response()->json(new WorkOrderStatusResource($status));
    }

    /**
     * Remove the specified status.
     */
    public function statusDestroy(Request $request, $id): JsonResponse
    {
        $status = WorkOrderStatus::findOrFail($id);
        $this->authorize('delete', $status);
        
        if ($status->is_management) {
            return response()->json(['message' => 'Management status cannot be deleted.'], 403);
        }
        
        // Check if status is in use
        $inUse = DB::table('work_orders')->where('status_id', $status->id)->exists();
        if ($inUse) {
            return response()->json(['message' => 'Status is in use by work orders.'], 409);
        }
        
        $status->delete();
        
        return response()->json(['message' => 'Deleted']);
    }

    /**
     * Display a listing of priorities.
     */
    public function priorityIndex(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $priorities = WorkOrderPriority::forCompany($companyId)->get();
        
        return response()->json(WorkOrderPriorityResource::collection($priorities));
    }

    /**
     * Store a newly created priority.
     */
    public function priorityStore(StoreWorkOrderPriorityRequest $request): JsonResponse
    {
        $priority = WorkOrderPriority::create($request->validated());
        
        return response()->json(new WorkOrderPriorityResource($priority), 201);
    }

    /**
     * Update the specified priority.
     */
    public function priorityUpdate(Request $request, $id): JsonResponse
    {
        $priority = WorkOrderPriority::findOrFail($id);
        $this->authorize('update', $priority);
        
        $priority->update($request->validated());
        
        return response()->json(new WorkOrderPriorityResource($priority));
    }

    /**
     * Remove the specified priority.
     */
    public function priorityDestroy(Request $request, $id): JsonResponse
    {
        $priority = WorkOrderPriority::findOrFail($id);
        $this->authorize('delete', $priority);
        
        if ($priority->is_management) {
            return response()->json(['message' => 'Management priority cannot be deleted.'], 403);
        }
        
        // Check if priority is in use
        $inUse = DB::table('work_orders')->where('priority_id', $priority->id)->exists();
        if ($inUse) {
            return response()->json(['message' => 'Priority is in use by work orders.'], 409);
        }
        
        $priority->delete();
        
        return response()->json(['message' => 'Deleted']);
    }

    /**
     * Display a listing of categories.
     */
    public function categoryIndex(Request $request): JsonResponse
    {
        $companyId = $request->user()->company_id;
        $categories = WorkOrderCategory::forCompany($companyId)->get();
        
        return response()->json(WorkOrderCategoryResource::collection($categories));
    }

    /**
     * Store a newly created category.
     */
    public function categoryStore(StoreWorkOrderCategoryRequest $request): JsonResponse
    {
        $category = WorkOrderCategory::create($request->validated());
        
        return response()->json(new WorkOrderCategoryResource($category), 201);
    }

    /**
     * Update the specified category.
     */
    public function categoryUpdate(Request $request, $id): JsonResponse
    {
        $category = WorkOrderCategory::findOrFail($id);
        $this->authorize('update', $category);
        
        $category->update($request->validated());
        
        return response()->json(new WorkOrderCategoryResource($category));
    }

    /**
     * Remove the specified category.
     */
    public function categoryDestroy(Request $request, $id): JsonResponse
    {
        $category = WorkOrderCategory::findOrFail($id);
        $this->authorize('delete', $category);
        
        // Check if category is in use
        $inUse = DB::table('work_orders')->where('category_id', $category->id)->exists();
        if ($inUse) {
            return response()->json(['message' => 'Category is in use by work orders.'], 409);
        }
        
        $category->delete(); // Soft delete
        
        return response()->json(['message' => 'Deleted']);
    }
}
