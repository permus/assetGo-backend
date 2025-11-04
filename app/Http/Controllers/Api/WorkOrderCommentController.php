<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\WorkOrder\StoreWorkOrderCommentRequest;
use App\Http\Resources\WorkOrderCommentResource;
use App\Models\WorkOrder;
use App\Models\WorkOrderComment;
use App\Models\WorkOrderAssignment;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class WorkOrderCommentController extends Controller
{
    protected $notificationService;

    public function __construct(NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }
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

        // Send notifications to admins and company owners
        $creator = $request->user();
        try {
            $this->notificationService->createForAdminsAndOwners(
                $creator->company_id,
                [
                    'type' => 'work_order',
                    'action' => 'comment_added',
                    'title' => 'Comment Added to Work Order',
                    'message' => $this->notificationService->formatWorkOrderMessage('comment_added', $workOrder->title),
                    'data' => [
                        'workOrderId' => $workOrder->id,
                        'workOrderTitle' => $workOrder->title,
                        'commentId' => $comment->id,
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

            // Notify work order assignees
            $assignedUserIds = $this->notificationService->getWorkOrderAssignees($workOrder->id);
            if (!empty($assignedUserIds)) {
                // Exclude the comment creator from notifications
                $assignedUserIds = array_filter($assignedUserIds, fn($id) => $id !== $creator->id);
                if (!empty($assignedUserIds)) {
                    $this->notificationService->createForUsers(
                        array_values($assignedUserIds),
                        [
                            'company_id' => $creator->company_id,
                            'type' => 'work_order',
                            'action' => 'comment_added',
                            'title' => 'New Comment on Your Work Order',
                            'message' => "A comment was added to work order '{$workOrder->title}'",
                            'data' => [
                                'workOrderId' => $workOrder->id,
                                'workOrderTitle' => $workOrder->title,
                                'commentId' => $comment->id,
                                'createdBy' => [
                                    'id' => $creator->id,
                                    'name' => $creator->first_name . ' ' . $creator->last_name,
                                ],
                            ],
                            'created_by' => $creator->id,
                        ]
                    );
                }
            }
        } catch (\Exception $e) {
            \Log::warning('Failed to send work order comment notification', [
                'work_order_id' => $workOrder->id,
                'error' => $e->getMessage()
            ]);
        }

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


