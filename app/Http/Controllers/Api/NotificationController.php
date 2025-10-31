<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

class NotificationController extends Controller
{
    public function __construct(private NotificationService $notificationService) {}

    /**
     * List notifications with pagination and filtering
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $user = Auth::user();
            $perPage = min($request->get('per_page', 20), 50);
            $read = $request->get('read'); // null, true, false
            $type = $request->get('type');

            $query = \App\Models\Notification::where('user_id', $user->id)
                ->orderBy('created_at', 'desc');

            // Filter by read status
            if ($read !== null) {
                if ($read) {
                    $query->read();
                } else {
                    $query->unread();
                }
            }

            // Filter by type
            if ($type) {
                $query->ofType($type);
            }

            $notifications = $query->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'notifications' => NotificationResource::collection($notifications->items()),
                    'pagination' => [
                        'current_page' => $notifications->currentPage(),
                        'last_page' => $notifications->lastPage(),
                        'per_page' => $notifications->perPage(),
                        'total' => $notifications->total(),
                        'from' => $notifications->firstItem(),
                        'to' => $notifications->lastItem(),
                    ],
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Failed to fetch notifications', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => config('app.debug')
                    ? 'Failed to fetch notifications: ' . $e->getMessage()
                    : 'Failed to fetch notifications. Please try again later.'
            ], 500);
        }
    }

    /**
     * Get unread count
     */
    public function unreadCount(): JsonResponse
    {
        try {
            $user = Auth::user();
            $count = $this->notificationService->getUnreadCount($user->id);

            return response()->json([
                'success' => true,
                'data' => [
                    'count' => $count,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Failed to get unread count', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => config('app.debug')
                    ? 'Failed to get unread count: ' . $e->getMessage()
                    : 'Failed to get unread count. Please try again later.'
            ], 500);
        }
    }

    /**
     * Mark notification as read
     */
    public function markAsRead(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $success = $this->notificationService->markAsRead($id, $user->id);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'error' => 'Notification not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read',
            ]);
        } catch (Exception $e) {
            Log::error('Failed to mark notification as read', [
                'notification_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => config('app.debug')
                    ? 'Failed to mark notification as read: ' . $e->getMessage()
                    : 'Failed to mark notification as read. Please try again later.'
            ], 500);
        }
    }

    /**
     * Mark all notifications as read
     */
    public function markAllAsRead(): JsonResponse
    {
        try {
            $user = Auth::user();
            $count = $this->notificationService->markAllAsRead($user->id);

            return response()->json([
                'success' => true,
                'message' => "Marked {$count} notifications as read",
                'data' => [
                    'count' => $count,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Failed to mark all notifications as read', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => config('app.debug')
                    ? 'Failed to mark all notifications as read: ' . $e->getMessage()
                    : 'Failed to mark all notifications as read. Please try again later.'
            ], 500);
        }
    }

    /**
     * Delete a notification
     */
    public function destroy(int $id): JsonResponse
    {
        try {
            $user = Auth::user();
            $success = $this->notificationService->delete($id, $user->id);

            if (!$success) {
                return response()->json([
                    'success' => false,
                    'error' => 'Notification not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted',
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete notification', [
                'notification_id' => $id,
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => config('app.debug')
                    ? 'Failed to delete notification: ' . $e->getMessage()
                    : 'Failed to delete notification. Please try again later.'
            ], 500);
        }
    }

    /**
     * Delete all read notifications
     */
    public function deleteRead(): JsonResponse
    {
        try {
            $user = Auth::user();
            $count = \App\Models\Notification::where('user_id', $user->id)
                ->where('read', true)
                ->delete();

            // Broadcast updated unread count
            $this->notificationService->broadcastUnreadCount($user->id);

            return response()->json([
                'success' => true,
                'message' => "Deleted {$count} read notifications",
                'data' => [
                    'count' => $count,
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Failed to delete read notifications', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'error' => config('app.debug')
                    ? 'Failed to delete read notifications: ' . $e->getMessage()
                    : 'Failed to delete read notifications. Please try again later.'
            ], 500);
        }
    }
}
