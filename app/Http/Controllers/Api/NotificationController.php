<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | GET NOTIFICATIONS
    |--------------------------------------------------------------------------
    */
    public function index(Request $request): JsonResponse
    {
        try {

            $notifications = Notification::query()
                ->where('user_id', $request->user()->id)
                ->latest()
                ->latest()
                ->take(20)
                ->get();

            return response()->json([
                'success' => true,
                'message' => 'Notifications fetched successfully.',
                'data' => $notifications,
            ], 200);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch notifications.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | GET UNREAD COUNT
    |--------------------------------------------------------------------------
    */
    public function unreadCount(Request $request): JsonResponse
    {
        try {

            $count = Notification::query()
                ->where('user_id', $request->user()->id)
                ->where('is_read', false)
                ->count();

            return response()->json([
                'success' => true,
                'count' => $count,
            ], 200);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch unread count.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MARK SINGLE NOTIFICATION AS READ
    |--------------------------------------------------------------------------
    */
    public function markAsRead(Request $request, int $id): JsonResponse
    {
        try {

            $notification = Notification::query()
                ->where('user_id', $request->user()->id)
                ->find($id);

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found.',
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | UPDATE ONLY IF UNREAD
            |--------------------------------------------------------------------------
            */
            if (!$notification->is_read) {

                $notification->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Notification marked as read.',
                'data' => $notification,
            ], 200);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to update notification.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | MARK ALL NOTIFICATIONS AS READ
    |--------------------------------------------------------------------------
    */
    public function markAllAsRead(Request $request): JsonResponse
    {
        try {

            Notification::query()
                ->where('user_id', $request->user()->id)
                ->where('is_read', false)
                ->update([
                    'is_read' => true,
                    'read_at' => now(),
                ]);

            return response()->json([
                'success' => true,
                'message' => 'All notifications marked as read.',
            ], 200);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to mark all notifications as read.',
            ], 500);
        }
    }

    /*
    |--------------------------------------------------------------------------
    | DELETE NOTIFICATION
    |--------------------------------------------------------------------------
    */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {

            $notification = Notification::query()
                ->where('user_id', $request->user()->id)
                ->find($id);

            if (!$notification) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found.',
                ], 404);
            }

            $notification->delete();

            return response()->json([
                'success' => true,
                'message' => 'Notification deleted successfully.',
            ], 200);

        } catch (\Throwable $e) {

            report($e);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete notification.',
            ], 500);
        }
    }
}