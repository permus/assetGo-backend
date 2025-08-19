<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\WorkOrder;
use App\Models\WorkOrderAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WorkOrderAssignmentController extends Controller
{
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

        return response()->json([
            'success' => true,
            'data' => $assignments,
            'message' => 'Assignments updated successfully',
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

        return response()->json([
            'success' => true,
            'message' => 'Assignment removed'
        ]);
    }
}


