<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Models\WorkOrderAssignment;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkOrderAssignmentController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
    public function index(WorkOrder $workOrder)
    {
        if ($workOrder->company_id !== request()->user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Work order not found'
            ], 404);
        }

        $assignments = WorkOrderAssignment::with('user')
            ->where('work_order_id', $workOrder->id)
            ->get();

        return response()->json([
            'success' => true,
            'data' => $assignments,
        ]);
    }

    public function store(Request $request, WorkOrder $workOrder)
    {
        if ($workOrder->company_id !== $request->user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Work order not found'
            ], 404);
        }

        $validated = $request->validate([
            'user_ids' => 'required|array',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        $userIds = collect($validated['user_ids'])->unique()->values();

        $existing = WorkOrderAssignment::where('work_order_id', $workOrder->id)->pluck('user_id');

        $toAdd = $userIds->diff($existing);
        $toRemove = $existing->diff($userIds);

        foreach ($toAdd as $userId) {
            WorkOrderAssignment::create([
                'work_order_id' => $workOrder->id,
                'user_id' => $userId,
                'assigned_by' => Auth::id(),
                'status' => 'assigned',
            ]);
        }

        if ($toRemove->isNotEmpty()) {
            WorkOrderAssignment::where('work_order_id', $workOrder->id)
                ->whereIn('user_id', $toRemove->all())
                ->delete();
        }

        $assignments = WorkOrderAssignment::with('user')
            ->where('work_order_id', $workOrder->id)
            ->get();

        // Send notifications to admins and company owners if assignments were added
        if ($toAdd->isNotEmpty()) {
            $creator = $request->user();
            try {
                $this->notificationService->createForAdminsAndOwners(
                    $creator->company_id,
                    [
                        'type' => 'work_order',
                        'action' => 'assigned',
                        'title' => 'Work Order Assigned',
                        'message' => $this->notificationService->formatWorkOrderMessage('assigned', $workOrder->title),
                        'data' => [
                            'workOrderId' => $workOrder->id,
                            'workOrderTitle' => $workOrder->title,
                            'userIds' => $toAdd->toArray(),
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
                \Log::warning('Failed to send work order assignment notifications', [
                    'work_order_id' => $workOrder->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $assignments,
            'message' => 'Assignments updated successfully',
        ]);
    }

    public function assign(Request $request, WorkOrder $workOrder)
    {
        if ($workOrder->company_id !== $request->user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Work order not found'
            ], 404);
        }

        $validated = $request->validate([
            'user_id' => 'required|integer|exists:users,id',
            'due_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        // Check if work order is already assigned to this user
        $existingAssignment = WorkOrderAssignment::where('work_order_id', $workOrder->id)
            ->where('user_id', $validated['user_id'])
            ->first();

        if ($existingAssignment) {
            return response()->json([
                'success' => false,
                'message' => 'Work order is already assigned to this user'
            ], 400);
        }

        // Create new assignment
        $assignment = WorkOrderAssignment::create([
            'work_order_id' => $workOrder->id,
            'user_id' => $validated['user_id'],
            'assigned_by' => Auth::id(),
            'status' => 'assigned',
            'due_date' => $validated['due_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        // Update the main work order for backward compatibility
        $workOrder->update([
            'assigned_to' => $validated['user_id'],
            'assigned_by' => Auth::id(),
        ]);

        $assignment->load('user');

        // Send notifications to admins and company owners
        $creator = $request->user();
        try {
            $this->notificationService->createForAdminsAndOwners(
                $creator->company_id,
                [
                    'type' => 'work_order',
                    'action' => 'assigned',
                    'title' => 'Work Order Assigned',
                    'message' => $this->notificationService->formatWorkOrderMessage('assigned', $workOrder->title),
                    'data' => [
                        'workOrderId' => $workOrder->id,
                        'workOrderTitle' => $workOrder->title,
                        'assignedUserId' => $validated['user_id'],
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
            \Log::warning('Failed to send work order assignment notifications', [
                'work_order_id' => $workOrder->id,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'data' => $assignment,
            'message' => 'Work order assigned successfully'
        ]);
    }

    public function update(Request $request, WorkOrder $workOrder, WorkOrderAssignment $assignment)
    {
        if ($workOrder->company_id !== $request->user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Work order not found'
            ], 404);
        }

        if ($assignment->work_order_id !== $workOrder->id) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment does not belong to this work order'
            ], 422);
        }

        $validated = $request->validate([
            'status' => 'required|string|in:assigned,accepted,declined,completed'
        ]);

        $assignment->status = $validated['status'];
        $assignment->save();

        $assignment->load('user');

        return response()->json([
            'success' => true,
            'data' => $assignment,
            'message' => 'Assignment updated'
        ]);
    }

    public function destroy(WorkOrder $workOrder, WorkOrderAssignment $assignment)
    {
        if ($workOrder->company_id !== request()->user()->company_id) {
            return response()->json([
                'success' => false,
                'message' => 'Work order not found'
            ], 404);
        }

        if ($assignment->work_order_id !== $workOrder->id) {
            return response()->json([
                'success' => false,
                'message' => 'Assignment does not belong to this work order'
            ], 422);
        }

        $assignment->delete();

        // Send notifications to admins and company owners
        $creator = request()->user();
        try {
            $this->notificationService->createForAdminsAndOwners(
                $creator->company_id,
                [
                    'type' => 'work_order',
                    'action' => 'unassigned',
                    'title' => 'Work Order Assignment Removed',
                    'message' => $this->notificationService->formatWorkOrderMessage('unassigned', $workOrder->title),
                    'data' => [
                        'workOrderId' => $workOrder->id,
                        'workOrderTitle' => $workOrder->title,
                        'removedUserId' => $assignment->user_id,
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
            \Log::warning('Failed to send work order unassignment notifications', [
                'work_order_id' => $workOrder->id,
                'error' => $e->getMessage()
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Assignment removed'
        ]);
    }
}


