<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkOrder\StoreWorkOrderCommentRequest;
use App\Http\Resources\WorkOrderCommentResource;
use App\Models\WorkOrder;
use App\Models\WorkOrderComment;
use Illuminate\Http\Request;

class WorkOrderCommentController extends Controller
{
    public function index(Request $request, WorkOrder $workOrder)
    {
        if ($workOrder->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $comments = $workOrder->comments()->with('user:id,first_name,last_name,email')->get();
        return response()->json([
            'success' => true,
            'data' => WorkOrderCommentResource::collection($comments)
        ]);
    }

    public function store(StoreWorkOrderCommentRequest $request, WorkOrder $workOrder)
    {
        if ($workOrder->company_id !== $request->user()->company_id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        $comment = $workOrder->comments()->create([
            'user_id' => $request->user()->id,
            'comment' => $request->validated()['comment'],
            'meta' => $request->validated()['meta'] ?? null,
        ]);

        $comment->load('user:id,first_name,last_name,email');

        return response()->json([
            'success' => true,
            'data' => new WorkOrderCommentResource($comment),
            'message' => 'Comment added'
        ], 201);
    }

    public function destroy(Request $request, WorkOrder $workOrder, WorkOrderComment $comment)
    {
        if ($workOrder->company_id !== $request->user()->company_id || $comment->work_order_id !== $workOrder->id) {
            return response()->json(['success' => false, 'message' => 'Not found'], 404);
        }

        // Allow owner or admin (simple rule; extend with policies if needed)
        if ($comment->user_id !== $request->user()->id) {
            return response()->json(['success' => false, 'message' => 'Forbidden'], 403);
        }

        $comment->delete();

        return response()->json(['success' => true, 'message' => 'Deleted']);
    }
}


