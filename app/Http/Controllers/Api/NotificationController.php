<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Traits\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    use ApiResponse;

    /**
     * Get all notifications for the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min($request->get('per_page', 20), 100);

        $query = Notification::where('user_id', $request->user()->id);

        // Filter by read status
        if ($request->has('is_read')) {
            $query->where('is_read', $request->boolean('is_read'));
        }

        // Filter by type
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $notifications = $query->latest()->paginate($perPage);

        return $this->successResponse(
            NotificationResource::collection($notifications)->response()->getData(true),
            'Notifications retrieved successfully'
        );
    }

    /**
     * Get unread notifications count.
     */
    public function unreadCount(Request $request): JsonResponse
    {
        $count = Notification::where('user_id', $request->user()->id)
            ->unread()
            ->count();

        return $this->successResponse([
            'unread_count' => $count,
        ], 'Unread count retrieved successfully');
    }

    /**
     * Get only unread notifications.
     */
    public function unread(Request $request): JsonResponse
    {
        $perPage = min($request->get('per_page', 20), 100);

        $notifications = Notification::where('user_id', $request->user()->id)
            ->unread()
            ->latest()
            ->paginate($perPage);

        return $this->successResponse(
            NotificationResource::collection($notifications)->response()->getData(true),
            'Unread notifications retrieved successfully'
        );
    }

    /**
     * Get a specific notification.
     */
    public function show(string $notificationId, Request $request): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->findOrFail($notificationId);

        return $this->successResponse(
            new NotificationResource($notification),
            'Notification retrieved successfully'
        );
    }

    /**
     * Mark a notification as read.
     */
    public function markAsRead(string $notificationId, Request $request): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->findOrFail($notificationId);

        $notification->markAsRead();

        return $this->successResponse(
            new NotificationResource($notification->fresh()),
            'Notification marked as read'
        );
    }

    /**
     * Mark a notification as unread.
     */
    public function markAsUnread(string $notificationId, Request $request): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->findOrFail($notificationId);

        $notification->markAsUnread();

        return $this->successResponse(
            new NotificationResource($notification->fresh()),
            'Notification marked as unread'
        );
    }

    /**
     * Mark all notifications as read.
     */
    public function markAllAsRead(Request $request): JsonResponse
    {
        $updated = Notification::where('user_id', $request->user()->id)
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);

        return $this->successResponse([
            'marked_count' => $updated,
        ], 'All notifications marked as read');
    }

    /**
     * Delete a notification.
     */
    public function destroy(string $notificationId, Request $request): JsonResponse
    {
        $notification = Notification::where('user_id', $request->user()->id)
            ->findOrFail($notificationId);

        $notification->delete();

        return $this->deletedResponse('Notification deleted successfully');
    }

    /**
     * Delete all read notifications.
     */
    public function deleteRead(Request $request): JsonResponse
    {
        $deleted = Notification::where('user_id', $request->user()->id)
            ->read()
            ->delete();

        return $this->successResponse([
            'deleted_count' => $deleted,
        ], 'Read notifications deleted successfully');
    }

    /**
     * Delete all notifications for the user.
     */
    public function deleteAll(Request $request): JsonResponse
    {
        $deleted = Notification::where('user_id', $request->user()->id)
            ->delete();

        return $this->successResponse([
            'deleted_count' => $deleted,
        ], 'All notifications deleted successfully');
    }

    /**
     * Get notification statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $userId = $request->user()->id;

        $total = Notification::where('user_id', $userId)->count();
        $unread = Notification::where('user_id', $userId)->unread()->count();
        $read = Notification::where('user_id', $userId)->read()->count();

        // Count by type
        $byType = Notification::where('user_id', $userId)
            ->selectRaw('type, COUNT(*) as count')
            ->groupBy('type')
            ->pluck('count', 'type');

        return $this->successResponse([
            'total' => $total,
            'unread' => $unread,
            'read' => $read,
            'by_type' => $byType,
        ], 'Notification statistics retrieved successfully');
    }
}
